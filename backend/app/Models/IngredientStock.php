<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock real de un insumo en un almacén. Se lee con lockForUpdate() al despachar
 * comandas; su historial valorizado vive en kardex_movements (no se audita aparte).
 *
 * @property int $id
 * @property int $company_id
 * @property int $ingredient_id
 * @property int $warehouse_id
 * @property numeric-string $stock
 * @property numeric-string $average_cost
 */
#[Table('ingredient_warehouse')]
#[Fillable(['ingredient_id', 'warehouse_id', 'stock', 'average_cost'])]
#[Hidden(['id', 'company_id', 'ingredient_id', 'warehouse_id'])]
class IngredientStock extends Model
{
    use BelongsToCompany;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stock' => 'decimal:4',
            'average_cost' => 'decimal:4',
        ];
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
}
