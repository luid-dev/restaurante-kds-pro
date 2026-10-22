<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad (OWASP Secure Headers / ASVS V14) para una API JSON.
 */
final class SecurityHeadersMiddleware
{
    /**
     * @var array<string, string>
     */
    private const array HEADERS = [
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'",
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Cross-Origin-Resource-Policy' => 'same-site',
        'Permissions-Policy' => 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()',
        'Referrer-Policy' => 'no-referrer',
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'X-Permitted-Cross-Domain-Policies' => 'none',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        /** @var Response $response */
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            $response->headers->set($name, $value);
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Las respuestas de la API contienen datos de negocio: no deben quedar en cachés intermedias.
        if (! $response->headers->hasCacheControlDirective('public')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
