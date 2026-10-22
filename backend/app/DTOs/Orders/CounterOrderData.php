<?php

declare(strict_types=1);

namespace App\DTOs\Orders;

use App\Enums\OrderType;
use InvalidArgumentException;

/**
 * Pedido de mostrador: para llevar (nombre opcional) o delivery (dirección y teléfono obligatorios).
 */
final readonly class CounterOrderData
{
    public function __construct(
        public OrderType $orderType,
        public ?string $customerName,
        public ?string $deliveryAddress = null,
        public ?string $deliveryPhone = null,
    ) {
        if ($orderType === OrderType::DineIn) {
            throw new InvalidArgumentException('Los pedidos de salón se abren desde una mesa.');
        }

        if ($orderType === OrderType::Delivery && ($deliveryAddress === null || $deliveryPhone === null)) {
            throw new InvalidArgumentException('El delivery requiere dirección y teléfono.');
        }
    }
}
