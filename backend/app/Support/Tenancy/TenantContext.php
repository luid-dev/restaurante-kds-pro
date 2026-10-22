<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Exceptions\TenancyException;
use App\Models\Company;
use Closure;
use Illuminate\Support\Facades\Context;

/**
 * Empresa (tenant) activa durante la petición o el job.
 *
 * Se guarda como dato oculto de Laravel Context: no se escribe en los logs y
 * viaja automáticamente con los jobs encolados desde la petición.
 */
final class TenantContext
{
    private const string KEY = 'tenant.company_id';

    public function set(Company|int $company): void
    {
        $id = $company instanceof Company ? $company->id : $company;

        Context::addHidden(self::KEY, $id);
    }

    public function id(): ?int
    {
        $id = Context::getHidden(self::KEY);

        return is_int($id) ? $id : null;
    }

    /**
     * @param  class-string  $requiredBy
     */
    public function idOrFail(string $requiredBy): int
    {
        return $this->id() ?? throw TenancyException::missingContext($requiredBy);
    }

    public function check(): bool
    {
        return $this->id() !== null;
    }

    public function forget(): void
    {
        Context::forgetHidden(self::KEY);
    }

    /**
     * Ejecuta el callback dentro de la empresa indicada y restaura el contexto previo.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function run(Company|int $company, Closure $callback): mixed
    {
        $previous = $this->id();
        $this->set($company);

        try {
            return $callback();
        } finally {
            $previous === null ? $this->forget() : $this->set($previous);
        }
    }
}
