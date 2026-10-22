<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MenuItem
 */
final class MenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'sku' => $this->sku,
            'name' => $this->name,
            'production_area' => $this->production_area->value,
            'sale_price' => $this->sale_price,
            'packaging_surcharge' => $this->packaging_surcharge,
            'preparation_time_minutes' => $this->preparation_time_minutes,
            'is_available' => $this->is_available,
            'category_id' => $this->whenLoaded('category', fn (): string => $this->category->uuid),
        ];
    }
}
