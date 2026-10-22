<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\AuditableModel;
use App\Traits\BelongsToCompany;
use App\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Salón, piso o terraza.
 *
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['name', 'sort_order', 'is_active'])]
#[Hidden(['id', 'company_id'])]
class DiningZone extends Model
{
    use AuditableModel, BelongsToCompany, HasPublicUuid, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<RestaurantTable, $this>
     */
    public function tables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class);
    }
}
