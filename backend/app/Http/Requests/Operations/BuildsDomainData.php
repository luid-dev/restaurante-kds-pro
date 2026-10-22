<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations;

use Closure;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Las invariantes de los DTOs (montos, documentos) se devuelven como 422 en el campo indicado.
 */
trait BuildsDomainData
{
    /**
     * @template TData
     *
     * @param  Closure(): TData  $factory
     * @return TData
     */
    protected function buildData(string $field, Closure $factory): mixed
    {
        try {
            return $factory();
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([$field => [$exception->getMessage()]]);
        }
    }
}
