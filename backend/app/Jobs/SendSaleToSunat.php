<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Sale;
use App\Services\SunatService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Envía el comprobante firmado al webservice de SUNAT y guarda el CDR. Corre
 * fuera de la petición de cobro: una caída de SUNAT nunca bloquea la caja.
 */
final class SendSaleToSunat implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public readonly int $companyId,
        public readonly int $saleId,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(SunatService $sunat, TenantContext $tenant): void
    {
        $tenant->run($this->companyId, function () use ($sunat): void {
            $sunat->deliver(Sale::query()->findOrFail($this->saleId));
        });
    }
}
