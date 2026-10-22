<?php

declare(strict_types=1);

namespace App\DTOs\Orders;

use App\Support\Decimal;
use InvalidArgumentException;

/**
 * Plato que el mozo envía a cocina: cantidad, indicaciones predefinidas y nota libre.
 */
final readonly class OrderLineData
{
    /**
     * @var numeric-string
     */
    public string $quantity;

    /**
     * @param  list<string>  $modifiers
     */
    public function __construct(
        public string $menuItemUuid,
        string $quantity,
        public array $modifiers = [],
        public ?string $notes = null,
    ) {
        $normalized = Decimal::normalize($quantity, 2);

        if (! Decimal::isBetween($normalized, '0.01', '99.00')) {
            throw new InvalidArgumentException('La cantidad debe estar entre 1 y 99.');
        }

        $this->quantity = $normalized;
    }
}
