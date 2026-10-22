<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCode;

final class AuthenticationFailedException extends ApiException
{
    /**
     * Mensaje único para usuario inexistente, clave o PIN errado: evita la enumeración de cuentas.
     */
    public static function invalidCredentials(): self
    {
        return new self('Credenciales incorrectas. Verifique e intente nuevamente.', 401, ErrorCode::InvalidCredentials);
    }

    /**
     * El login abre una sesión por cookie: solo se acepta desde el SPA (origen stateful de Sanctum).
     */
    public static function statefulSessionRequired(): self
    {
        return new self('El inicio de sesión solo está disponible desde la aplicación web del restaurante.', 400, ErrorCode::HttpError);
    }
}
