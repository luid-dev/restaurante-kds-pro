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

/**
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property string $code
 * @property string $name
 * @property bool $is_default
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'is_default', 'is_active'])]
#[Hidden(['id', 'company_id'])]
class Warehouse extends Model
{
    use AuditableModel, BelongsToCompany, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
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
}
