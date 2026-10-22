<?php

declare(strict_types=1);

namespace App\Traits;

use App\Exceptions\TenancyException;
use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aislamiento multi-empresa para modelos con columna company_id:
 *  - GlobalScope que filtra por la empresa del contexto (falla en cerrado).
 *  - Asigna company_id al crear y bloquea escrituras hacia otra empresa.
 *  - company_id es inmutable una vez persistido.
 *
 * company_id nunca debe estar en #[Fillable]: solo lo asigna este trait.
 *
 * @mixin Model
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(static function (Model $model): void {
            $tenant = app(TenantContext::class);
            $companyId = $model->getAttribute('company_id');

            if ($companyId === null) {
                $model->setAttribute('company_id', $tenant->idOrFail($model::class));

                return;
            }

            if ($tenant->check() && filter_var($companyId, FILTER_VALIDATE_INT) !== $tenant->id()) {
                throw TenancyException::crossTenantWrite($model::class);
            }
        });

        static::updating(static function (Model $model): void {
            if ($model->isDirty('company_id')) {
                throw TenancyException::companyReassignment($model::class);
            }
        });
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
