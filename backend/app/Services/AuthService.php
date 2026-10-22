<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuthMethod;
use App\Exceptions\AuthenticationFailedException;
use App\Exceptions\TenantAccessDeniedException;
use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Models\User;
use App\Support\Auth\PinFingerprint;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Autenticación dual sobre la sesión Sanctum SPA (cookie HttpOnly + CSRF):
 *  - Credenciales (correo y contraseña) para administración.
 *  - PIN de 6 dígitos + RUC para el cambio rápido de usuario en terminales compartidas.
 *
 * Toda autenticación, exitosa o fallida, queda en el log de auditoría "auth".
 */
final class AuthService
{
    public const string SESSION_METHOD = 'auth.method';

    public const string SESSION_LAST_ACTIVITY = 'auth.last_activity';

    private static ?string $timingHash = null;

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly PermissionRegistrar $permissions,
    ) {}

    public function loginWithPassword(Request $request, string $email, string $password): User
    {
        $this->ensureStatefulRequest($request);
        $normalizedEmail = Str::lower(trim($email));

        $user = User::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->whereRaw('lower(email) = ?', [$normalizedEmail])
            ->first();

        if ($user === null || $user->password === null || ! Hash::check($password, $user->password)) {
            $this->equalizeTiming($user, $password);
            $this->audit($request, 'login_failed', 'Intento de acceso fallido con credenciales.', null, $user?->company_id, [
                'method' => AuthMethod::Password->value,
                'email' => $normalizedEmail,
            ]);

            throw AuthenticationFailedException::invalidCredentials();
        }

        return $this->startSession($request, $user, AuthMethod::Password);
    }

    public function loginWithPin(Request $request, string $companyRuc, string $pin): User
    {
        $this->ensureStatefulRequest($request);

        $company = Company::query()->where('ruc', $companyRuc)->first();
        $user = $company === null ? null : User::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->where('pin_lookup', PinFingerprint::make($company->id, $pin))
            ->first();

        if ($user === null || ! $user->verifyPin($pin)) {
            $this->equalizeTiming($user, $pin);
            $this->audit($request, 'login_failed', 'Intento de acceso fallido con PIN.', null, $company?->id, [
                'method' => AuthMethod::Pin->value,
                'company_ruc' => $companyRuc,
            ]);

            throw AuthenticationFailedException::invalidCredentials();
        }

        return $this->startSession($request, $user, AuthMethod::Pin);
    }

    public function logout(Request $request): void
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->audit($request, 'logout', 'Cierre de sesión.', $user, $user->company_id, []);
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    /**
     * Perfil con empresa, roles y permisos del equipo (empresa) activo.
     */
    public function loadProfile(User $user): User
    {
        return $user->loadMissing(['company', 'roles.permissions', 'permissions']);
    }

    public function sessionMethod(Request $request): ?AuthMethod
    {
        $method = $request->hasSession() ? $request->session()->get(self::SESSION_METHOD) : null;

        return is_string($method) ? AuthMethod::tryFrom($method) : null;
    }

    private function startSession(Request $request, User $user, AuthMethod $method): User
    {
        $company = $user->company()->first();

        if (! $user->is_active) {
            throw TenantAccessDeniedException::inactiveUser();
        }

        if ($company === null || ! $company->is_active) {
            throw TenantAccessDeniedException::inactiveCompany();
        }

        // Cambio de usuario en la terminal: la sesión anterior se reemplaza (mitiga fijación de sesión).
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_METHOD, $method->value);
        $request->session()->put(self::SESSION_LAST_ACTIVITY, now()->getTimestamp());

        $this->tenant->set($company);
        $this->permissions->setPermissionsTeamId($company);
        $user->setRelation('company', $company);

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $this->audit($request, 'login', $method === AuthMethod::Pin ? 'Acceso por PIN de terminal.' : 'Acceso con credenciales.', $user, $company->id, [
            'method' => $method->value,
        ]);

        return $this->loadProfile($user);
    }

    private function ensureStatefulRequest(Request $request): void
    {
        if (! $request->hasSession()) {
            throw AuthenticationFailedException::statefulSessionRequired();
        }
    }

    /**
     * Verifica contra un hash de referencia cuando no hay usuario: el tiempo de
     * respuesta no revela si el correo o el PIN existen.
     */
    private function equalizeTiming(?User $user, string $secret): void
    {
        if ($user === null) {
            Hash::check($secret, self::$timingHash ??= Hash::make(Str::random(40)));
        }
    }

    /**
     * @param  array<string, string>  $properties
     */
    private function audit(Request $request, string $event, string $description, ?User $user, ?int $companyId, array $properties): void
    {
        $logger = activity('auth')
            ->event($event)
            ->withProperties([...$properties, 'user_agent' => Str::limit((string) $request->userAgent(), 255, '')])
            ->tap(static function (Model $activity) use ($companyId, $request): void {
                $traceId = Context::get('trace_id');

                $activity->setAttribute('company_id', $companyId);
                $activity->setAttribute('trace_id', is_string($traceId) ? $traceId : null);
                $activity->setAttribute('ip_address', $request->ip());
            });

        if ($user !== null) {
            $logger->performedOn($user)->causedBy($user);
        }

        $logger->log($description);
    }
}
