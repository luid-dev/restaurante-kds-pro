<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\OpenCashSessionRequest;
use App\Http\Resources\CashSessionResource;
use App\Models\CashRegister;
use App\Models\DocumentSeries;
use App\Models\User;
use App\Services\CashSessionService;
use App\Support\Decimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CashSessionController extends Controller
{
    public function __construct(private readonly CashSessionService $sessions) {}

    /**
     * Turno abierto del cajero, cajas disponibles y series habilitadas para cobrar.
     */
    public function current(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $session = $this->sessions->currentFor($user);

        return $this->success([
            'session' => $session === null ? null : CashSessionResource::make($session)->resolve(),
            'registers' => CashRegister::query()->where('is_active', true)->orderBy('code')->get()
                ->map(static fn (CashRegister $register): array => ['id' => $register->uuid, 'code' => $register->code, 'name' => $register->name])->all(),
            'series' => DocumentSeries::query()->where('is_active', true)->whereIn('document_type', ['01', '03', '00'])->orderBy('series')->get()
                ->map(static fn (DocumentSeries $series): array => [
                    'series' => $series->series,
                    'document_type' => $series->document_type->value,
                    'label' => $series->document_type->label(),
                    'next_number' => $series->formatNumber($series->next_correlative),
                ])->all(),
        ]);
    }

    public function store(OpenCashSessionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $register = CashRegister::query()->where('is_active', true)->where('uuid', $request->string('cash_register_id')->toString())->firstOrFail();
        $session = $this->sessions->open($register, $user, Decimal::normalize($request->string('opening_amount')->toString(), 2));

        return $this->created(CashSessionResource::make($session), "Turno abierto en {$register->name}.");
    }
}
