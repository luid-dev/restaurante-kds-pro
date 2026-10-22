<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CashRegisterSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashRegisterSession
 */
final class CashSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status->value,
            'opening_amount' => $this->opening_amount,
            'opened_at' => $this->opened_at->toIso8601String(),
            'cash_register' => $this->whenLoaded('cashRegister', fn (): array => [
                'id' => $this->cashRegister->uuid,
                'code' => $this->cashRegister->code,
                'name' => $this->cashRegister->name,
            ]),
        ];
    }
}
