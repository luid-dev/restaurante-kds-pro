<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DiningZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DiningZone
 */
final class DiningZoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'sort_order' => $this->sort_order,
            'tables' => RestaurantTableResource::collection($this->whenLoaded('tables')),
        ];
    }
}
