<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

use App\Support\Decimal;
use InvalidArgumentException;

/**
 * Plato (y cantidad) que paga un comensal al dividir la cuenta.
 */
final readonly class ChargeLineData
{
    /** @var numeric-string */
    public string $quantity;

    public function __construct(public string $orderItemUuid, string $quantity)
    {
        $this->quantity = Decimal::normalize($quantity, 2);

        if (bccomp($this->quantity, '0', 2) <= 0) {
            throw new InvalidArgumentException('La cantidad a cobrar debe ser mayor a cero.');
        }
    }
}
