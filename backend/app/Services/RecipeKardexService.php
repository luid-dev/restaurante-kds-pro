<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\KardexMovementType;
use App\Enums\OrderItemStatus;
use App\Enums\OrderType;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\KardexMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Descarga del escandallo en fracciones y kardex valorizado (costo promedio ponderado).
 *
 * Cada movimiento guarda su origen: la comanda (consumo de venta), el plato
 * anulado (reverso o merma) o la nota de crédito (merma por devolución).
 *
 * Debe ejecutarse dentro de la transacción de la comanda: bloquea cada fila de
 * ingredient_warehouse con SELECT ... FOR UPDATE, siempre en orden ascendente de
 * insumo para que dos comandas simultáneas no se bloqueen mutuamente (deadlock).
 */
final class RecipeKardexService
{
    /**
     * Descuenta la receta de un plato: 2 x "1/4 Pollo" = 2 x 0.2500 = 0.5000 de pollo entero.
     * Requiere item.menuItem.recipes y item.order cargados (o cargables).
     */
    public function consumeIngredientsForOrderItem(OrderItem $item, OrderType $orderType, int $warehouseId, User $actor): void
    {
        $this->consumeForItems($item->order, [$item], $orderType, $warehouseId, $actor);
    }

    /**
     * Descarga en lote de una comanda: suma por insumo y bloquea cada fila una sola vez.
     *
     * @param  iterable<OrderItem>  $items
     */
    public function consumeForItems(Order $order, iterable $items, OrderType $orderType, int $warehouseId, User $actor): void
    {
        $requirements = $this->requirements($orderType, $items);

        $this->move($order, $order->order_number, $requirements, KardexMovementType::SaleConsumption, $warehouseId, $actor, null);
    }

    /**
     * Anulación de un plato (llamar ANTES de cambiar su estado a VOIDED):
     *  - En cola (PENDING): cocina no lo tocó, los insumos vuelven al almacén.
     *  - En preparación o listo: el insumo se perdió. El consumo de venta se revierte
     *    y se registra como merma (tipo SUNAT 13): el stock no cambia, pero el costo
     *    pasa de "costo de ventas" a "merma operativa" y queda en la auditoría.
     *
     * @return bool true si el insumo volvió al almacén, false si se registró como merma.
     */
    public function reverseIngredientsOnVoid(OrderItem $item, string $reason, User $authorizer): bool
    {
        $order = $item->order;
        $warehouseId = $this->defaultWarehouseId();
        $requirements = $this->requirements($order->order_type, [$item]);
        $reference = "Anulación {$order->order_number}: {$reason}";

        if ($item->status === OrderItemStatus::Pending) {
            $this->move($item, $order->order_number, $requirements, KardexMovementType::AdjustmentIn, $warehouseId, $authorizer, $reference);

            return true;
        }

        $this->reclassifyAsWaste($item, $order->order_number, $requirements, $warehouseId, $authorizer, $reference);

        activity('inventory')
            ->event('operational_waste')
            ->performedOn($item)
            ->causedBy($authorizer)
            ->withProperties([
                'order_number' => $order->order_number,
                'menu_item' => $item->menuItem->name,
                'quantity' => $item->quantity,
                'kitchen_status' => $item->status->value,
                'reason' => $reason,
                'ingredients' => $requirements,
            ])
            ->log("Merma operativa por anulación en {$order->order_number}");

        return false;
    }

    /**
     * Nota de crédito por anulación o devolución: lo ya cocinado no vuelve al almacén.
     * El consumo de venta se revierte y se registra como merma (tipo SUNAT 13) con
     * la nota como origen; el stock no cambia.
     *
     * @param  array<int, numeric-string>  $requirements
     */
    public function reclassifyCreditedConsumption(Sale $creditNote, array $requirements, User $actor, string $reason): void
    {
        $this->reclassifyAsWaste($creditNote, $creditNote->documentNumber(), $requirements, $this->defaultWarehouseId(), $actor, "Nota de crédito {$creditNote->documentNumber()}: {$reason}");
    }

    /**
     * Cantidad total por insumo (escala 4), ordenada por id de insumo. Los envases y
     * bolsas solo se descuentan en para llevar / delivery o con la indicación "Para llevar".
     *
     * @param  iterable<OrderItem>  $items
     * @return array<int, numeric-string>
     */
    public function requirements(OrderType $orderType, iterable $items): array
    {
        $portions = [];

        foreach ($items as $item) {
            $portions[] = ['item' => $item, 'quantity' => $item->quantity];
        }

        return $this->requirementsFor($orderType, $portions);
    }

    /**
     * Igual que requirements(), pero con la cantidad de cada plato indicada (p. ej. la
     * parte cobrada en un comprobante de una cuenta dividida).
     *
     * @param  iterable<array{item: OrderItem, quantity: numeric-string}>  $portions
     * @return array<int, numeric-string>
     */
    public function requirementsFor(OrderType $orderType, iterable $portions): array
    {
        $required = [];

        foreach ($portions as ['item' => $item, 'quantity' => $portion]) {
            $packaged = $item->usesPackaging($orderType);

            foreach ($item->menuItem->recipes as $line) {
                if ($line->apply_only_on_takeout && ! $packaged) {
                    continue;
                }

                $quantity = Decimal::mul($portion, $line->quantity_required, 4);
                $required[$line->ingredient_id] = Decimal::add($required[$line->ingredient_id] ?? '0', $quantity, 4);
            }
        }

        ksort($required);

        return array_filter($required, static fn (string $quantity): bool => bccomp($quantity, '0', 4) > 0);
    }

    public function defaultWarehouseId(): int
    {
        return Warehouse::query()->where('is_default', true)->where('is_active', true)->firstOrFail()->id;
    }

    /**
     * Reverso del consumo (entrada) seguido de la merma (salida) al mismo costo promedio.
     *
     * @param  array<int, numeric-string>  $requirements
     */
    private function reclassifyAsWaste(Model $source, string $reference, array $requirements, int $warehouseId, User $actor, string $reason): void
    {
        $this->move($source, $reference, $requirements, KardexMovementType::AdjustmentIn, $warehouseId, $actor, "Reverso de consumo por merma - {$reason}");
        $this->move($source, $reference, $requirements, KardexMovementType::Waste, $warehouseId, $actor, "Merma operativa - {$reason}");
    }

    /**
     * @param  array<int, numeric-string>  $requirements
     */
    private function move(Model $source, string $reference, array $requirements, KardexMovementType $type, int $warehouseId, User $actor, ?string $notes): void
    {
        $occurredAt = CarbonImmutable::now();

        foreach ($requirements as $ingredientId => $quantity) {
            if (bccomp($quantity, '0', 4) <= 0) {
                continue;
            }

            $stock = IngredientStock::query()
                ->where('ingredient_id', $ingredientId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first() ?? $this->openStock($ingredientId, $warehouseId);

            // Las salidas se valorizan al costo promedio vigente; las entradas lo recalculan.
            $unitCost = $stock->average_cost;

            if ($type->isInbound()) {
                $balance = Decimal::add($stock->stock, $quantity, 4);
                $averageCost = $this->weightedAverage($stock->stock, $stock->average_cost, $quantity, $unitCost);
            } else {
                $balance = Decimal::sub($stock->stock, $quantity, 4);
                $averageCost = $stock->average_cost;
            }

            $stock->update(['stock' => $balance, 'average_cost' => $averageCost]);

            KardexMovement::query()->create([
                'warehouse_id' => $warehouseId,
                'ingredient_id' => $ingredientId,
                'movement_type' => $type,
                'sunat_operation_type' => $type->sunatOperationCode(),
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
                'document_reference' => $reference,
                'quantity_in' => $type->isInbound() ? $quantity : '0',
                'quantity_out' => $type->isInbound() ? '0' : $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => Decimal::mul($quantity, $unitCost, 4),
                'balance_quantity' => $balance,
                'balance_unit_cost' => $averageCost,
                'balance_total_cost' => Decimal::mul($balance, $averageCost, 4),
                'notes' => $notes === null ? null : mb_substr($notes, 0, 255),
                'created_by' => $actor->id,
                'occurred_at' => $occurredAt,
            ]);
        }
    }

    /**
     * Costo promedio ponderado: (saldo x costo + entrada x costo entrada) / (saldo + entrada).
     * Con saldo negativo o nulo (venta sin stock registrado) se adopta el costo de la entrada.
     *
     * @param  numeric-string  $stock
     * @param  numeric-string  $averageCost
     * @param  numeric-string  $quantity
     * @param  numeric-string  $unitCost
     * @return numeric-string
     */
    private function weightedAverage(string $stock, string $averageCost, string $quantity, string $unitCost): string
    {
        if (bccomp($stock, '0', 4) <= 0) {
            return Decimal::round($unitCost, 4);
        }

        $value = Decimal::add(Decimal::mul($stock, $averageCost, 10), Decimal::mul($quantity, $unitCost, 10), 10);

        return Decimal::div($value, Decimal::add($stock, $quantity, 4), 4);
    }

    /**
     * Insumo sin fila de stock en el almacén: se abre en cero con su costo de referencia.
     */
    private function openStock(int $ingredientId, int $warehouseId): IngredientStock
    {
        $ingredient = Ingredient::query()->findOrFail($ingredientId);

        return IngredientStock::query()->create([
            'ingredient_id' => $ingredientId,
            'warehouse_id' => $warehouseId,
            'stock' => '0',
            'average_cost' => $ingredient->unit_cost,
        ]);
    }
}
