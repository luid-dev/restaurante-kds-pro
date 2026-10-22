<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\TableStatus;
use App\Events\OrderUpdated;
use App\Events\TableStatusUpdated;
use App\Exceptions\OrderFlowException;
use App\Http\Resources\RestaurantTableResource;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vida de la mesa: apertura, cambio de mesa, unión de cuentas y liberación.
 *
 * Orden de bloqueo en todo el sistema: mesas (por id ascendente) y luego comandas.
 * Así dos mozos que operan las mismas mesas nunca se bloquean mutuamente.
 */
final readonly class TableService
{
    private const int MAX_DINERS = 99;

    public function __construct(
        private OrderNumberGenerator $numbers,
        private OrderTotalsCalculator $totals,
    ) {}

    /**
     * Abre la mesa con su comanda. Si dos mozos la abren a la vez, el segundo recibe un 409.
     */
    public function openTableAndCreateOrder(RestaurantTable $table, User $waiter, int $dinersCount): Order
    {
        return DB::transaction(function () use ($table, $waiter, $dinersCount): Order {
            $locked = RestaurantTable::query()->whereKey($table->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== TableStatus::Available && $locked->status !== TableStatus::Reserved) {
                throw OrderFlowException::tableNotAvailable($locked);
            }

            $company = Company::query()->findOrFail($locked->company_id);
            $now = CarbonImmutable::now();
            $businessDate = $now->setTimezone($company->settings->timezone)->toDateString();

            $order = Order::query()->create([
                'restaurant_table_id' => $locked->id,
                'waiter_id' => $waiter->id,
                'business_date' => $businessDate,
                'order_number' => $this->numbers->next($company, $businessDate),
                'order_type' => OrderType::DineIn,
                'diners_count' => $dinersCount,
                'status' => OrderStatus::Open,
                'opened_at' => $now,
            ]);

            $locked->update([
                'status' => TableStatus::Occupied,
                'current_order_id' => $order->id,
                'opened_at' => $now,
            ]);

            $this->broadcast($company, $locked);

            return $order->load(['diningTable', 'waiter', 'items.menuItem']);
        });
    }

    /**
     * Cambio de mesa (destino libre) o unión de cuentas (destino ocupado).
     *
     * Al unir, los platos de la mesa origen pasan a la comanda destino, la comanda
     * origen queda anulada por fusión y la mesa origen se libera. No se unen cuentas
     * con pagos parciales: el cobro ya emitido referencia a su comanda original.
     */
    public function transferOrMergeTables(RestaurantTable $source, RestaurantTable $target, User $actor): Order
    {
        if ($source->id === $target->id) {
            throw OrderFlowException::sameTable();
        }

        return DB::transaction(function () use ($source, $target, $actor): Order {
            $tables = RestaurantTable::query()
                ->whereKey([$source->id, $target->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lockedSource = $tables->get($source->id) ?? throw OrderFlowException::tableNotFound();
            $lockedTarget = $tables->get($target->id) ?? throw OrderFlowException::tableNotFound();

            if ($lockedSource->current_order_id === null) {
                throw OrderFlowException::tableWithoutOrder($lockedSource);
            }

            $company = Company::query()->findOrFail($lockedSource->company_id);

            $order = $lockedTarget->current_order_id === null
                ? $this->transfer($lockedSource, $lockedTarget, $actor)
                : $this->merge($lockedSource, $lockedTarget, $company, $actor);

            $this->broadcast($company, $lockedSource);
            $this->broadcast($company, $lockedTarget);
            OrderUpdated::dispatch($company->uuid, $order->uuid, $order->status->value, $order->total_amount);

            return $order->load(['diningTable', 'waiter', 'items.menuItem']);
        });
    }

    /**
     * Libera la mesa tras el cobro total. Debe llamarse con la mesa bloqueada.
     */
    public function release(RestaurantTable $table): void
    {
        $table->update([
            'status' => TableStatus::Available,
            'current_order_id' => null,
            'opened_at' => null,
        ]);
    }

    /**
     * Notifica el nuevo estado de la mesa a todas las pantallas (tras el commit).
     */
    public function broadcast(Company $company, RestaurantTable $table): void
    {
        $table->load('currentOrder.waiter');

        TableStatusUpdated::dispatch($company->uuid, RestaurantTableResource::make($table)->resolve());
    }

    private function transfer(RestaurantTable $source, RestaurantTable $target, User $actor): Order
    {
        if ($target->status !== TableStatus::Available && $target->status !== TableStatus::Reserved) {
            throw OrderFlowException::tableNotAvailable($target);
        }

        $order = Order::query()->whereKey($source->current_order_id)->lockForUpdate()->firstOrFail();
        $order->update(['restaurant_table_id' => $target->id]);

        $target->update([
            'status' => $source->status,
            'current_order_id' => $order->id,
            'opened_at' => $source->opened_at,
        ]);
        $this->release($source);

        activity('tables')
            ->event('transferred')
            ->performedOn($order)
            ->causedBy($actor)
            ->withProperties(['order_number' => $order->order_number, 'from' => $source->code, 'to' => $target->code])
            ->log("Comanda {$order->order_number} pasó de {$source->code} a {$target->code}");

        return $order;
    }

    private function merge(RestaurantTable $source, RestaurantTable $target, Company $company, User $actor): Order
    {
        $orders = Order::query()
            ->whereKey([$source->current_order_id, $target->current_order_id])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $sourceOrder = $orders->get($source->current_order_id) ?? throw OrderFlowException::tableWithoutOrder($source);
        $targetOrder = $orders->get($target->current_order_id) ?? throw OrderFlowException::tableWithoutOrder($target);

        foreach ([$sourceOrder, $targetOrder] as $order) {
            if (bccomp($order->paid_amount, '0', 2) > 0) {
                throw OrderFlowException::mergeWithPayments($order);
            }
        }

        $moved = OrderItem::query()->where('order_id', $sourceOrder->id)->update(['order_id' => $targetOrder->id]);

        // La precuenta impresa del destino ya no refleja la cuenta unida: vuelve a abierta.
        $targetOrder->update([
            'status' => OrderStatus::Open,
            'diners_count' => min(self::MAX_DINERS, $targetOrder->diners_count + $sourceOrder->diners_count),
        ]);
        $target->update(['status' => TableStatus::Occupied]);

        $this->totals->refresh($sourceOrder, $company->settings);
        $sourceOrder->update(['status' => OrderStatus::Cancelled, 'closed_at' => now()]);
        $this->totals->refresh($targetOrder, $company->settings);
        $this->release($source);

        activity('tables')
            ->event('merged')
            ->performedOn($targetOrder)
            ->causedBy($actor)
            ->withProperties([
                'source_order' => $sourceOrder->order_number,
                'target_order' => $targetOrder->order_number,
                'from' => $source->code,
                'to' => $target->code,
                'items_moved' => $moved,
            ])
            ->log("Cuenta de {$source->code} unida a {$target->code}");

        OrderUpdated::dispatch($company->uuid, $sourceOrder->uuid, $sourceOrder->status->value, $sourceOrder->total_amount);

        return $targetOrder;
    }
}
