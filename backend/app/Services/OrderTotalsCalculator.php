<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Company\CompanySettingsData;
use App\Enums\OrderItemStatus;
use App\Models\Order;
use App\Support\Decimal;

/**
 * Totales de la comanda con BCMath:
 *  - subtotal: platos vigentes (precio con IGV si prices_include_tax).
 *  - empaque: recargo por plato en para llevar / delivery o con la indicación "Para llevar".
 *  - IGV: tasa total configurada (IGV + IPM) solo sobre platos gravados (10) y
 *    empaque; exonerados (20) e inafectos (30) no generan impuesto.
 *  - recargo al consumo: fuera de la base del IGV (cargo SUNAT 46).
 */
final class OrderTotalsCalculator
{
    /**
     * Recalcula y persiste los totales de la comanda (dentro de su transacción).
     */
    public function refresh(Order $order, CompanySettingsData $settings): void
    {
        $order->load('items.menuItem');
        $order->update($this->calculate($order, $settings));
    }

    /**
     * Requiere items.menuItem cargados.
     *
     * @return array{subtotal: numeric-string, packaging_total: numeric-string, tax_amount: numeric-string, service_charge: numeric-string, total_amount: numeric-string}
     */
    public function calculate(Order $order, CompanySettingsData $settings): array
    {
        $subtotal = '0';
        $untaxed = '0';
        $packaging = '0';

        foreach ($order->items as $item) {
            if ($item->status === OrderItemStatus::Voided) {
                continue;
            }

            $amount = Decimal::mul($item->unit_price, $item->quantity, 4);
            $subtotal = Decimal::add($subtotal, $amount);

            if (! $item->tax_affectation_type->isTaxed()) {
                $untaxed = Decimal::add($untaxed, $amount);
            }

            if ($item->usesPackaging($order->order_type)) {
                $packaging = Decimal::add($packaging, Decimal::mul($item->menuItem->packaging_surcharge, $item->quantity, 4));
            }
        }

        $subtotal = Decimal::round($subtotal, 2);
        $untaxed = Decimal::round($untaxed, 2);
        $packaging = Decimal::round($packaging, 2);
        $taxableGross = Decimal::add(Decimal::sub($subtotal, $untaxed, 2), $packaging, 2);
        $rate = Decimal::div($settings->tax->igvRate, '100', 6);

        if ($settings->tax->pricesIncludeTax) {
            $base = Decimal::div($taxableGross, Decimal::add('1', $rate, 6), 2);
            $tax = Decimal::sub($taxableGross, $base, 2);
        } else {
            $tax = Decimal::mul($taxableGross, $rate, 2);
        }

        $gross = Decimal::add(Decimal::add($subtotal, $packaging, 2), $settings->tax->pricesIncludeTax ? '0' : $tax, 2);

        $serviceCharge = '0.00';

        if ($settings->serviceCharge->enabled && in_array($order->order_type, $settings->serviceCharge->appliesTo, true)) {
            $serviceCharge = Decimal::mul($subtotal, Decimal::div($settings->serviceCharge->rate, '100', 6), 2);
        }

        return [
            'subtotal' => $subtotal,
            'packaging_total' => $packaging,
            'tax_amount' => $tax,
            'service_charge' => $serviceCharge,
            'total_amount' => Decimal::add($gross, $serviceCharge, 2),
        ];
    }
}
