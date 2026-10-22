<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo de afectación del IGV (catálogo SUNAT 07).
 */
enum TaxAffectationType: string
{
    case Taxed = '10';
    case Exempt = '20';
    case Unaffected = '30';

    public function label(): string
    {
        return match ($this) {
            self::Taxed => 'Gravado - Operación onerosa',
            self::Exempt => 'Exonerado - Operación onerosa',
            self::Unaffected => 'Inafecto - Operación onerosa',
        };
    }

    /**
     * Solo la operación gravada paga IGV (10.50% MYPE o 18.00% según la empresa);
     * exonerada (tributo 9997 EXO) e inafecta (9998 INA) van con IGV 0.
     */
    public function isTaxed(): bool
    {
        return $this === self::Taxed;
    }

    /**
     * Glosa de la línea "Por consumo" cuando la cuenta mezcla afectaciones.
     */
    public function consumptionGloss(): string
    {
        return match ($this) {
            self::Taxed => ConsumptionFormat::CONSUMPTION_GLOSS,
            self::Exempt => ConsumptionFormat::CONSUMPTION_GLOSS.' - EXONERADO',
            self::Unaffected => ConsumptionFormat::CONSUMPTION_GLOSS.' - INAFECTO',
        };
    }
}
