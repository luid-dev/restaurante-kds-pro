<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductionArea;
use App\Traits\AuditableModel;
use App\Traits\BelongsToCompany;
use App\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * Nota técnica predefinida para la comanda (Pecho, Pierna, Sin helar...).
 *
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property string $name
 * @property ProductionArea|null $production_area
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['name', 'production_area', 'sort_order', 'is_active'])]
#[Hidden(['id', 'company_id'])]
class ItemModifier extends Model
{
    use AuditableModel, BelongsToCompany, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'production_area' => ProductionArea::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
