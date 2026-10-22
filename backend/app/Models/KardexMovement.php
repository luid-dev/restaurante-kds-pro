<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KardexMovementType;
use App\Traits\BelongsToCompany;
use App\Traits\HasPublicUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Movimiento del kardex valorizado (formato SUNAT 13.1). Inmutable en PostgreSQL:
 * las correcciones se registran como ADJUSTMENT_IN / ADJUSTMENT_OUT.
 *
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property int $warehouse_id
 * @property int $ingredient_id
 * @property KardexMovementType $movement_type
 * @property string $sunat_operation_type
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $document_reference
 * @property numeric-string $quantity_in
 * @property numeric-string $quantity_out
 * @property numeric-string $unit_cost
 * @property numeric-string $total_cost
 * @property numeric-string $balance_quantity
 * @property numeric-string $balance_unit_cost
 * @property numeric-string $balance_total_cost
 * @property string|null $notes
 * @property int|null $created_by
 * @property CarbonImmutable $occurred_at
 * @property-read Ingredient $ingredient
 * @property-read Warehouse $warehouse
 * @property-read User|null $creator
 * @property-read Model|null $source
 */
#[Fillable([
    'warehouse_id', 'ingredient_id', 'movement_type', 'sunat_operation_type', 'source_type', 'source_id',
    'document_reference', 'quantity_in', 'quantity_out', 'unit_cost', 'total_cost', 'balance_quantity',
    'balance_unit_cost', 'balance_total_cost', 'notes', 'created_by', 'occurred_at',
])]
#[Hidden(['id', 'company_id', 'warehouse_id', 'ingredient_id', 'source_type', 'source_id', 'created_by'])]
class KardexMovement extends Model
{
    use BelongsToCompany, HasPublicUuid;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'movement_type' => KardexMovementType::class,
            'quantity_in' => 'decimal:4',
            'quantity_out' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
            'balance_quantity' => 'decimal:4',
            'balance_unit_cost' => 'decimal:4',
            'balance_total_cost' => 'decimal:4',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Venta, compra, merma o ajuste que originó el movimiento.
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Ingredient, $this>
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
