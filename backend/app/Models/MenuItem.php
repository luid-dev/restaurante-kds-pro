<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductionArea;
use App\Enums\TaxAffectationType;
use App\Traits\AuditableModel;
use App\Traits\BelongsToCompany;
use App\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Plato o bebida de la carta.
 *
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property int $menu_category_id
 * @property ProductionArea $production_area
 * @property string $sku
 * @property string $name
 * @property numeric-string $sale_price
 * @property TaxAffectationType $tax_affectation_type
 * @property int $preparation_time_minutes
 * @property numeric-string $packaging_surcharge
 * @property bool $is_available
 * @property-read MenuCategory $category
 */
#[Fillable([
    'menu_category_id', 'production_area', 'sku', 'name', 'sale_price', 'tax_affectation_type',
    'preparation_time_minutes', 'packaging_surcharge', 'is_available',
])]
#[Hidden(['id', 'company_id', 'menu_category_id'])]
class MenuItem extends Model
{
    use AuditableModel, BelongsToCompany, HasPublicUuid, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'production_area' => ProductionArea::class,
            'tax_affectation_type' => TaxAffectationType::class,
            'sale_price' => 'decimal:2',
            'packaging_surcharge' => 'decimal:2',
            'preparation_time_minutes' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MenuCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    /**
     * Escandallo: cuánto de cada insumo descuenta una unidad vendida.
     *
     * @return HasMany<Recipe, $this>
     */
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }
}
