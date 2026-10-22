<?php

declare(strict_types=1);

namespace App\Traits;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Pista de auditoría (ISO/IEC 27001 A.8.15) sobre spatie/laravel-activitylog.
 *
 * Cada registro guarda empresa, trace_id e IP de origen. La tabla activity_log
 * es inmutable a nivel de PostgreSQL (trigger) y los secretos se excluyen
 * globalmente en config/activitylog.php (default_except_attributes).
 *
 * @mixin Model
 */
trait AuditableModel
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('audit')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function beforeActivityLogged(Model $activity, string $eventName): void
    {
        $traceId = Context::get('trace_id');

        $activity->setAttribute('company_id', $this->auditCompanyId());
        $activity->setAttribute('trace_id', is_string($traceId) ? $traceId : null);
        $activity->setAttribute('ip_address', app()->runningInConsole() ? null : request()->ip());
    }

    /**
     * Empresa a la que pertenece el registro auditado.
     */
    protected function auditCompanyId(): ?int
    {
        $companyId = filter_var($this->getAttributes()['company_id'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        return $companyId ?? app(TenantContext::class)->id();
    }
}
