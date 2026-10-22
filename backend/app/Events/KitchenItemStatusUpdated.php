<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Cocina marcó platos como en preparación, listos o entregados (o caja los anuló).
 * Lo reciben las demás pantallas KDS y los mozos (canal de empresa) para recoger el pedido.
 */
final class KitchenItemStatusUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable;

    /**
     * @param  list<string>  $itemUuids
     */
    public function __construct(
        public readonly string $companyUuid,
        public readonly string $orderUuid,
        public readonly ?string $tableCode,
        public readonly string $area,
        public readonly array $itemUuids,
        public readonly string $status,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("company.{$this->companyUuid}.kds"),
            new PrivateChannel('company.'.$this->companyUuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'kds.item.status';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderUuid,
            'table_code' => $this->tableCode,
            'area' => $this->area,
            'item_ids' => $this->itemUuids,
            'status' => $this->status,
        ];
    }
}
