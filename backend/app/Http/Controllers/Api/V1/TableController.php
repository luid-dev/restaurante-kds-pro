<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\OpenTableRequest;
use App\Http\Requests\Operations\TransferTableRequest;
use App\Http\Resources\OrderResource;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\TableService;
use Illuminate\Http\JsonResponse;

final class TableController extends Controller
{
    public function __construct(private readonly TableService $tables) {}

    public function open(OpenTableRequest $request, RestaurantTable $table): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $order = $this->tables->openTableAndCreateOrder($table, $user, $request->integer('diners_count'));

        return $this->created(OrderResource::make($order), "Mesa {$table->code} abierta.");
    }

    public function transfer(TransferTableRequest $request, RestaurantTable $table): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $target = RestaurantTable::query()->where('uuid', $request->string('target_table_id')->toString())->firstOrFail();
        $order = $this->tables->transferOrMergeTables($table, $target, $user);

        return $this->success(OrderResource::make($order), "Cuenta movida a {$target->code}.");
    }
}
