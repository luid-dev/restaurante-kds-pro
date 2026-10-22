<?php

declare(strict_types=1);

use App\Exceptions\ApiException;
use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\AssignTraceId;
use App\Http\Middleware\EnforcePinSessionTimeout;
use App\Http\Middleware\EnsureTenantScope;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum', 'pin.timeout', 'tenant']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Capa más externa: toda respuesta (incluidos errores) lleva trace_id y cabeceras de seguridad.
        $middleware->prepend([
            AssignTraceId::class,
            SecurityHeadersMiddleware::class,
            ForceJsonResponse::class,
        ]);

        $middleware->statefulApi();
        $middleware->throttleApi();

        $middleware->alias([
            'pin.timeout' => EnforcePinSessionTimeout::class,
            'tenant' => EnsureTenantScope::class,
        ]);

        // El tenant debe existir antes del route model binding (CompanyScope y teams de Spatie).
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureTenantScope::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'pin',
            'pin_code',
            'pin_code_confirmation',
        ]);

        // Reglas de negocio esperadas (400, 401, 403, 404, 409, 422) no son incidentes: solo se
        // registran los 5xx (certificado, firma, configuración fiscal) y las excepciones no controladas.
        // 401/403/404/419/422 del framework ya están fuera del reporte por defecto.
        $exceptions->dontReportWhen(static fn (Throwable $e): bool => $e instanceof ApiException && $e->status() < 500);

        $exceptions->shouldRenderJsonWhen(static fn (): bool => true);

        $exceptions->render(new ApiExceptionRenderer);
    })->create();
