<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Detalle del comprobante: ítem por ítem o glosa única por consumo (permitida en restaurantes).
 */
enum ConsumptionFormat: string
{
    case Detailed = 'DETAILED';
    case ByConsumption = 'BY_CONSUMPTION';

    public const string CONSUMPTION_GLOSS = 'POR CONSUMO DE ALIMENTOS Y BEBIDAS';

    public function label(): string
    {
        return match ($this) {
            self::Detailed => 'Detallado',
            self::ByConsumption => 'Por consumo',
        };
    }
}
