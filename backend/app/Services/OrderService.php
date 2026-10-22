<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Orders\CounterOrderData;
use App\DTOs\Orders\OrderLineData;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Events\KitchenItemStatusUpdated;
use App\Events\OrderSentToKitchen;
use App\Events\OrderUpdated;
use App\Exceptions\OrderFlowException;
use App\Models\Company;
use App\Models\ItemModifier;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Comandas: envío a cocina, pedidos para llevar / delivery, anulaciones y precuenta.
 */
final readonly class OrderService
{
    public function __construct(
        private RecipeKardexService $kardex,
        private OrderTotalsCalculator $totals,
        private OrderNumberGenerator $numbers,
        private TableService $tables,
        private KitchenDisplayService $kitchen,
    ) {}

    /**
     * Pedido de mostrador sin mesa (para llevar o delivery).
     */
    public function createCounterOrder(CounterOrderData $data, User $actor): Order
    {
        return DB::transaction(function () use ($data, $actor): Order {
            $company = Company::query()->findOrFail($actor->company_id);
            $now = CarbonImmutable::now();
            $businessDate = $now->setTimezone($company->settings->timezone)->toDateString();

            $order = Order::query()->create([
                'waiter_id' => $actor->id,
                'business_date' => $businessDate,
                'order_number' => $this->numbers->next($company, $businessDate),
                'order_type' => $data->orderType,
                'diners_count' => 1,
                'customer_name' => $data->customerName,
                'delivery_address' => $data->deliveryAddress,
                'delivery_phone' => $data->deliveryPhone,
                'status' => OrderStatus::Open,
                'opened_at' => $now,
            ]);

            OrderUpdated::dispatch($company->uuid, $order->uuid, $order->status->value, $order->total_amount);

            return $order->load(['diningTable', 'waiter', 'items.menuItem']);
        });
    }

    /**
     * Comanda platos nuevos en una sola transacción: precio y área congelados,
     * escandallo descontado en fracciones con lock del stock, totales e IGV
     * recalculados con BCMath y aviso al KDS tras el commit.
     *
     * Si la mesa ya tenía precuenta, la comanda vuelve a "abierta" (la precuenta
     * impresa deja de ser válida).
     *
     * @param  list<OrderLineData>  $items
     */
    public function addItemsAndSendToKitchen(Order $order, array $items, User $actor): Order
    {
        return DB::transaction(function () use ($order, $items, $actor): Order {
            $table = $this->lockTableOf($order);
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Open && $locked->status !== OrderStatus::PreBilled) {
                throw OrderFlowException::orderClosed($locked);
            }

            $menuItems = $this->resolveMenuItems($items);
            $this->assertKnownModifiers($items);
            $sentAt = CarbonImmutable::now();
            $created = [];

            foreach ($items as $line) {
                $menuItem = $menuItems[$line->menuItemUuid];

                $item = OrderItem::query()->create([
                    'order_id' => $locked->id,
                    'menu_item_id' => $menuItem->id,
                    'production_area' => $menuItem->production_area,
                    'quantity' => $line->quantity,
                    'unit_price' => $menuItem->sale_price,
                    'tax_affectation_type' => $menuItem->tax_affectation_type,
                    'modifiers_json' => $line->modifiers,
                    'notes' => $line->notes,
                    'status' => OrderItemStatus::Pending,
                    'sent_to_kitchen_at' => $sentAt,
                ]);

                $created[] = $item->setRelation('menuItem', $menuItem)->setRelation('order', $locked);
            }

            $this->kardex->consumeForItems($locked, $created, $locked->order_type, $this->kardex->defaultWarehouseId(), $actor);

            if ($locked->status === OrderStatus::PreBilled) {
                $locked->update(['status' => OrderStatus::Open]);
                $table?->update(['status' => TableStatus::Occupied]);
            }

            $company = Company::query()->findOrFail($locked->company_id);
            $this->totals->refresh($locked, $company->settings);
            $locked->setRelation('diningTable', $table);

            OrderSentToKitchen::dispatch(
                $company->uuid,
                $this->kitchen->presentOrder($locked),
                $sentAt->toIso8601String(),
                $this->kitchen->groupByArea($created),
            );
            OrderUpdated::dispatch($company->uuid, $locked->uuid, $locked->status->value, $locked->total_amount);

            if ($table !== null) {
                $this->tables->broadcast($company, $table);
            }

            return $locked->load(['diningTable', 'waiter', 'items.menuItem']);
        });
    }

    /**
     * Anulación de un plato ya comandado (control de mermas y robo). Requiere el
     * permiso orders.void_item (ruta) y un motivo; queda en la auditoría inmutable.
     */
    public function voidItem(Order $order, OrderItem $item, User $authorizer, string $reason): OrderItem
    {
        return DB::transaction(function () use ($order, $item, $authorizer, $reason): OrderItem {
            $table = $this->lockTableOf($order);
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status !== OrderStatus::Open && $lockedOrder->status !== OrderStatus::PreBilled) {
                throw OrderFlowException::orderClosed($lockedOrder);
            }

            $locked = OrderItem::query()->with('menuItem.recipes')->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($locked->order_id !== $lockedOrder->id) {
                throw OrderFlowException::itemNotInOrder();
            }

            if ($locked->status === OrderItemStatus::Voided) {
                throw OrderFlowException::alreadyVoided();
            }

            if (bccomp($locked->paid_quantity, '0', 2) > 0) {
                throw OrderFlowException::alreadyPaid();
            }

            $previousStatus = $locked->status;
            $stockReturned = $this->kardex->reverseIngredientsOnVoid($locked->setRelation('order', $lockedOrder), $reason, $authorizer);

            $locked->update([
                'status' => OrderItemStatus::Voided,
                'voided_by' => $authorizer->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            $company = Company::query()->findOrFail($lockedOrder->company_id);
            $this->totals->refresh($lockedOrder, $company->settings);

            activity('orders')
                ->event('item_voided')
                ->performedOn($locked)
                ->causedBy($authorizer)
                ->withProperties([
                    'order_number' => $lockedOrder->order_number,
                    'menu_item' => $locked->menuItem->name,
                    'quantity' => $locked->quantity,
                    'previous_status' => $previousStatus->value,
                    'reason' => $reason,
                    'stock_returned' => $stockReturned,
                ])
                ->log("Plato anulado en {$lockedOrder->order_number}");

            KitchenItemStatusUpdated::dispatch(
                $company->uuid,
                $lockedOrder->uuid,
                $table?->code,
                $locked->production_area->value,
                [$locked->uuid],
                OrderItemStatus::Voided->value,
            );
            OrderUpdated::dispatch($company->uuid, $lockedOrder->uuid, $lockedOrder->status->value, $lockedOrder->total_amount);

            if ($table !== null) {
                $this->tables->broadcast($company, $table);
            }

            return $locked;
        });
    }

    /**
     * Precuenta: congela la cuenta para el cobro y marca la mesa en "precuenta".
     */
    public function requestPreBill(Order $order, User $actor): Order
    {
        return DB::transaction(function () use ($order, $actor): Order {
            $table = $this->lockTableOf($order);
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Open && $locked->status !== OrderStatus::PreBilled) {
                throw OrderFlowException::orderClosed($locked);
            }

            if (bccomp($locked->total_amount, '0', 2) <= 0) {
                throw OrderFlowException::emptyOrder($locked);
            }

            $locked->update(['status' => OrderStatus::PreBilled]);
            $table?->update(['status' => TableStatus::PreBill]);

            activity('orders')
                ->event('pre_billed')
                ->performedOn($locked)
                ->causedBy($actor)
                ->withProperties(['order_number' => $locked->order_number, 'total_amount' => $locked->total_amount])
                ->log("Precuenta de {$locked->order_number}");

            $company = Company::query()->findOrFail($locked->company_id);
            OrderUpdated::dispatch($company->uuid, $locked->uuid, $locked->status->value, $locked->total_amount);

            if ($table !== null) {
                $this->tables->broadcast($company, $table);
            }

            return $locked->load(['diningTable', 'waiter', 'items.menuItem']);
        });
    }

    /**
     * Bloquea la mesa de la comanda antes que la comanda (orden global de locks).
     */
    private function lockTableOf(Order $order): ?RestaurantTable
    {
        return $order->restaurant_table_id === null
            ? null
            : RestaurantTable::query()->whereKey($order->restaurant_table_id)->lockForUpdate()->firstOrFail();
    }

    /**
     * @param  list<OrderLineData>  $lines
     * @return array<string, MenuItem>
     */
    private function resolveMenuItems(array $lines): array
    {
        $uuids = array_values(array_unique(array_map(static fn (OrderLineData $line): string => $line->menuItemUuid, $lines)));
        $found = MenuItem::query()->with('recipes')->whereIn('uuid', $uuids)->get()->keyBy('uuid');
        $unavailable = [];

        foreach ($uuids as $uuid) {
            $menuItem = $found->get($uuid);

            if ($menuItem === null || ! $menuItem->is_available) {
                $unavailable[] = $menuItem->name ?? $uuid;
            }
        }

        if ($unavailable !== []) {
            throw OrderFlowException::unavailableItems($unavailable);
        }

        return $found->all();
    }

    /**
     * @param  list<OrderLineData>  $lines
     */
    private function assertKnownModifiers(array $lines): void
    {
        $requested = array_values(array_unique(array_merge(...array_map(static fn (OrderLineData $line): array => $line->modifiers, $lines))));

        if ($requested === []) {
            return;
        }

        $known = ItemModifier::query()->where('is_active', true)->whereIn('name', $requested)->get()->map(static fn (ItemModifier $modifier): string => $modifier->name)->all();
        $unknown = array_values(array_diff($requested, $known));

        if ($unknown !== []) {
            throw OrderFlowException::unknownModifiers($unknown);
        }
    }
}
