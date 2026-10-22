<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\AuthMethod;
use App\Exceptions\SessionExpiredException;
use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sesión corta para accesos por PIN: en una terminal compartida, tras N minutos
 * sin actividad la sesión se cierra y el siguiente mozo debe ingresar su PIN.
 */
final class EnforcePinSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession() && $request->session()->get(AuthService::SESSION_METHOD) === AuthMethod::Pin->value) {
            $lastActivity = $request->session()->get(AuthService::SESSION_LAST_ACTIVITY);
            $idleSeconds = config()->integer('auth.pin.idle_timeout_minutes', 30) * 60;

            if (! is_int($lastActivity) || now()->getTimestamp() - $lastActivity > $idleSeconds) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw new SessionExpiredException;
            }

            $request->session()->put(AuthService::SESSION_LAST_ACTIVITY, now()->getTimestamp());
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
