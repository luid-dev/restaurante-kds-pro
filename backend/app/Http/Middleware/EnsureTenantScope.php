<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\TenantAccessDeniedException;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija la empresa del usuario autenticado como tenant activo (CompanyScope)
 * y como "team" de Spatie Permission. Debe ejecutarse después de auth:sanctum
 * y antes de SubstituteBindings (ver prioridad en bootstrap/app.php).
 */
final class EnsureTenantScope
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly PermissionRegistrar $permissions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        if (! $user->is_active) {
            throw TenantAccessDeniedException::inactiveUser();
        }

        $company = $user->company()->first();

        if ($company === null || ! $company->is_active) {
            throw TenantAccessDeniedException::inactiveCompany();
        }

        $this->tenant->set($company);
        $this->permissions->setPermissionsTeamId($company);
        $user->setRelation('company', $company)->unsetRelation('roles')->unsetRelation('permissions');

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
