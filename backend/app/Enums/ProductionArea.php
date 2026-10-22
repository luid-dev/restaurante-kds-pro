<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Áreas de producción a las que se enrutan las comandas (pantallas KDS e impresoras).
 */
enum ProductionArea: string
{
    case GrillOven = 'GRILL_OVEN';
    case HotKitchen = 'HOT_KITCHEN';
    case ColdBar = 'COLD_BAR';

    public function label(): string
    {
        return match ($this) {
            self::GrillOven => 'Horno y Parrilla',
            self::HotKitchen => 'Cocina Caliente',
            self::ColdBar => 'Barra Fría',
        };
    }

    /**
     * Tiempo estándar de preparación (minutos) a partir del cual el KDS marca KDS_DELAYED.
     */
    public function defaultTargetMinutes(): int
    {
        return match ($this) {
            self::GrillOven => 25,
            self::HotKitchen => 15,
            self::ColdBar => 5,
        };
    }
}
