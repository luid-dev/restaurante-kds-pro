<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Movimientos del kardex valorizado (formato SUNAT 13.1) con su tipo de operación (tabla 12).
 */
enum KardexMovementType: string
{
    case InitialBalance = 'INITIAL_BALANCE';
    case Purchase = 'PURCHASE';
    case SaleConsumption = 'SALE_CONSUMPTION';
    case Waste = 'WASTE';
    case AdjustmentIn = 'ADJUSTMENT_IN';
    case AdjustmentOut = 'ADJUSTMENT_OUT';

    public function sunatOperationCode(): string
    {
        return match ($this) {
            self::InitialBalance => '16',
            self::Purchase => '02',
            self::SaleConsumption => '10',
            self::Waste => '13',
            self::AdjustmentIn, self::AdjustmentOut => '99',
        };
    }

    public function isInbound(): bool
    {
        return match ($this) {
            self::InitialBalance, self::Purchase, self::AdjustmentIn => true,
            self::SaleConsumption, self::Waste, self::AdjustmentOut => false,
        };
    }
}
