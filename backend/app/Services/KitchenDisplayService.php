<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\ProductionArea;
use App\Events\KitchenItemStatusUpdated;
use App\Events\OrderUpdated;
use App\Exceptions\OrderFlowException;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

/**
 * Cola del KDS: platos vivos agrupados en tickets (una ronda de comanda = un ticket),
 * del más antiguo al más reciente (FIFO de cocina).
 */
final class KitchenDisplayService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function tickets(?ProductionArea $area): array
    {
        $items = OrderItem::query()
            ->with(['order.diningTable', 'order.waiter', 'menuItem'])
            ->when($area !== null, static fn ($query) => $query->where('production_area', $area))
            ->whereIn('status', OrderItemStatus::kitchenQueue())
            ->orderBy('sent_to_kitchen_at')
            ->orderBy('id')
            ->get();

        $tickets = [];

        foreach ($items->groupBy(static fn (OrderItem $item): string => $item->order_id.'|'.$item->sent_to_kitchen_at?->toIso8601String()) as $roundItems) {
            $first = $roundItems->firstOrFail();

            $tickets[] = [
                'order' => $this->presentOrder($first->order),
                'sent_at' => $first->sent_to_kitchen_at?->toIso8601String(),
                'items' => $roundItems->map($this->presentItem(...))->values()->all(),
            ];
        }

        return $tickets;
    }

    /**
     * Cabecera del ticket (también viaja en el evento OrderSentToKitchen).
     *
     * @return array<string, string|null>
     */
    public function presentOrder(Order $order): array
    {
        return [
            'id' => $order->uuid,
            'order_number' => $order->order_number,
            'order_type' => $order->order_type->value,
            'table_code' => $order->diningTable?->code,
            'waiter_name' => $order->waiter->name,
            'customer_name' => $order->customer_name,
        ];
    }

    /**
     * Requiere menuItem cargado.
     *
     * @return array<string, mixed>
     */
    public function presentItem(OrderItem $item): array
    {
        return [
            'id' => $item->uuid,
            'name' => $item->menuItem->name,
            'production_area' => $item->production_area->value,
            'quantity' => $item->quantity,
            'modifiers' => $item->modifiers_json,
            'notes' => $item->notes,
            'status' => $item->status->value,
            'sent_to_kitchen_at' => $item->sent_to_kitchen_at?->toIso8601String(),
            'ready_at' => $item->ready_at?->toIso8601String(),
        ];
    }

    /**
     * @param  iterable<OrderItem>  $items
     * @return array<string, list<array<string, mixed>>>
     */
    public function groupByArea(iterable $items): array
    {
        $areas = [];

        foreach ($items as $item) {
            $areas[$item->production_area->value][] = $this->presentItem($item);
        }

        return $areas;
    }

    /**
     * Avanza uno o varios platos (un toque por plato o "toda la comanda").
     * Los platos que ya están en ese estado o más adelante se omiten; si ninguno
     * puede avanzar, responde 409.
     *
     * @param  list<string>  $itemUuids
     * @return list<OrderItem>
     */
    public function advanceItems(array $itemUuids, OrderItemStatus $target): array
    {
        return DB::transaction(function () use ($itemUuids, $target): array {
            $items = OrderItem::query()
                ->with('order.diningTable')
                ->whereIn('uuid', $itemUuids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($items->count() !== count(array_unique($itemUuids))) {
                throw OrderFlowException::itemNotFound();
            }

            $advanced = $items->filter(static fn (OrderItem $item): bool => $item->status->canAdvanceTo($target))->values();

            if ($advanced->isEmpty()) {
                $first = $items->firstOrFail();

                throw OrderFlowException::invalidKitchenTransition($first->status, $target);
            }

            $now = now();

            foreach ($advanced as $item) {
                $item->update([
                    'status' => $target,
                    'ready_at' => $target === OrderItemStatus::Ready ? $now : $item->ready_at,
                ]);
            }

            $company = Company::query()->findOrFail($advanced->firstOrFail()->company_id);

            foreach ($advanced->groupBy(static fn (OrderItem $item): string => $item->order_id.'|'.$item->production_area->value) as $group) {
                $first = $group->firstOrFail();

                KitchenItemStatusUpdated::dispatch(
                    $company->uuid,
                    $first->order->uuid,
                    $first->order->diningTable?->code,
                    $first->production_area->value,
                    array_values($group->map(static fn (OrderItem $item): string => $item->uuid)->all()),
                    $target->value,
                );
            }

            foreach ($advanced->unique('order_id') as $item) {
                OrderUpdated::dispatch($company->uuid, $item->order->uuid, $item->order->status->value, $item->order->total_amount);
            }

            return array_values($advanced->all());
        });
    }
}
