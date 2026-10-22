<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asigna un trace_id (UUIDv7) a cada petición. Se publica en Context para que
 * aparezca en logs, jobs y auditoría, y se devuelve en la cabecera X-Trace-Id.
 *
 * El identificador lo genera siempre el servidor: un valor enviado por el
 * cliente podría falsear la correlación de la pista de auditoría.
 */
final class AssignTraceId
{
    public const string HEADER = 'X-Trace-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $traceId = (string) Str::uuid7();

        Context::add('trace_id', $traceId);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set(self::HEADER, $traceId);

        return $response;
    }
}
