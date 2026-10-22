<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RestaurantTable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mesa del plano con el resumen de su comanda activa.
 *
 * @mixin RestaurantTable
 */
final class RestaurantTableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->relationLoaded('currentOrder') ? $this->currentOrder : null;

        return [
            'id' => $this->uuid,
            'code' => $this->code,
            'capacity' => $this->capacity,
            'grid_x' => $this->grid_x,
            'grid_y' => $this->grid_y,
            'status' => $this->status->value,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'order' => $order === null ? null : [
                'id' => $order->uuid,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'diners_count' => $order->diners_count,
                'total_amount' => $order->total_amount,
                'waiter_name' => $order->relationLoaded('waiter') ? $order->waiter->name : null,
            ],
        ];
    }
}
