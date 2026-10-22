<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TableStatus;
use App\Traits\AuditableModel;
use App\Traits\BelongsToCompany;
use App\Traits\HasPublicUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Mesa del plano. Se bloquea con lockForUpdate() al abrirla, unirla o cobrarla.
 *
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property int $dining_zone_id
 * @property string $code
 * @property int $capacity
 * @property int $grid_x
 * @property int $grid_y
 * @property TableStatus $status
 * @property int|null $current_order_id
 * @property CarbonImmutable|null $opened_at
 */
#[Fillable(['dining_zone_id', 'code', 'capacity', 'grid_x', 'grid_y', 'status', 'current_order_id', 'opened_at'])]
#[Hidden(['id', 'company_id', 'dining_zone_id', 'current_order_id'])]
class RestaurantTable extends Model
{
    use AuditableModel, BelongsToCompany, HasPublicUuid, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TableStatus::class,
            'capacity' => 'integer',
            'grid_x' => 'integer',
            'grid_y' => 'integer',
            'opened_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DiningZone, $this>
     */
    public function diningZone(): BelongsTo
    {
        return $this->belongsTo(DiningZone::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function currentOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'current_order_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
