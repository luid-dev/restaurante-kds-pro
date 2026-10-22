<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderItemStatus: string
{
    case Pending = 'PENDING';
    case Preparing = 'PREPARING';
    case Ready = 'READY';
    case Served = 'SERVED';
    case Voided = 'VOIDED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En cola',
            self::Preparing => 'En preparación',
            self::Ready => 'Listo para servir',
            self::Served => 'Entregado',
            self::Voided => 'Anulado',
        };
    }

    /**
     * Estados activos en la pantalla de cocina.
     *
     * @return list<self>
     */
    public static function kitchenQueue(): array
    {
        return [self::Pending, self::Preparing, self::Ready];
    }

    /**
     * El KDS solo avanza (en cola -> en preparación -> listo -> entregado), nunca retrocede.
     */
    public function canAdvanceTo(self $target): bool
    {
        $flow = [self::Pending, self::Preparing, self::Ready, self::Served];
        $current = array_search($this, $flow, true);
        $next = array_search($target, $flow, true);

        return $current !== false && $next !== false && $next > $current;
    }

    /**
     * Un plato ya comandado solo se anula con permiso orders.void_item y motivo auditado.
     */
    public function requiresVoidAuthorization(): bool
    {
        return $this !== self::Pending && $this !== self::Voided;
    }
}
