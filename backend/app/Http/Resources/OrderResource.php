<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
final class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'order_number' => $this->order_number,
            'order_type' => $this->order_type->value,
            'status' => $this->status->value,
            'business_date' => $this->business_date->toDateString(),
            'diners_count' => $this->diners_count,
            'customer_name' => $this->customer_name,
            'opened_at' => $this->opened_at->toIso8601String(),
            'table' => $this->whenLoaded('diningTable', fn (): ?array => $this->diningTable === null ? null : [
                'id' => $this->diningTable->uuid,
                'code' => $this->diningTable->code,
            ]),
            'waiter' => $this->whenLoaded('waiter', fn (): array => [
                'id' => $this->waiter->uuid,
                'name' => $this->waiter->name,
            ]),
            'totals' => [
                'subtotal' => $this->subtotal,
                'packaging_total' => $this->packaging_total,
                'tax_amount' => $this->tax_amount,
                'service_charge' => $this->service_charge,
                'total_amount' => $this->total_amount,
                'paid_amount' => $this->paid_amount,
                'balance' => Decimal::sub($this->total_amount, $this->paid_amount, 2),
            ],
            'delivery_address' => $this->delivery_address,
            'delivery_phone' => $this->delivery_phone,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
