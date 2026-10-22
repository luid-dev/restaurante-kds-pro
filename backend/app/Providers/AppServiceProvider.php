<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Models\Scopes\CompanyScope;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureModels();
        $this->configureAuthentication();
        $this->configureRateLimiting();

        Date::use(CarbonImmutable::class);
        JsonResource::withoutWrapping();
        DB::prohibitDestructiveCommands($this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceHttps();
        }
    }

    /**
     * Strict Mode: sin lazy loading, sin atributos descartados en silencio y sin
     * acceso a atributos no cargados. En producción un N+1 se registra en lugar
     * de interrumpir la operación de caja o cocina.
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict();

        if ($this->app->isProduction()) {
            Model::handleLazyLoadingViolationUsing(static function (Model $model, string $relation): void {
                Log::warning('Lazy loading detectado.', ['model' => $model::class, 'relation' => $relation]);
            });
        }
    }

    private function configureAuthentication(): void
    {
        // El usuario se resuelve antes de conocer el tenant (es quien lo define),
        // por eso el proveedor de autenticación consulta sin CompanyScope.
        Auth::provider('tenant-eloquent', static function (Application $app, array $config): EloquentUserProvider {
            /** @var class-string<Model> $model */
            $model = $config['model'];

            return (new EloquentUserProvider($app->make('hash'), $model))
                ->withQuery(static fn (Builder $query): Builder => $query->withoutGlobalScope(CompanyScope::class));
        });

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Password::defaults(fn (): Password => $this->app->isProduction()
            ? Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(8));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', static function (Request $request): Limit {
            $identifier = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(240)->by(is_scalar($identifier) ? 'user:'.$identifier : 'ip:'.$request->ip());
        });

        // Fuerza bruta de contraseñas: por cuenta+IP y por IP.
        RateLimiter::for('auth-password', static function (Request $request): array {
            $email = $request->input('email');
            $account = is_string($email) ? Str::lower(trim($email)) : '';

            return [
                Limit::perMinute(5)->by('auth-password:'.$account.'|'.$request->ip()),
                Limit::perMinute(30)->by('auth-password-ip:'.$request->ip()),
            ];
        });

        // El RUC es público: el espacio de 10^6 PIN se protege por terminal (IP) y por empresa.
        RateLimiter::for('auth-pin', static function (Request $request): array {
            $ruc = $request->input('company_ruc');
            $company = is_string($ruc) ? $ruc : '';

            return [
                Limit::perMinute(5)->by('auth-pin:'.$company.'|'.$request->ip()),
                Limit::perMinutes(15, 60)->by('auth-pin-company:'.$company),
            ];
        });
    }
}
