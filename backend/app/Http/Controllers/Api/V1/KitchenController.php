<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProductionArea;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\AdvanceKitchenItemsRequest;
use App\Models\OrderItem;
use App\Services\KitchenDisplayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class KitchenController extends Controller
{
    public function __construct(private readonly KitchenDisplayService $kitchen) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['area' => ['nullable', Rule::enum(ProductionArea::class)]]);
        $area = $request->filled('area') ? ProductionArea::from($request->string('area')->toString()) : null;

        return $this->success($this->kitchen->tickets($area));
    }

    public function advance(AdvanceKitchenItemsRequest $request): JsonResponse
    {
        $items = $this->kitchen->advanceItems($request->itemUuids(), $request->target());

        return $this->success([
            'item_ids' => array_map(static fn (OrderItem $item): string => $item->uuid, $items),
            'status' => $request->target()->value,
        ], "{$request->target()->label()}: ".count($items).' plato(s).');
    }
}
