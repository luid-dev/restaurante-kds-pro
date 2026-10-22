<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaxAffectationType;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea del comprobante. En formato BY_CONSUMPTION existe una sola línea con la
 * glosa "POR CONSUMO DE ALIMENTOS Y BEBIDAS" y sin order_item_id.
 *
 * @property int $id
 * @property int $company_id
 * @property int $sale_id
 * @property int|null $order_item_id
 * @property string $description
 * @property string $unit_code Unidad SUNAT (catálogo 03) o ZZ para la glosa de consumo
 * @property numeric-string $quantity
 * @property numeric-string $unit_value
 * @property numeric-string $unit_price
 * @property TaxAffectationType $tax_affectation_type
 * @property numeric-string $igv_amount
 * @property numeric-string $line_total
 */
#[Fillable([
    'sale_id', 'order_item_id', 'description', 'unit_code', 'quantity', 'unit_value', 'unit_price',
    'tax_affectation_type', 'igv_amount', 'line_total',
])]
#[Hidden(['id', 'company_id', 'sale_id', 'order_item_id'])]
class SaleItem extends Model
{
    use BelongsToCompany;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_affectation_type' => TaxAffectationType::class,
            'quantity' => 'decimal:2',
            'unit_value' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'igv_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
