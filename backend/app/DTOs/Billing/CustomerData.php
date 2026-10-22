<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

use App\Support\Billing\Ruc;
use InvalidArgumentException;

/**
 * Adquiriente del comprobante (catálogo SUNAT 06): 0 sin documento, 1 DNI, 4 CE, 6 RUC, 7 pasaporte.
 */
final readonly class CustomerData
{
    public const string ANONYMOUS_NAME = 'CLIENTES VARIOS';

    public function __construct(
        public string $documentType = '0',
        public ?string $documentNumber = null,
        public ?string $name = null,
        public ?string $address = null,
    ) {
        $valid = match ($documentType) {
            '0' => $documentNumber === null,
            '1' => $documentNumber !== null && preg_match('/^\d{8}$/', $documentNumber) === 1,
            '4', '7' => $documentNumber !== null && preg_match('/^[A-Z0-9]{6,15}$/', $documentNumber) === 1,
            '6' => $documentNumber !== null && Ruc::isValid($documentNumber),
            default => false,
        };

        if (! $valid) {
            throw new InvalidArgumentException('Documento del cliente inválido.');
        }

        if ($documentType === '6' && ($name === null || trim($name) === '')) {
            throw new InvalidArgumentException('Ingrese la razón social del cliente.');
        }
    }

    public static function anonymous(): self
    {
        return new self;
    }

    public function isIdentified(): bool
    {
        return $this->documentType !== '0';
    }
}
