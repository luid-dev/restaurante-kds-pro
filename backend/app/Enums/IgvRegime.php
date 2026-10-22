<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Régimen de IGV aplicable a la empresa.
 *
 * La tasa configurada en companies.settings es la tasa total del comprobante
 * (IGV + IPM). El régimen MYPE de restaurantes (Ley 31556 y modificatorias:
 * Ley 32219, Ley 32387) cambia por año, por eso la tasa es configurable y auditada.
 */
enum IgvRegime: string
{
    case General = 'GENERAL';
    case MypeRestaurant = 'MYPE_RESTAURANT';

    public const string GENERAL_RATE = '18.00';

    public function label(): string
    {
        return match ($this) {
            self::General => 'Régimen general (IGV 18%)',
            self::MypeRestaurant => 'MYPE restaurantes y hoteles (Ley 31556)',
        };
    }

    /**
     * Tasa total sugerida al configurar el régimen. Para MYPE corresponde a 2026
     * según SUNAT: IGV 8% + IPM 2.5% = 10.50%. Debe revisarse cada ejercicio.
     */
    public function suggestedRate(): string
    {
        return match ($this) {
            self::General => self::GENERAL_RATE,
            self::MypeRestaurant => '10.50',
        };
    }
}
