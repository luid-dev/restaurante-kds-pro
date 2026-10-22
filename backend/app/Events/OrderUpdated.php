<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * La comanda cambió (platos enviados, anulados o listos): los clientes vuelven a
 * pedir el detalle por la API, que aplica permisos. El socket solo avisa.
 */
final class OrderUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable;

    public function __construct(
        public readonly string $companyUuid,
        public readonly string $orderUuid,
        public readonly string $status,
        public readonly string $totalAmount,
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
        return 'order.updated';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return ['order_id' => $this->orderUuid, 'status' => $this->status, 'total_amount' => $this->totalAmount];
    }
}
