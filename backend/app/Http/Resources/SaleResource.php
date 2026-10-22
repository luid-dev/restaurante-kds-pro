<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Company;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Comprobante para el ticket térmico y el registro de ventas: emisor, adquiriente,
 * líneas con su afectación, pagos, hash, QR, documento relacionado y archivos.
 *
 * @mixin Sale
 */
final class SaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $company = $this->relationLoaded('company') ? $this->company : Company::query()->findOrFail($this->company_id);
        $order = $this->relationLoaded('order') ? $this->order : null;

        return [
            'id' => $this->uuid,
            'document_type' => $this->document_type->value,
            'document_label' => $this->document_type->label(),
            'document_number' => $this->documentNumber(),
            'consumption_format' => $this->consumption_format->value,
            'charge_mode' => $this->charge_mode->value,
            'issued_at' => $this->issued_at->toIso8601String(),
            'issuer' => [
                'ruc' => $company->ruc,
                'business_name' => $company->business_name,
                'trade_name' => $company->trade_name,
                'fiscal_address' => $company->fiscalAddressLine(),
            ],
            'customer' => [
                'document_type' => $this->customer_document_type,
                'document_number' => $this->customer_document_number,
                'name' => $this->customer_name,
            ],
            'order' => $order === null ? null : [
                'id' => $order->uuid,
                'order_number' => $order->order_number,
                'table_code' => $order->relationLoaded('diningTable') ? $order->diningTable?->code : null,
                'status' => $order->status->value,
                'balance' => bcsub($order->total_amount, $order->paid_amount, 2),
            ],
            'totals' => [
                'igv_rate' => $this->igv_rate,
                'taxable_amount' => $this->taxable_amount,
                'exempt_amount' => $this->exempt_amount,
                'unaffected_amount' => $this->unaffected_amount,
                'igv_amount' => $this->igv_amount,
                'service_charge' => $this->service_charge,
                'total_amount' => $this->total_amount,
            ],
            'items' => $this->whenLoaded('items', fn (): array => $this->items->map(static fn (SaleItem $item): array => [
                'description' => $item->description,
                'unit_code' => $item->unit_code,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'tax_affectation_type' => $item->tax_affectation_type->value,
                'line_total' => $item->line_total,
            ])->all()),
            'payments' => $this->whenLoaded('payments', fn (): array => $this->payments->map(static fn (SalePayment $payment): array => [
                'method' => $payment->method->value,
                'amount' => $payment->amount,
                'received_amount' => $payment->received_amount,
                'change_amount' => $payment->change_amount,
                'reference' => $payment->reference,
            ])->all()),
            'credit_note_reason' => $this->credit_note_reason === null ? null : [
                'code' => $this->credit_note_reason->value,
                'label' => $this->credit_note_reason->label(),
                'description' => $this->credit_note_description,
            ],
            'related_document' => $this->whenLoaded('relatedSale', fn (): ?array => $this->relatedSale === null ? null : [
                'id' => $this->relatedSale->uuid,
                'document_type' => $this->relatedSale->document_type->value,
                'document_number' => $this->relatedSale->documentNumber(),
            ]),
            'credit_note' => $this->whenLoaded('creditNote', fn (): ?array => $this->creditNote === null ? null : [
                'id' => $this->creditNote->uuid,
                'document_number' => $this->creditNote->documentNumber(),
            ]),
            'sunat' => [
                'status' => $this->sunat_status->value,
                'hash' => $this->sunat_hash,
                'response_code' => $this->sunat_response_code,
                'response_description' => $this->sunat_response_description,
                'qr_payload' => $this->document_type->isElectronic() ? $this->qrPayload($company) : null,
                'has_xml' => $this->sunat_xml_path !== null,
                'has_cdr' => $this->sunat_cdr_path !== null,
            ],
        ];
    }
}
