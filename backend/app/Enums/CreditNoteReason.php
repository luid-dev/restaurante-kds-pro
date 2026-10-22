<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Motivos de nota de crédito (catálogo SUNAT 09) que anulan el comprobante completo.
 */
enum CreditNoteReason: string
{
    case OperationCancelled = '01';
    case RucError = '02';
    case FullReturn = '06';

    public function label(): string
    {
        return match ($this) {
            self::OperationCancelled => 'Anulación de la operación',
            self::RucError => 'Anulación por error en el RUC',
            self::FullReturn => 'Devolución total',
        };
    }

    /**
     * El error en el RUC solo corrige facturas: una boleta no identifica al adquirente con RUC.
     */
    public function appliesTo(DocumentType $type): bool
    {
        return match ($this) {
            self::RucError => $type === DocumentType::Invoice,
            self::OperationCancelled, self::FullReturn => $type === DocumentType::Invoice || $type === DocumentType::Receipt,
        };
    }

    /**
     * Anulación y devolución revierten un consumo real: los insumos ya cocinados
     * pasan de costo de ventas a merma. El error en el RUC solo corrige el documento.
     */
    public function reclassifiesConsumptionAsWaste(): bool
    {
        return $this !== self::RucError;
    }
}
