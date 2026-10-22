<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Cambio de estado de una mesa (abierta, en precuenta, liberada) para todos los
 * mozos de la empresa. Se emite solo tras el commit y un fallo de Reverb nunca
 * revierte la operación (ShouldRescue).
 */
final class TableStatusUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $table
     */
    public function __construct(
        public readonly string $companyUuid,
        public readonly array $table,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('company.'.$this->companyUuid)];
    }

    public function broadcastAs(): string
    {
        return 'table.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['table' => $this->table];
    }
}
