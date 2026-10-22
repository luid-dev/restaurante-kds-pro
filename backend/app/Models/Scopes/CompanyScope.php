<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtra por la empresa activa. Falla en cerrado: sin contexto de tenant
 * lanza una excepción en lugar de devolver datos de todas las empresas.
 *
 * @implements Scope<Model>
 */
final class CompanyScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = app(TenantContext::class)->idOrFail($model::class);

        $builder->where($model->qualifyColumn('company_id'), $companyId);
    }
}
