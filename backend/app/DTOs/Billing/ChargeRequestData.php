<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

use App\Enums\ChargeMode;
use App\Enums\ConsumptionFormat;
use App\Support\Decimal;
use InvalidArgumentException;

/**
 * Cobro solicitado en caja: qué se cobra (todo, platos o monto), con qué
 * comprobante (serie y formato) y con qué medios de pago.
 */
final readonly class ChargeRequestData
{
    /** @var numeric-string|null */
    public ?string $amount;

    /**
     * @param  list<ChargeLineData>  $lines
     * @param  list<PaymentData>  $payments
     */
    public function __construct(
        public ChargeMode $mode,
        public string $series,
        public ConsumptionFormat $format,
        public CustomerData $customer,
        public array $payments,
        public array $lines = [],
        ?string $amount = null,
    ) {
        if ($payments === []) {
            throw new InvalidArgumentException('Registre al menos un medio de pago.');
        }

        if ($mode === ChargeMode::Items && $lines === []) {
            throw new InvalidArgumentException('Seleccione los platos a cobrar.');
        }

        if ($mode === ChargeMode::Amount) {
            if ($amount === null) {
                throw new InvalidArgumentException('Indique el monto a cobrar.');
            }

            if ($format === ConsumptionFormat::Detailed) {
                throw new InvalidArgumentException('Un cobro por monto o partes iguales solo admite el formato "Por consumo".');
            }
        }

        $this->amount = $amount === null ? null : Decimal::normalize($amount, 2);
    }

    /**
     * @return numeric-string
     */
    public function paymentsTotal(): string
    {
        return array_reduce($this->payments, static fn (string $sum, PaymentData $payment): string => Decimal::add($sum, $payment->amount, 2), '0.00');
    }
}
