<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCode;

final class TenantAccessDeniedException extends ApiException
{
    public static function inactiveCompany(): self
    {
        return new self('La empresa está suspendida. Contacte al administrador.', 403, ErrorCode::TenantAccessDenied);
    }

    public static function inactiveUser(): self
    {
        return new self('El usuario está deshabilitado. Contacte al administrador.', 403, ErrorCode::TenantAccessDenied);
    }
}
