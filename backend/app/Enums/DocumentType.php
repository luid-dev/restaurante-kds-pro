<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipos de comprobante (catálogo SUNAT 01) y nota de venta interna.
 */
enum DocumentType: string
{
    case Invoice = '01';
    case Receipt = '03';
    case CreditNote = '07';
    case SalesNote = '00';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Factura electrónica',
            self::Receipt => 'Boleta de venta electrónica',
            self::CreditNote => 'Nota de crédito electrónica',
            self::SalesNote => 'Nota de venta',
        };
    }

    /**
     * La nota de venta es un documento interno: no se envía a SUNAT.
     */
    public function isElectronic(): bool
    {
        return $this !== self::SalesNote;
    }
}
