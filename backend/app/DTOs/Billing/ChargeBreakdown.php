<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

use App\Enums\ChargeMode;
use App\Models\OrderItem;

/**
 * Resultado del cálculo de un cobro (BCMath): lo que SunatService convierte en comprobante.
 */
final readonly class ChargeBreakdown
{
    /**
     * @param  list<array{item: OrderItem, quantity: numeric-string, gross: numeric-string, packaging: numeric-string}>  $lines
     * @param  numeric-string  $gross  Platos + empaque, IGV incluido
     * @param  numeric-string  $serviceCharge  Recargo al consumo (fuera de la base del IGV)
     * @param  numeric-string  $total
     */
    public function __construct(
        public ChargeMode $mode,
        public array $lines,
        public string $gross,
        public string $serviceCharge,
        public string $total,
        public bool $settlesOrder,
    ) {}
}
