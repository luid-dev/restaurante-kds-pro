<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Traits\AuditableModel;
use App\Traits\BelongsToCompany;
use App\Traits\HasPublicUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Comanda de salón, para llevar o delivery.
 *
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property int|null $restaurant_table_id
 * @property int $waiter_id
 * @property int|null $cash_register_session_id
 * @property CarbonImmutable $business_date
 * @property string $order_number
 * @property OrderType $order_type
 * @property int $diners_count
 * @property string|null $customer_name
 * @property string|null $delivery_address
 * @property string|null $delivery_phone
 * @property numeric-string $subtotal
 * @property numeric-string $packaging_total
 * @property numeric-string $tax_amount
 * @property numeric-string $service_charge
 * @property numeric-string $total_amount
 * @property numeric-string $paid_amount
 * @property OrderStatus $status
 * @property CarbonImmutable $opened_at
 * @property CarbonImmutable|null $closed_at
 * @property-read RestaurantTable|null $diningTable
 * @property-read User $waiter
 */
#[Fillable([
    'restaurant_table_id', 'waiter_id', 'cash_register_session_id', 'business_date', 'order_number', 'order_type',
    'diners_count', 'customer_name', 'delivery_address', 'delivery_phone', 'subtotal', 'packaging_total',
    'tax_amount', 'service_charge', 'total_amount', 'paid_amount', 'status', 'opened_at', 'closed_at',
])]
#[Hidden(['id', 'company_id', 'restaurant_table_id', 'waiter_id', 'cash_register_session_id'])]
class Order extends Model
{
    use AuditableModel, BelongsToCompany, HasPublicUuid;

    /**
     * Mismos valores por defecto que la tabla: una comanda recién creada ya expone sus totales.
     *
     * @var array<string, string|int>
     */
    protected $attributes = [
        'diners_count' => 1,
        'subtotal' => '0.00',
        'packaging_total' => '0.00',
        'tax_amount' => '0.00',
        'service_charge' => '0.00',
        'total_amount' => '0.00',
        'paid_amount' => '0.00',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_type' => OrderType::class,
            'status' => OrderStatus::class,
            'business_date' => 'date',
            'diners_count' => 'integer',
            'subtotal' => 'decimal:2',
            'packaging_total' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RestaurantTable, $this>
     */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    /**
     * @return BelongsTo<CashRegisterSession, $this>
     */
    public function cashRegisterSession(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Comprobantes emitidos: varios cuando la cuenta se divide.
     *
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Descargos de insumos generados por esta comanda.
     *
     * @return MorphMany<KardexMovement, $this>
     */
    public function kardexMovements(): MorphMany
    {
        return $this->morphMany(KardexMovement::class, 'source');
    }
}
