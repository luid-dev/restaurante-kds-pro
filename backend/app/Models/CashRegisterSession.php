<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashSessionStatus;
use App\Traits\AuditableModel;
use App\Traits\BelongsToCompany;
use App\Traits\HasPublicUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Turno de caja (apertura, arqueo y cierre).
 *
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property int $cash_register_id
 * @property int $opened_by
 * @property int|null $closed_by
 * @property CashSessionStatus $status
 * @property numeric-string $opening_amount
 * @property numeric-string|null $expected_amount
 * @property numeric-string|null $closing_amount
 * @property CarbonImmutable $opened_at
 * @property CarbonImmutable|null $closed_at
 * @property-read CashRegister $cashRegister
 */
#[Fillable([
    'cash_register_id', 'opened_by', 'closed_by', 'status', 'opening_amount', 'expected_amount',
    'closing_amount', 'opened_at', 'closed_at',
])]
#[Hidden(['id', 'company_id', 'cash_register_id', 'opened_by', 'closed_by'])]
class CashRegisterSession extends Model
{
    use AuditableModel, BelongsToCompany, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CashSessionStatus::class,
            'opening_amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'closing_amount' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CashRegister, $this>
     */
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
