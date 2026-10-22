<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\CreditNoteReason;
use App\Enums\ErrorCode;
use App\Models\Order;
use App\Models\Sale;

/**
 * Reglas de caja y facturación que el cajero debe poder resolver en pantalla.
 */
final class BillingException extends ApiException
{
    public static function noOpenCashSession(): self
    {
        return new self('Abra su turno de caja antes de cobrar.', 409, ErrorCode::Conflict);
    }

    public static function cashSessionAlreadyOpen(string $register): self
    {
        return new self("La caja {$register} ya tiene un turno abierto.", 409, ErrorCode::Conflict);
    }

    public static function orderNotChargeable(Order $order): self
    {
        return new self("La comanda {$order->order_number} no admite cobros ({$order->status->label()}).", 409, ErrorCode::Conflict);
    }

    public static function nothingToCharge(): self
    {
        return new self('No hay saldo pendiente por cobrar.', 409, ErrorCode::Conflict);
    }

    public static function amountExceedsBalance(string $balance): self
    {
        return new self("El monto supera el saldo pendiente (S/ {$balance}).", 422, ErrorCode::BusinessRuleViolation);
    }

    public static function quantityExceedsUnpaid(string $name, string $unpaid): self
    {
        return new self("Solo quedan {$unpaid} por cobrar de \"{$name}\".", 422, ErrorCode::BusinessRuleViolation);
    }

    public static function itemsAfterAmountSplit(): self
    {
        return new self('La cuenta ya se dividió por montos: cobre el saldo por monto o completo en formato "Por consumo".', 409, ErrorCode::Conflict);
    }

    public static function paymentsMismatch(string $expected, string $received): self
    {
        return new self("Los pagos (S/ {$received}) no coinciden con el total a cobrar (S/ {$expected}).", 422, ErrorCode::BusinessRuleViolation);
    }

    public static function invalidSeries(string $series): self
    {
        return new self("La serie {$series} no está habilitada para este comprobante.", 422, ErrorCode::BusinessRuleViolation);
    }

    public static function invoiceRequiresRuc(): self
    {
        return new self('La factura exige el RUC y la razón social del cliente.', 422, ErrorCode::BusinessRuleViolation);
    }

    public static function receiptRequiresCustomer(): self
    {
        return new self('Las boletas mayores a S/ 700.00 deben identificar al cliente (DNI, CE o RUC).', 422, ErrorCode::BusinessRuleViolation);
    }

    public static function certificateMissing(): self
    {
        return new self('El certificado digital SUNAT no está configurado. No se emitió el comprobante.', 503, ErrorCode::ServiceUnavailable);
    }

    public static function signingFailed(): self
    {
        return new self('No se pudo firmar el comprobante electrónico. No se registró el cobro.', 503, ErrorCode::ServiceUnavailable);
    }

    public static function issuerAddressIncomplete(): self
    {
        return new self('El domicilio fiscal de la empresa está incompleto (ubigeo, departamento, provincia, distrito y dirección). No se emitió el comprobante.', 503, ErrorCode::ServiceUnavailable);
    }

    public static function creditNoteNotAllowed(Sale $sale): self
    {
        return new self("{$sale->document_type->label()} {$sale->documentNumber()} no admite nota de crédito.", 422, ErrorCode::BusinessRuleViolation);
    }

    public static function creditNoteReasonNotApplicable(CreditNoteReason $reason, Sale $sale): self
    {
        return new self("El motivo \"{$reason->label()}\" no aplica a {$sale->document_type->label()}.", 422, ErrorCode::BusinessRuleViolation);
    }

    public static function alreadyCredited(Sale $sale): self
    {
        return new self("El comprobante {$sale->documentNumber()} ya tiene una nota de crédito.", 409, ErrorCode::Conflict);
    }

    public static function rejectedBySunat(Sale $sale): self
    {
        return new self("SUNAT rechazó {$sale->documentNumber()}: no existe un comprobante válido que anular.", 409, ErrorCode::Conflict);
    }

    public static function creditNoteOnOpenOrder(Order $order): self
    {
        return new self("La comanda {$order->order_number} aún tiene saldo por cobrar: liquídela antes de emitir la nota de crédito.", 409, ErrorCode::Conflict);
    }

    public static function creditNoteSeriesMissing(string $prefix): self
    {
        return new self("No hay una serie de nota de crédito habilitada que empiece con {$prefix}.", 422, ErrorCode::BusinessRuleViolation);
    }

    public static function fileNotAvailable(string $file): self
    {
        return new self("El {$file} aún no está disponible para este comprobante.", 404, ErrorCode::NotFound);
    }
}
