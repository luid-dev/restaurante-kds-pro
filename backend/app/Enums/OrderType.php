<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderType: string
{
    case DineIn = 'DINE_IN';
    case Takeout = 'TAKEOUT';
    case Delivery = 'DELIVERY';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Salón',
            self::Takeout => 'Para llevar',
            self::Delivery => 'Delivery',
        };
    }

    public function requiresTable(): bool
    {
        return $this === self::DineIn;
    }

    /**
     * Para llevar y delivery cobran empaque y descuentan los insumos de envase de la receta.
     */
    public function usesPackaging(): bool
    {
        return $this !== self::DineIn;
    }
}
