<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderItemStatus;
use App\Enums\OrderType;
use App\Enums\ProductionArea;
use App\Enums\TaxAffectationType;
use App\Traits\AuditableModel;
use App\Traits\BelongsToCompany;
use App\Traits\HasPublicUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plato comandado. paid_quantity permite cobrar el ítem en varios comprobantes
 * (división de cuenta); la anulación exige permiso, motivo y queda auditada.
 *
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property int $order_id
 * @property int $menu_item_id
 * @property ProductionArea $production_area
 * @property numeric-string $quantity
 * @property numeric-string $paid_quantity
 * @property numeric-string $unit_price
 * @property TaxAffectationType $tax_affectation_type Congelada al comandar (catálogo SUNAT 07)
 * @property list<string> $modifiers_json
 * @property string|null $notes
 * @property OrderItemStatus $status
 * @property CarbonImmutable|null $sent_to_kitchen_at
 * @property CarbonImmutable|null $ready_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 * @property CarbonImmutable|null $voided_at
 * @property-read Order $order
 * @property-read MenuItem $menuItem
 */
#[Fillable([
    'order_id', 'menu_item_id', 'production_area', 'quantity', 'paid_quantity', 'unit_price', 'tax_affectation_type', 'modifiers_json',
    'notes', 'status', 'sent_to_kitchen_at', 'ready_at', 'voided_by', 'void_reason', 'voided_at',
])]
#[Hidden(['id', 'company_id', 'order_id', 'menu_item_id', 'voided_by'])]
class OrderItem extends Model
{
    use AuditableModel, BelongsToCompany, HasPublicUuid;

    /** Indicación que convierte un plato de salón en "para llevar" (empaque y envases de receta). */
    public const string TAKEAWAY_MODIFIER = 'Para llevar';

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'paid_quantity' => '0.00',
        'tax_affectation_type' => '10',
        'modifiers_json' => '[]',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'production_area' => ProductionArea::class,
            'status' => OrderItemStatus::class,
            'tax_affectation_type' => TaxAffectationType::class,
            'quantity' => 'decimal:2',
            'paid_quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'modifiers_json' => 'array',
            'sent_to_kitchen_at' => 'datetime',
            'ready_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * Cantidad aún no cobrada (BCMath, escala 2).
     *
     * @return numeric-string
     */
    public function unpaidQuantity(): string
    {
        return bcsub($this->quantity, $this->paid_quantity, 2);
    }

    /**
     * El plato lleva envase: comanda para llevar / delivery o plato de salón marcado "Para llevar".
     */
    public function usesPackaging(OrderType $orderType): bool
    {
        return $orderType->usesPackaging() || in_array(self::TAKEAWAY_MODIFIER, $this->modifiers_json, true);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
