<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCode;
use RuntimeException;
use Throwable;

/**
 * Base de las excepciones de dominio que viajan al cliente con mensaje propio
 * (p. ej. mesa ya ocupada, stock insuficiente). El renderer global las convierte
 * al contrato JSON de error con su código y estado HTTP.
 */
abstract class ApiException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        string $message,
        private readonly int $status = 422,
        private readonly ErrorCode $errorCode = ErrorCode::BusinessRuleViolation,
        private readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): ErrorCode
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
