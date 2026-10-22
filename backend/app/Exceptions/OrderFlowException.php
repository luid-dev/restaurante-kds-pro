<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCode;
use App\Enums\OrderItemStatus;
use App\Models\Order;
use App\Models\RestaurantTable;

/**
 * Reglas del flujo de mesa y comanda que el cliente debe poder explicar al mozo.
 */
final class OrderFlowException extends ApiException
{
    public static function tableNotAvailable(RestaurantTable $table): self
    {
        return new self("La mesa {$table->code} ya no está libre. Actualice el plano e intente con otra mesa.", 409, ErrorCode::Conflict);
    }

    public static function orderClosed(Order $order): self
    {
        return new self("La comanda {$order->order_number} ya no admite cambios ({$order->status->label()}).", 409, ErrorCode::Conflict);
    }

    /**
     * @param  list<string>  $names
     */
    public static function unavailableItems(array $names): self
    {
        return new self('Platos no disponibles en la carta: '.implode(', ', $names).'.', 422, ErrorCode::BusinessRuleViolation);
    }

    /**
     * @param  list<string>  $names
     */
    public static function unknownModifiers(array $names): self
    {
        return new self('Indicaciones no registradas: '.implode(', ', $names).'.', 422, ErrorCode::BusinessRuleViolation);
    }

    public static function invalidKitchenTransition(OrderItemStatus $from, OrderItemStatus $to): self
    {
        return new self("El plato no puede pasar de \"{$from->label()}\" a \"{$to->label()}\".", 409, ErrorCode::Conflict);
    }

    public static function alreadyVoided(): self
    {
        return new self('El plato ya fue anulado.', 409, ErrorCode::Conflict);
    }

    public static function alreadyPaid(): self
    {
        return new self('El plato ya fue cobrado: anúlelo con una nota de crédito.', 409, ErrorCode::Conflict);
    }

    public static function sameTable(): self
    {
        return new self('Elija una mesa distinta a la de origen.', 422, ErrorCode::BusinessRuleViolation);
    }

    public static function tableNotFound(): self
    {
        return new self('La mesa ya no existe en el plano.', 404, ErrorCode::NotFound);
    }

    public static function tableWithoutOrder(RestaurantTable $table): self
    {
        return new self("La mesa {$table->code} no tiene una cuenta abierta.", 409, ErrorCode::Conflict);
    }

    public static function mergeWithPayments(Order $order): self
    {
        return new self("La cuenta {$order->order_number} ya tiene pagos parciales: cóbrela antes de unir mesas.", 409, ErrorCode::Conflict);
    }

    public static function itemNotInOrder(): self
    {
        return new self('El plato no pertenece a esta comanda.', 404, ErrorCode::NotFound);
    }

    public static function itemNotFound(): self
    {
        return new self('Algunos platos ya no están en la cola de cocina. Actualice la pantalla.', 404, ErrorCode::NotFound);
    }

    public static function emptyOrder(Order $order): self
    {
        return new self("La comanda {$order->order_number} no tiene consumos por cobrar.", 422, ErrorCode::BusinessRuleViolation);
    }
}
