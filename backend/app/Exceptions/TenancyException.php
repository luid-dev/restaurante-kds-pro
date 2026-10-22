<?php

declare(strict_types=1);

namespace App\Exceptions;

use LogicException;

/**
 * Violación del aislamiento multi-empresa. Es un error de programación:
 * se reporta en los logs y el cliente solo recibe un 500 genérico.
 */
final class TenancyException extends LogicException
{
    /**
     * @param  class-string  $model
     */
    public static function missingContext(string $model): self
    {
        return new self("No hay empresa activa en el contexto para operar sobre [{$model}].");
    }

    /**
     * @param  class-string  $model
     */
    public static function crossTenantWrite(string $model): self
    {
        return new self("Intento de escribir [{$model}] en una empresa distinta a la del contexto activo.");
    }

    /**
     * @param  class-string  $model
     */
    public static function companyReassignment(string $model): self
    {
        return new self("El company_id de [{$model}] es inmutable.");
    }
}
