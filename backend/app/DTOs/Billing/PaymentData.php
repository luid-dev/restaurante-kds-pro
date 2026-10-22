<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

use App\Enums\PaymentMethod;
use App\Support\Decimal;
use InvalidArgumentException;

/**
 * Un medio de pago dentro de un cobro (mixto: varios suman el total exacto).
 */
final readonly class PaymentData
{
    /** @var numeric-string */
    public string $amount;

    /** @var numeric-string|null */
    public ?string $receivedAmount;

    public function __construct(
        public PaymentMethod $method,
        string $amount,
        ?string $receivedAmount = null,
        public ?string $reference = null,
    ) {
        $this->amount = Decimal::normalize($amount, 2);

        if (bccomp($this->amount, '0', 2) <= 0) {
            throw new InvalidArgumentException('Cada pago debe ser mayor a cero.');
        }

        if (! $method->givesChange()) {
            $this->receivedAmount = null;

            return;
        }

        $received = Decimal::normalize($receivedAmount ?? $amount, 2);

        if (bccomp($received, $this->amount, 2) < 0) {
            throw new InvalidArgumentException('El efectivo recibido no cubre el monto a pagar.');
        }

        $this->receivedAmount = $received;
    }

    /**
     * @return numeric-string
     */
    public function change(): string
    {
        return $this->receivedAmount === null ? '0.00' : Decimal::sub($this->receivedAmount, $this->amount, 2);
    }
}
