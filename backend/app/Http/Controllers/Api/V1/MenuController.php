<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemModifierResource;
use App\Http\Resources\MenuCategoryResource;
use App\Models\ItemModifier;
use App\Models\MenuCategory;
use Illuminate\Http\JsonResponse;

/**
 * Carta técnica del mozo: categorías con platos disponibles e indicaciones de cocina.
 */
final class MenuController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $categories = MenuCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['items' => static fn ($query) => $query->where('is_available', true)->orderBy('name')])
            ->get();

        $modifiers = ItemModifier::query()->where('is_active', true)->orderBy('sort_order')->get();

        return $this->success([
            'categories' => MenuCategoryResource::collection($categories)->resolve(),
            'modifiers' => ItemModifierResource::collection($modifiers)->resolve(),
        ]);
    }
}
