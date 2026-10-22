<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\AuditableModel;
use App\Traits\BelongsToCompany;
use App\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea del escandallo: un plato descuenta `quantity_required` del insumo
 * (p. ej. 1/4 de pollo descuenta 0.2500 de pollo entero).
 *
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property int $menu_item_id
 * @property int $ingredient_id
 * @property numeric-string $quantity_required
 * @property bool $apply_only_on_takeout
 * @property-read MenuItem $menuItem
 * @property-read Ingredient $ingredient
 */
#[Fillable(['menu_item_id', 'ingredient_id', 'quantity_required', 'apply_only_on_takeout'])]
#[Hidden(['id', 'company_id', 'menu_item_id', 'ingredient_id'])]
class Recipe extends Model
{
    use AuditableModel, BelongsToCompany, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_required' => 'decimal:4',
            'apply_only_on_takeout' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    /**
     * @return BelongsTo<Ingredient, $this>
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
