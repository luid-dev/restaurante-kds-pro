<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UnitOfMeasure;
use App\Traits\AuditableModel;
use App\Traits\BelongsToCompany;
use App\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Insumo crudo controlado por peso, volumen o unidad.
 *
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property string $sku
 * @property string $name
 * @property UnitOfMeasure $unit_code
 * @property numeric-string $unit_cost
 * @property numeric-string $min_stock
 * @property bool $is_active
 */
#[Fillable(['sku', 'name', 'unit_code', 'unit_cost', 'min_stock', 'is_active'])]
#[Hidden(['id', 'company_id'])]
class Ingredient extends Model
{
    use AuditableModel, BelongsToCompany, HasPublicUuid, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_code' => UnitOfMeasure::class,
            'unit_cost' => 'decimal:4',
            'min_stock' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<IngredientStock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(IngredientStock::class);
    }

    /**
     * @return HasMany<Recipe, $this>
     */
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    /**
     * @return HasMany<KardexMovement, $this>
     */
    public function kardexMovements(): HasMany
    {
        return $this->hasMany(KardexMovement::class);
    }
}
