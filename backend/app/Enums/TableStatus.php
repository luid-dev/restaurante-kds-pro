<?php

declare(strict_types=1);

namespace App\Enums;

enum TableStatus: string
{
    case Available = 'AVAILABLE';
    case Occupied = 'OCCUPIED';
    case PreBill = 'PRE_BILL';
    case Reserved = 'RESERVED';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Libre',
            self::Occupied => 'Ocupada',
            self::PreBill => 'Precuenta',
            self::Reserved => 'Reservada',
        };
    }
}
