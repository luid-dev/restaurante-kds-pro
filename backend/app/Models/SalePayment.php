<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Traits\BelongsToCompany;
use App\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago de un comprobante. Un cobro mixto genera varias filas cuya suma es el total.
 *
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property int $sale_id
 * @property PaymentMethod $method
 * @property numeric-string $amount
 * @property numeric-string|null $received_amount
 * @property numeric-string $change_amount
 * @property string|null $reference
 */
#[Fillable(['sale_id', 'method', 'amount', 'received_amount', 'change_amount', 'reference'])]
#[Hidden(['id', 'company_id', 'sale_id'])]
class SalePayment extends Model
{
    use BelongsToCompany, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'received_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
