<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\KardexMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportPeriodRequest;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\KardexMovement;
use App\Models\MenuItem;
use App\Models\Recipe;
use App\Models\User;
use App\Services\ReportService;
use App\Support\Decimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Insumos con stock crítico, escandallo valorizado (salón vs. para llevar) y kardex.
 */
final class InventoryController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function ingredients(): JsonResponse
    {
        $ingredients = Ingredient::query()->with('stocks')->where('is_active', true)->orderBy('name')->get();

        $rows = $ingredients->map(static function (Ingredient $ingredient): array {
            $stock = '0.0000';
            $value = '0.0000';

            foreach ($ingredient->stocks as $row) {
                /** @var IngredientStock $row */
                $stock = Decimal::add($stock, $row->stock, 4);
                $value = Decimal::add($value, Decimal::mul($row->stock, $row->average_cost, 4), 4);
            }

            $averageCost = bccomp($stock, '0', 4) > 0 ? Decimal::div($value, $stock, 4) : $ingredient->unit_cost;

            return [
                'id' => $ingredient->uuid,
                'sku' => $ingredient->sku,
                'name' => $ingredient->name,
                'unit_code' => $ingredient->unit_code->value,
                'unit_symbol' => $ingredient->unit_code->symbol(),
                'stock' => $stock,
                'min_stock' => $ingredient->min_stock,
                'average_cost' => $averageCost,
                'stock_value' => Decimal::round($value, 2),
                'is_critical' => bccomp($stock, $ingredient->min_stock, 4) <= 0,
            ];
        })->all();

        return $this->success($rows, meta: [
            'critical_count' => count(array_filter($rows, static fn (array $row): bool => $row['is_critical'])),
            'inventory_value' => array_reduce($rows, static fn (string $sum, array $row): string => Decimal::add($sum, $row['stock_value'], 2), '0.00'),
        ]);
    }

    /**
     * Escandallo de cada plato: fracción de insumo que descuenta en salón y en
     * para llevar / delivery (envases), valorizada al costo promedio vigente.
     */
    public function recipes(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tax = Company::query()->findOrFail($user->company_id)->settings->tax;
        $factor = Decimal::add('1', Decimal::div($tax->igvRate, '100', 6), 6);
        $costs = $this->reports->averageCosts();

        $items = MenuItem::query()
            ->with(['category', 'recipes' => static fn ($query) => $query->with('ingredient')->orderBy('apply_only_on_takeout')->orderBy('id')])
            ->orderBy('menu_category_id')
            ->orderBy('name')
            ->get();

        $rows = $items->map(static function (MenuItem $item) use ($tax, $factor, $costs): array {
            $dineInCost = '0.0000';
            $takeoutCost = '0.0000';
            $lines = [];

            foreach ($item->recipes as $line) {
                /** @var Recipe $line */
                $unitCost = $costs[$line->ingredient_id] ?? $line->ingredient->unit_cost;
                $lineCost = Decimal::mul($line->quantity_required, $unitCost, 4);
                $takeoutCost = Decimal::add($takeoutCost, $lineCost, 4);

                if (! $line->apply_only_on_takeout) {
                    $dineInCost = Decimal::add($dineInCost, $lineCost, 4);
                }

                $lines[] = [
                    'ingredient' => ['id' => $line->ingredient->uuid, 'sku' => $line->ingredient->sku, 'name' => $line->ingredient->name, 'unit_code' => $line->ingredient->unit_code->value],
                    'quantity_required' => $line->quantity_required,
                    'apply_only_on_takeout' => $line->apply_only_on_takeout,
                    'dine_in_quantity' => $line->apply_only_on_takeout ? '0.0000' : $line->quantity_required,
                    'takeout_quantity' => $line->quantity_required,
                    'unit_cost' => $unitCost,
                    'line_cost' => $lineCost,
                ];
            }

            $netPrice = $tax->pricesIncludeTax && $item->tax_affectation_type->isTaxed()
                ? Decimal::div($item->sale_price, $factor, 4)
                : $item->sale_price;

            return [
                'id' => $item->uuid,
                'sku' => $item->sku,
                'name' => $item->name,
                'category' => $item->category->name,
                'production_area' => $item->production_area->value,
                'sale_price' => $item->sale_price,
                'net_price' => Decimal::round($netPrice, 2),
                'tax_affectation_type' => $item->tax_affectation_type->value,
                'packaging_surcharge' => $item->packaging_surcharge,
                'dine_in_cost' => $dineInCost,
                'takeout_cost' => $takeoutCost,
                'food_cost_percentage' => bccomp($netPrice, '0', 4) > 0 ? Decimal::mul(Decimal::div($dineInCost, $netPrice, 10), '100', 2) : null,
                'lines' => $lines,
            ];
        })->all();

        return $this->success($rows);
    }

    public function kardex(ReportPeriodRequest $request): JsonResponse
    {
        $request->validate(['movement_type' => ['nullable', Rule::enum(KardexMovementType::class)]]);

        /** @var User $user */
        $user = $request->user();
        $period = $request->period(Company::query()->findOrFail($user->company_id));
        $ingredient = $request->filled('ingredient')
            ? Ingredient::query()->where('uuid', $request->string('ingredient')->toString())->firstOrFail()
            : null;

        $movements = KardexMovement::query()
            ->with(['ingredient', 'creator'])
            ->when($ingredient !== null, static fn ($query) => $query->where('ingredient_id', $ingredient?->id))
            ->when($request->filled('movement_type'), static fn ($query) => $query->where('movement_type', $request->string('movement_type')->toString()))
            ->where('occurred_at', '>=', $period->start)
            ->where('occurred_at', '<', $period->end)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $rows = array_map(static fn (KardexMovement $movement): array => [
            'id' => $movement->uuid,
            'occurred_at' => $movement->occurred_at->toIso8601String(),
            'ingredient' => ['id' => $movement->ingredient->uuid, 'sku' => $movement->ingredient->sku, 'name' => $movement->ingredient->name, 'unit_code' => $movement->ingredient->unit_code->value],
            'movement_type' => $movement->movement_type->value,
            'sunat_operation_type' => $movement->sunat_operation_type,
            'document_reference' => $movement->document_reference,
            'quantity_in' => $movement->quantity_in,
            'quantity_out' => $movement->quantity_out,
            'unit_cost' => $movement->unit_cost,
            'total_cost' => $movement->total_cost,
            'balance_quantity' => $movement->balance_quantity,
            'balance_unit_cost' => $movement->balance_unit_cost,
            'balance_total_cost' => $movement->balance_total_cost,
            'notes' => $movement->notes,
            'created_by' => $movement->creator?->name,
        ], $movements->items());

        return $this->success($rows, meta: [
            'period' => $period->toArray(),
            'pagination' => [
                'current_page' => $movements->currentPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
                'last_page' => $movements->lastPage(),
            ],
        ]);
    }
}
