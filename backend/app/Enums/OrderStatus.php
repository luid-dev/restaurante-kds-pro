<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Open = 'OPEN';
    case PreBilled = 'PRE_BILLED';
    case Paid = 'PAID';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierta',
            self::PreBilled => 'Precuenta emitida',
            self::Paid => 'Pagada',
            self::Cancelled => 'Anulada',
        };
    }

    public function isClosed(): bool
    {
        return $this === self::Paid || $this === self::Cancelled;
    }
}
