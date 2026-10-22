<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\SendOrderItemsRequest;
use App\Http\Requests\Operations\StoreCounterOrderRequest;
use App\Http\Requests\Operations\VoidOrderItemRequest;
use App\Http\Resources\OrderItemResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function show(Order $order): JsonResponse
    {
        return $this->success(OrderResource::make($order->load(['diningTable', 'waiter', 'items.menuItem'])));
    }

    public function store(StoreCounterOrderRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->created(OrderResource::make($this->orders->createCounterOrder($request->toData(), $user)), 'Pedido registrado.');
    }

    public function sendItems(SendOrderItemsRequest $request, Order $order): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $updated = $this->orders->addItemsAndSendToKitchen($order, $request->toLines(), $user);

        return $this->success(OrderResource::make($updated), 'Comanda enviada a cocina.');
    }

    public function voidItem(VoidOrderItemRequest $request, Order $order, OrderItem $item): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $voided = $this->orders->voidItem($order, $item, $user, $request->string('reason')->trim()->toString());

        return $this->success(OrderItemResource::make($voided->load('menuItem')), 'Plato anulado.');
    }

    public function preBill(Request $request, Order $order): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->success(OrderResource::make($this->orders->requestPreBill($order, $user)), 'Precuenta emitida.');
    }
}
