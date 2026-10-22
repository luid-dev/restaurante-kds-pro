<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\ChargeOrderRequest;
use App\Http\Requests\Operations\QuoteChargeRequest;
use App\Http\Resources\SaleResource;
use App\Models\Order;
use App\Models\Sale;
use App\Models\User;
use App\Services\SplitBillService;
use App\Support\Decimal;
use Illuminate\Http\JsonResponse;

final class ChargeController extends Controller
{
    public function __construct(private readonly SplitBillService $billing) {}

    public function store(ChargeOrderRequest $request, Order $order): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $sale = $this->billing->charge($order, $request->toData(), $user);

        return $this->created(SaleResource::make($sale), "Comprobante {$sale->documentNumber()} emitido.");
    }

    public function quote(QuoteChargeRequest $request, Order $order): JsonResponse
    {
        $quote = $this->billing->quote($order, $request->chargeMode(), $request->consumptionFormat(), $request->chargeLines(), $request->chargeAmount());
        $balance = $this->billing->balance($order);

        return $this->success([
            'mode' => $quote->mode->value,
            'gross' => $quote->gross,
            'service_charge' => $quote->serviceCharge,
            'total' => $quote->total,
            'settles_order' => $quote->settlesOrder,
            'balance' => $balance,
            'balance_after' => Decimal::sub($balance, $quote->total, 2),
        ]);
    }

    public function show(Sale $sale): JsonResponse
    {
        return $this->success(SaleResource::make($sale->load(['items', 'payments', 'order.diningTable', 'relatedSale', 'creditNote'])));
    }
}
