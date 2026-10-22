<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Unidades de medida (catálogo SUNAT 03) usadas en insumos y kardex.
 */
enum UnitOfMeasure: string
{
    case Unit = 'NIU';
    case Kilogram = 'KGM';
    case Liter = 'LTR';

    public function symbol(): string
    {
        return match ($this) {
            self::Unit => 'UND',
            self::Kilogram => 'KG',
            self::Liter => 'L',
        };
    }
}
