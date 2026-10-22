<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ItemModifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ItemModifier
 */
final class ItemModifierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'production_area' => $this->production_area?->value,
        ];
    }
}
