<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CashSessionStatus;
use App\Exceptions\BillingException;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Turno de caja: todo cobro se asocia al turno abierto del cajero.
 */
final class CashSessionService
{
    public function currentFor(User $cashier): ?CashRegisterSession
    {
        return CashRegisterSession::query()
            ->with('cashRegister')
            ->where('opened_by', $cashier->id)
            ->where('status', CashSessionStatus::Open)
            ->first();
    }

    /**
     * @param  numeric-string  $openingAmount
     */
    public function open(CashRegister $register, User $cashier, string $openingAmount): CashRegisterSession
    {
        try {
            return DB::transaction(function () use ($register, $cashier, $openingAmount): CashRegisterSession {
                if ($this->currentFor($cashier) !== null) {
                    throw BillingException::cashSessionAlreadyOpen('del cajero');
                }

                $session = CashRegisterSession::query()->create([
                    'cash_register_id' => $register->id,
                    'opened_by' => $cashier->id,
                    'status' => CashSessionStatus::Open,
                    'opening_amount' => $openingAmount,
                    'opened_at' => CarbonImmutable::now(),
                ]);

                return $session->setRelation('cashRegister', $register);
            });
        } catch (UniqueConstraintViolationException) {
            // Índice parcial: un solo turno abierto por caja aunque dos cajeros abran a la vez.
            throw BillingException::cashSessionAlreadyOpen($register->code);
        }
    }
}
