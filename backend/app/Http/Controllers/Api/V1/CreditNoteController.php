<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\IssueCreditNoteRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Models\User;
use App\Services\CreditNoteService;
use Illuminate\Http\JsonResponse;

final class CreditNoteController extends Controller
{
    public function __construct(private readonly CreditNoteService $creditNotes) {}

    public function store(IssueCreditNoteRequest $request, Sale $sale): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $creditNote = $this->creditNotes->issue($sale, $request->reason(), $request->description(), $user);

        return $this->created(SaleResource::make($creditNote), "Nota de crédito {$creditNote->documentNumber()} emitida.");
    }
}
