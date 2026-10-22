<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'CASH';
    case Yape = 'YAPE';
    case Plin = 'PLIN';
    case Card = 'CARD';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::Yape => 'Yape',
            self::Plin => 'Plin',
            self::Card => 'Tarjeta',
        };
    }

    /**
     * Solo el efectivo registra monto entregado y vuelto.
     */
    public function givesChange(): bool
    {
        return $this === self::Cash;
    }
}
