<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Billing\ChargeBreakdown;
use App\DTOs\Billing\ChargeLineData;
use App\DTOs\Billing\ChargeRequestData;
use App\Enums\ChargeMode;
use App\Enums\ConsumptionFormat;
use App\Enums\DocumentType;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Events\OrderUpdated;
use App\Exceptions\BillingException;
use App\Exceptions\OrderFlowException;
use App\Models\CashRegisterSession;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cobro de la cuenta completa o dividida (Split Bill).
 *
 *  - FULL: todo el saldo pendiente.
 *  - ITEMS: cada comensal paga sus platos y cantidades (actualiza paid_quantity).
 *  - AMOUNT: partes iguales o monto libre; solo admite comprobante "Por consumo".
 *
 * Cada cobro genera su comprobante con pagos mixtos que suman el total exacto y
 * respeta la afectación de cada plato (gravado, exonerado o inafecto).
 * El recargo al consumo se prorratea y el cobro que liquida la cuenta toma el
 * saldo exacto: la mesa solo se libera cuando el pendiente llega a 0.00.
 */
final readonly class SplitBillService
{
    public function __construct(
        private SunatService $sunat,
        private TableService $tables,
        private CashSessionService $cashSessions,
    ) {}

    public function charge(Order $order, ChargeRequestData $request, User $cashier): Sale
    {
        $sale = DB::transaction(function () use ($order, $request, $cashier): Sale {
            $session = $this->cashSessions->currentFor($cashier) ?? throw BillingException::noOpenCashSession();
            $table = $order->restaurant_table_id === null
                ? null
                : RestaurantTable::query()->whereKey($order->restaurant_table_id)->lockForUpdate()->firstOrFail();
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Open && $locked->status !== OrderStatus::PreBilled) {
                throw BillingException::orderNotChargeable($locked);
            }

            $balance = $this->balance($locked);

            if (bccomp($balance, '0', 2) <= 0) {
                throw BillingException::nothingToCharge();
            }

            $items = OrderItem::query()
                ->with('menuItem')
                ->where('order_id', $locked->id)
                ->where('status', '!=', OrderItemStatus::Voided)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $breakdown = $this->breakdown($locked, $items, $request->mode, $request->format, $request->lines, $request->amount, $balance);
            $this->assertPayments($request, $breakdown->total);

            $sale = $this->sunat->issue($locked, $request, $breakdown, $session, $cashier);

            foreach ($request->payments as $payment) {
                SalePayment::query()->create([
                    'sale_id' => $sale->id,
                    'method' => $payment->method,
                    'amount' => $payment->amount,
                    'received_amount' => $payment->receivedAmount,
                    'change_amount' => $payment->change(),
                    'reference' => $payment->reference,
                ]);
            }

            $this->applyToOrder($locked, $items, $breakdown, $session);

            $company = Company::query()->findOrFail($locked->company_id);

            if ($breakdown->settlesOrder && $table !== null) {
                $this->tables->release($table);
            }

            if ($table !== null) {
                $this->tables->broadcast($company, $table);
            }

            activity('cashier')
                ->event('charged')
                ->performedOn($sale)
                ->causedBy($cashier)
                ->withProperties([
                    'order_number' => $locked->order_number,
                    'document' => $sale->documentNumber(),
                    'charge_mode' => $breakdown->mode->value,
                    'total_amount' => $breakdown->total,
                    'balance_after' => $this->balance($locked),
                    'payments' => array_map(static fn ($payment): array => ['method' => $payment->method->value, 'amount' => $payment->amount], $request->payments),
                ])
                ->log("Cobro {$sale->documentNumber()} de {$locked->order_number}");

            OrderUpdated::dispatch($company->uuid, $locked->uuid, $locked->status->value, $locked->total_amount);

            return $sale;
        });

        $this->sunat->queueDelivery($sale);

        return $sale->load(['items', 'payments', 'order.diningTable']);
    }

    /**
     * Cotización de un cobro sin registrarlo: caja muestra el total exacto (con el
     * prorrateo del recargo al consumo) antes de capturar los pagos.
     *
     * @param  list<ChargeLineData>  $lines
     * @param  numeric-string|null  $amount
     */
    public function quote(Order $order, ChargeMode $mode, ConsumptionFormat $format, array $lines, ?string $amount): ChargeBreakdown
    {
        if ($order->status !== OrderStatus::Open && $order->status !== OrderStatus::PreBilled) {
            throw BillingException::orderNotChargeable($order);
        }

        $balance = $this->balance($order);

        if (bccomp($balance, '0', 2) <= 0) {
            throw BillingException::nothingToCharge();
        }

        $items = OrderItem::query()
            ->with('menuItem')
            ->where('order_id', $order->id)
            ->where('status', '!=', OrderItemStatus::Voided)
            ->orderBy('id')
            ->get();

        return $this->breakdown($order, $items, $mode, $format, $lines, $amount, $balance);
    }

    /**
     * Saldo pendiente de la comanda (total - pagado).
     *
     * @return numeric-string
     */
    public function balance(Order $order): string
    {
        return Decimal::sub($order->total_amount, $order->paid_amount, 2);
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     * @param  list<ChargeLineData>  $requestedLines
     * @param  numeric-string|null  $amount
     * @param  numeric-string  $balance
     */
    private function breakdown(Order $order, Collection $items, ChargeMode $mode, ConsumptionFormat $format, array $requestedLines, ?string $amount, string $balance): ChargeBreakdown
    {
        $hasAmountSplits = $order->sales()->where('charge_mode', ChargeMode::Amount)->exists();
        $remainingService = Decimal::sub($order->service_charge, (string) $order->sales()->where('document_type', '!=', DocumentType::CreditNote)->sum('service_charge'), 2);

        if ($mode === ChargeMode::Amount) {
            return $this->amountBreakdown($order, $amount, $balance, $remainingService);
        }

        if ($hasAmountSplits) {
            // Tras dividir por montos los platos ya no cuadran con el saldo: solo se liquida por consumo.
            if ($mode === ChargeMode::Items || $format === ConsumptionFormat::Detailed) {
                throw BillingException::itemsAfterAmountSplit();
            }

            return new ChargeBreakdown(ChargeMode::Full, [], Decimal::sub($balance, $remainingService, 2), $remainingService, $balance, true);
        }

        // El saldo completo omite los platos ya pagados en un cobro anterior por platos.
        $selected = $mode === ChargeMode::Full
            ? $items->filter(static fn (OrderItem $item): bool => bccomp($item->unpaidQuantity(), '0', 2) > 0)
                ->map(static fn (OrderItem $item): ChargeLineData => new ChargeLineData($item->uuid, $item->unpaidQuantity()))
                ->values()
                ->all()
            : $requestedLines;

        $lines = $this->resolveLines($order, $items, $selected, $this->grossFactor($order));

        if ($lines === []) {
            throw BillingException::nothingToCharge();
        }

        $gross = array_reduce($lines, static fn (string $sum, array $line): string => Decimal::add($sum, Decimal::add($line['gross'], $line['packaging'], 2), 2), '0.00');
        $settles = $this->coversAllItems($items, $lines);

        if ($settles) {
            return new ChargeBreakdown($mode, $lines, Decimal::sub($balance, $remainingService, 2), $remainingService, $balance, true);
        }

        $service = $this->serviceShare($order, $gross, Decimal::add($order->subtotal, $order->packaging_total, 2), $remainingService);

        return new ChargeBreakdown($mode, $lines, $gross, $service, Decimal::add($gross, $service, 2), false);
    }

    /**
     * @param  numeric-string|null  $requested
     * @param  numeric-string  $balance
     * @param  numeric-string  $remainingService
     */
    private function amountBreakdown(Order $order, ?string $requested, string $balance, string $remainingService): ChargeBreakdown
    {
        $amount = $requested ?? throw BillingException::nothingToCharge();

        if (bccomp($amount, $balance, 2) > 0) {
            throw BillingException::amountExceedsBalance($balance);
        }

        if (bccomp($amount, $balance, 2) === 0) {
            return new ChargeBreakdown(ChargeMode::Amount, [], Decimal::sub($amount, $remainingService, 2), $remainingService, $amount, true);
        }

        $service = $this->serviceShare($order, $amount, $order->total_amount, $remainingService);

        return new ChargeBreakdown(ChargeMode::Amount, [], Decimal::sub($amount, $service, 2), $service, $amount, false);
    }

    /**
     * Platos solicitados contra lo pendiente de cada uno (cantidades exactas a 2 decimales).
     *
     * @param  Collection<int, OrderItem>  $items
     * @param  array<int, ChargeLineData>  $selected
     * @param  numeric-string|null  $grossFactor  1 + tasa cuando la carta tiene precios sin IGV
     * @return list<array{item: OrderItem, quantity: numeric-string, gross: numeric-string, packaging: numeric-string}>
     */
    private function resolveLines(Order $order, Collection $items, array $selected, ?string $grossFactor): array
    {
        $byUuid = $items->keyBy('uuid');
        $requested = [];

        foreach ($selected as $line) {
            $requested[$line->orderItemUuid] = Decimal::add($requested[$line->orderItemUuid] ?? '0', $line->quantity, 2);
        }

        $lines = [];

        foreach ($requested as $uuid => $quantity) {
            $item = $byUuid->get($uuid) ?? throw OrderFlowException::itemNotInOrder();

            if (bccomp($quantity, '0', 2) <= 0) {
                continue;
            }

            if (bccomp($quantity, $item->unpaidQuantity(), 2) > 0) {
                throw BillingException::quantityExceedsUnpaid($item->menuItem->name, $item->unpaidQuantity());
            }

            $packaging = $item->usesPackaging($order->order_type) ? $item->menuItem->packaging_surcharge : '0';

            // Exonerados e inafectos no suman IGV aunque la carta tenga precios sin impuesto; el empaque es gravado.
            $lines[] = [
                'item' => $item,
                'quantity' => $quantity,
                'gross' => $this->gross($item->unit_price, $quantity, $item->tax_affectation_type->isTaxed() ? $grossFactor : null),
                'packaging' => $this->gross($packaging, $quantity, $grossFactor),
            ];
        }

        return $lines;
    }

    /**
     * @return numeric-string|null
     */
    private function grossFactor(Order $order): ?string
    {
        $tax = Company::query()->findOrFail($order->company_id)->settings->tax;

        return $tax->pricesIncludeTax ? null : Decimal::add('1', Decimal::div($tax->igvRate, '100', 6), 6);
    }

    /**
     * Importe final (IGV incluido) de una cantidad a precio de carta.
     *
     * @param  numeric-string  $unitPrice
     * @param  numeric-string  $quantity
     * @param  numeric-string|null  $grossFactor
     * @return numeric-string
     */
    private function gross(string $unitPrice, string $quantity, ?string $grossFactor): string
    {
        return $grossFactor === null
            ? Decimal::mul($unitPrice, $quantity, 2)
            : Decimal::mul(Decimal::mul($unitPrice, $quantity, 10), $grossFactor, 2);
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     * @param  list<array{item: OrderItem, quantity: numeric-string, gross: numeric-string, packaging: numeric-string}>  $lines
     */
    private function coversAllItems(Collection $items, array $lines): bool
    {
        $charged = [];

        foreach ($lines as $line) {
            $charged[$line['item']->id] = $line['quantity'];
        }

        return $items->every(static fn (OrderItem $item): bool => bccomp($item->unpaidQuantity(), $charged[$item->id] ?? '0', 2) === 0);
    }

    /**
     * Recargo al consumo proporcional a lo cobrado, sin exceder lo que queda por prorratear.
     *
     * @param  numeric-string  $charged
     * @param  numeric-string  $base
     * @param  numeric-string  $remainingService
     * @return numeric-string
     */
    private function serviceShare(Order $order, string $charged, string $base, string $remainingService): string
    {
        if (bccomp($order->service_charge, '0', 2) <= 0 || bccomp($base, '0', 2) <= 0) {
            return '0.00';
        }

        $share = Decimal::mul($order->service_charge, Decimal::div($charged, $base, 10), 2);

        return bccomp($share, $remainingService, 2) > 0 ? $remainingService : $share;
    }

    /**
     * @param  numeric-string  $total
     */
    private function assertPayments(ChargeRequestData $request, string $total): void
    {
        $paid = $request->paymentsTotal();

        if (bccomp($paid, $total, 2) !== 0) {
            throw BillingException::paymentsMismatch($total, $paid);
        }
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     */
    private function applyToOrder(Order $order, Collection $items, ChargeBreakdown $breakdown, CashRegisterSession $session): void
    {
        if ($breakdown->settlesOrder) {
            foreach ($items as $item) {
                $item->update(['paid_quantity' => $item->quantity]);
            }
        } else {
            foreach ($breakdown->lines as $line) {
                $line['item']->update(['paid_quantity' => Decimal::add($line['item']->paid_quantity, $line['quantity'], 2)]);
            }
        }

        $paidAmount = Decimal::add($order->paid_amount, $breakdown->total, 2);
        $settled = bccomp($paidAmount, $order->total_amount, 2) === 0;

        $order->update([
            'paid_amount' => $paidAmount,
            'cash_register_session_id' => $session->id,
            'status' => $settled ? OrderStatus::Paid : $order->status,
            'closed_at' => $settled ? now() : null,
        ]);
    }
}
