<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Comanda recién enviada a producción, agrupada por área (GRILL_OVEN, HOT_KITCHEN,
 * COLD_BAR). Viaja completa para que el KDS la pinte sin volver a consultar la API
 * y solo llega a private-company.{uuid}.kds (usuarios con permiso kds.view).
 */
final class OrderSentToKitchen implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $order  Cabecera del ticket (KitchenDisplayService::presentOrder)
     * @param  array<string, list<array<string, mixed>>>  $areas  Platos nuevos por área de producción
     */
    public function __construct(
        public readonly string $companyUuid,
        public readonly array $order,
        public readonly string $sentAt,
        public readonly array $areas,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("company.{$this->companyUuid}.kds")];
    }

    public function broadcastAs(): string
    {
        return 'kds.order.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['order' => $this->order, 'sent_at' => $this->sentAt, 'areas' => $this->areas];
    }
}
