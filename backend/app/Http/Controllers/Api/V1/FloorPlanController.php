<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\TableStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\DiningZoneResource;
use App\Http\Resources\OrderResource;
use App\Models\DiningZone;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Support\Decimal;
use Illuminate\Http\JsonResponse;

/**
 * Plano de salones con el estado vivo de cada mesa y los pedidos de mostrador.
 */
final class FloorPlanController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $zones = DiningZone::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['tables' => static fn ($query) => $query->orderBy('grid_y')->orderBy('grid_x')->with('currentOrder.waiter')])
            ->get();

        $counterOrders = Order::query()
            ->with(['waiter', 'items.menuItem'])
            ->whereIn('order_type', [OrderType::Takeout, OrderType::Delivery])
            ->whereIn('status', [OrderStatus::Open, OrderStatus::PreBilled])
            ->orderBy('opened_at')
            ->get();

        $sales = Order::query()
            ->whereIn('status', [OrderStatus::Open, OrderStatus::PreBilled])
            ->toBase()
            ->selectRaw('coalesce(sum(total_amount), 0) as total, coalesce(sum(total_amount - paid_amount), 0) as balance')
            ->first();

        return $this->success([
            'zones' => DiningZoneResource::collection($zones)->resolve(),
            'counter_orders' => OrderResource::collection($counterOrders)->resolve(),
            'summary' => [
                'available' => RestaurantTable::query()->where('status', TableStatus::Available)->count(),
                'occupied' => RestaurantTable::query()->where('status', TableStatus::Occupied)->count(),
                'pre_bill' => RestaurantTable::query()->where('status', TableStatus::PreBill)->count(),
                'open_sales_total' => Decimal::normalize(self::decimal($sales?->total), 2),
                'pending_balance' => Decimal::normalize(self::decimal($sales?->balance), 2),
            ],
        ]);
    }

    private static function decimal(mixed $value): string
    {
        return is_string($value) || is_int($value) ? (string) $value : '0';
    }
}
