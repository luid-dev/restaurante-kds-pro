<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChargeMode;
use App\Enums\ConsumptionFormat;
use App\Enums\CreditNoteReason;
use App\Enums\DocumentType;
use App\Enums\SunatStatus;
use App\Traits\AuditableModel;
use App\Traits\BelongsToCompany;
use App\Traits\HasPublicUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Comprobante emitido (factura, boleta, nota de crédito o nota de venta).
 *
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property int|null $order_id
 * @property int|null $cash_register_session_id
 * @property int|null $issued_by
 * @property int $document_series_id
 * @property int|null $related_sale_id
 * @property CreditNoteReason|null $credit_note_reason
 * @property string|null $credit_note_description
 * @property DocumentType $document_type
 * @property string $series
 * @property int $correlative
 * @property ConsumptionFormat $consumption_format
 * @property ChargeMode $charge_mode
 * @property string $customer_document_type
 * @property string|null $customer_document_number
 * @property string|null $customer_name
 * @property string $currency
 * @property numeric-string $igv_rate
 * @property numeric-string $taxable_amount
 * @property numeric-string $exempt_amount
 * @property numeric-string $unaffected_amount
 * @property numeric-string $igv_amount
 * @property numeric-string $service_charge
 * @property numeric-string $total_amount
 * @property SunatStatus $sunat_status
 * @property string|null $sunat_hash
 * @property string|null $sunat_response_code
 * @property string|null $sunat_xml_path
 * @property string|null $sunat_cdr_path
 * @property string|null $sunat_response_description
 * @property CarbonImmutable $issued_at
 * @property-read Sale|null $relatedSale
 * @property-read Sale|null $creditNote
 * @property-read Company $company
 */
#[Fillable([
    'order_id', 'cash_register_session_id', 'issued_by', 'document_series_id', 'related_sale_id', 'credit_note_reason',
    'credit_note_description', 'document_type',
    'series', 'correlative', 'consumption_format', 'charge_mode', 'customer_document_type', 'customer_document_number',
    'customer_name', 'currency', 'igv_rate', 'taxable_amount', 'exempt_amount', 'unaffected_amount', 'igv_amount', 'service_charge',
    'total_amount', 'sunat_status', 'sunat_hash', 'sunat_response_code', 'sunat_xml_path', 'sunat_cdr_path', 'sunat_response_description',
    'issued_at',
])]
#[Hidden(['id', 'company_id', 'order_id', 'cash_register_session_id', 'issued_by', 'document_series_id', 'related_sale_id', 'sunat_xml_path', 'sunat_cdr_path'])]
class Sale extends Model
{
    use AuditableModel, BelongsToCompany, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'consumption_format' => ConsumptionFormat::class,
            'charge_mode' => ChargeMode::class,
            'credit_note_reason' => CreditNoteReason::class,
            'sunat_status' => SunatStatus::class,
            'correlative' => 'integer',
            'igv_rate' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'exempt_amount' => 'decimal:2',
            'unaffected_amount' => 'decimal:2',
            'igv_amount' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'issued_at' => 'datetime',
        ];
    }

    /**
     * Valor de venta sin impuestos: gravado + exonerado + inafecto.
     *
     * @return numeric-string
     */
    public function saleValue(): string
    {
        return bcadd(bcadd($this->taxable_amount, $this->exempt_amount, 2), $this->unaffected_amount, 2);
    }

    /**
     * "B001-00000450".
     */
    public function documentNumber(): string
    {
        return sprintf('%s-%08d', $this->series, $this->correlative);
    }

    /**
     * Contenido del QR impreso (formato SUNAT):
     * RUC|TIPO|SERIE|NUMERO|IGV|TOTAL|FECHA|TIPO DOC ADQ|NUM DOC ADQ|HASH|
     */
    public function qrPayload(Company $company): string
    {
        return implode('|', [
            $company->ruc,
            $this->document_type->value,
            $this->series,
            (string) $this->correlative,
            $this->igv_amount,
            $this->total_amount,
            $this->issued_at->setTimezone($company->settings->timezone)->toDateString(),
            $this->customer_document_type,
            $this->customer_document_number ?? '-',
            $this->sunat_hash ?? '',
        ]).'|';
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<DocumentSeries, $this>
     */
    public function documentSeries(): BelongsTo
    {
        return $this->belongsTo(DocumentSeries::class);
    }

    /**
     * Comprobante que modifica una nota de crédito.
     *
     * @return BelongsTo<Sale, $this>
     */
    public function relatedSale(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_sale_id');
    }

    /**
     * Nota de crédito que anula este comprobante (como máximo una).
     *
     * @return HasOne<Sale, $this>
     */
    public function creditNote(): HasOne
    {
        return $this->hasOne(self::class, 'related_sale_id')->where('document_type', DocumentType::CreditNote);
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @return HasMany<SalePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return MorphMany<KardexMovement, $this>
     */
    public function kardexMovements(): MorphMany
    {
        return $this->morphMany(KardexMovement::class, 'source');
    }
}
