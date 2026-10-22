<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderItem;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
final class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'menu_item' => $this->whenLoaded('menuItem', fn (): array => [
                'id' => $this->menuItem->uuid,
                'sku' => $this->menuItem->sku,
                'name' => $this->menuItem->name,
                'packaging_surcharge' => $this->menuItem->packaging_surcharge,
            ]),
            'production_area' => $this->production_area->value,
            'quantity' => $this->quantity,
            'paid_quantity' => $this->paid_quantity,
            'unpaid_quantity' => $this->unpaidQuantity(),
            'unit_price' => $this->unit_price,
            'line_total' => Decimal::mul($this->unit_price, $this->quantity, 2),
            'modifiers' => $this->modifiers_json,
            'notes' => $this->notes,
            'status' => $this->status->value,
            'sent_to_kitchen_at' => $this->sent_to_kitchen_at?->toIso8601String(),
            'ready_at' => $this->ready_at?->toIso8601String(),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_reason' => $this->void_reason,
        ];
    }
}
