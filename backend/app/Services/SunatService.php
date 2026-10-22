<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Billing\ChargeBreakdown;
use App\DTOs\Billing\ChargeRequestData;
use App\DTOs\Billing\CustomerData;
use App\DTOs\Company\TaxSettingsData;
use App\Enums\ConsumptionFormat;
use App\Enums\DocumentType;
use App\Enums\OrderItemStatus;
use App\Enums\SunatStatus;
use App\Enums\TaxAffectationType;
use App\Exceptions\BillingException;
use App\Jobs\SendSaleToSunat;
use App\Models\CashRegisterSession;
use App\Models\Company;
use App\Models\DocumentSeries;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\Billing\AmountInWords;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company as GreenterCompany;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Sale\BaseSale;
use Greenter\Model\Sale\Charge;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Report\XmlUtils;
use Greenter\See;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Emisión de comprobantes (factura 01, boleta 03, nota de crédito 07 y nota de
 * venta 00) con Greenter (UBL 2.1).
 *
 *  - DETAILED: una línea por plato (y una de empaque), como en el ticket.
 *  - BY_CONSUMPTION: una línea "POR CONSUMO DE ALIMENTOS Y BEBIDAS" por cada
 *    afectación presente (gravado, exonerado, inafecto); el detalle queda en la
 *    precuenta interna.
 *
 * Cada línea conserva su afectación (catálogo 07): solo la gravada desglosa IGV
 * con la tasa de la empresa (10.50% MYPE o 18.00%). Los montos se calculan con
 * BCMath y solo se convierten a float en el borde de Greenter, ya redondeados.
 */
final readonly class SunatService
{
    private const string PACKAGING_DESCRIPTION = 'SERVICIO DE EMPAQUE PARA LLEVAR';

    /** Catálogo 53: recargo al consumo (no forma parte de la base del IGV). */
    private const string SERVICE_CHARGE_CODE = '46';

    /** Domicilio fiscal declarado en el RUC (código de establecimiento anexo). */
    private const string FISCAL_ESTABLISHMENT_CODE = '0000';

    /**
     * Reserva el correlativo y registra el comprobante con sus líneas. Para
     * comprobantes electrónicos firma el XML (sin certificado no hay cobro).
     */
    public function issue(Order $order, ChargeRequestData $request, ChargeBreakdown $charge, CashRegisterSession $session, User $cashier): Sale
    {
        $series = DocumentSeries::query()
            ->where('series', $request->series)
            ->where('is_active', true)
            ->whereIn('document_type', [DocumentType::Invoice, DocumentType::Receipt, DocumentType::SalesNote])
            ->lockForUpdate()
            ->first() ?? throw BillingException::invalidSeries($request->series);

        $this->assertCustomer($series->document_type, $request->customer, $charge->total);

        $company = Company::query()->findOrFail($order->company_id);

        if ($series->document_type->isElectronic()) {
            $this->assertIssuerAddress($company);
        }

        $tax = $company->settings->tax;
        $lines = $request->format === ConsumptionFormat::Detailed
            ? $this->detailedLines($charge)
            : $this->consumptionLines($order, $charge, $tax);
        $lines = array_map(fn (array $line): array => $this->withTax($line, $tax), $lines);

        $correlative = $series->next_correlative;
        $series->update(['next_correlative' => $correlative + 1]);
        $customer = $request->customer;

        $sale = Sale::query()->create([
            'order_id' => $order->id,
            'cash_register_session_id' => $session->id,
            'issued_by' => $cashier->id,
            'document_series_id' => $series->id,
            'document_type' => $series->document_type,
            'series' => $series->series,
            'correlative' => $correlative,
            'consumption_format' => $request->format,
            'charge_mode' => $charge->mode,
            'customer_document_type' => $customer->documentType,
            'customer_document_number' => $customer->documentNumber,
            'customer_name' => $customer->name,
            'currency' => $company->settings->currency,
            'igv_rate' => $tax->igvRate,
            'taxable_amount' => $this->sum($lines, 'value', TaxAffectationType::Taxed),
            'exempt_amount' => $this->sum($lines, 'value', TaxAffectationType::Exempt),
            'unaffected_amount' => $this->sum($lines, 'value', TaxAffectationType::Unaffected),
            'igv_amount' => $this->sum($lines, 'igv'),
            'service_charge' => $charge->serviceCharge,
            'total_amount' => $charge->total,
            'sunat_status' => $series->document_type->isElectronic() ? SunatStatus::Pending : SunatStatus::NotApplicable,
            'issued_at' => CarbonImmutable::now(),
        ]);

        foreach ($lines as $line) {
            SaleItem::query()->create([
                'sale_id' => $sale->id,
                'order_item_id' => $line['order_item_id'],
                'description' => $line['description'],
                'unit_code' => $line['unit_code'],
                'quantity' => $line['quantity'],
                'unit_value' => $line['unit_value'],
                'unit_price' => $line['unit_price'],
                'tax_affectation_type' => $line['affectation'],
                'igv_amount' => $line['igv'],
                'line_total' => $line['gross'],
            ]);
        }

        if ($series->document_type->isElectronic()) {
            $this->sign($sale->load('items'), $company);
        }

        return $sale;
    }

    /**
     * Encola el envío a SUNAT (solo comprobantes electrónicos y si está habilitado).
     */
    public function queueDelivery(Sale $sale): void
    {
        if ($sale->document_type->isElectronic() && config()->boolean('sunat.send_enabled')) {
            SendSaleToSunat::dispatch($sale->company_id, $sale->id);
        }
    }

    /**
     * Envía el XML firmado y registra el CDR. Un error de comunicación lanza
     * excepción para que el job reintente; un rechazo queda registrado.
     */
    public function deliver(Sale $sale): void
    {
        if ($sale->sunat_status !== SunatStatus::Pending || $sale->sunat_xml_path === null) {
            return;
        }

        $xml = Storage::disk($this->disk())->get($sale->sunat_xml_path) ?? throw new RuntimeException("XML no encontrado: {$sale->documentNumber()}");
        $company = Company::query()->findOrFail($sale->company_id);

        $see = $this->see();
        $see->setService(config()->string('sunat.endpoint'));
        $see->setClaveSOL($company->ruc, config()->string('sunat.sol_user'), config()->string('sunat.sol_password'));

        $result = $see->sendXmlFile($xml);

        if (! $result instanceof BillResult || ! $result->isSuccess()) {
            $error = $result instanceof BillResult ? $result->getError() : null;
            $code = (int) ($error?->getCode() ?? 0);

            // 2000-3999: rechazo definitivo. Otros (0100-1999, SOAP): reintentar.
            if ($error !== null && $code >= 2000 && $code < 4000) {
                $sale->update(['sunat_status' => SunatStatus::Rejected, 'sunat_response_code' => (string) $code, 'sunat_response_description' => mb_substr((string) $error->getMessage(), 0, 255)]);

                return;
            }

            throw new RuntimeException("SUNAT no respondió para {$sale->documentNumber()}: código {$code}.");
        }

        $cdr = $result->getCdrResponse();
        $code = (int) ($cdr?->getCode() ?? -1);
        $status = match (true) {
            $code === 0 && ($cdr?->getNotes() ?? []) === [] => SunatStatus::Accepted,
            $code === 0, $code >= 4000 => SunatStatus::Observed,
            default => SunatStatus::Rejected,
        };

        $cdrZip = $result->getCdrZip();
        $cdrPath = null;

        if ($cdrZip !== null && $cdrZip !== '') {
            $cdrPath = sprintf('sunat/%s/R-%s.zip', $company->ruc, $this->fileName($sale, $company));
            Storage::disk($this->disk())->put($cdrPath, $cdrZip);
        }

        $sale->update([
            'sunat_status' => $status,
            'sunat_response_code' => (string) $code,
            'sunat_response_description' => mb_substr((string) $cdr?->getDescription(), 0, 255),
            'sunat_cdr_path' => $cdrPath,
        ]);
    }

    /**
     * Modelo Greenter del comprobante (UBL 2.1): Invoice para factura y boleta,
     * Note para la nota de crédito (requiere relatedSale cargado). Público para
     * inspección y pruebas.
     */
    public function buildDocument(Sale $sale, Company $company): Invoice|Note
    {
        if ($sale->document_type === DocumentType::CreditNote) {
            $related = $sale->relatedSale ?? throw new RuntimeException("La nota {$sale->documentNumber()} no tiene comprobante de referencia.");

            $note = (new Note)
                ->setCodMotivo($sale->credit_note_reason?->value)
                ->setDesMotivo($sale->credit_note_description)
                ->setTipDocAfectado($related->document_type->value)
                ->setNumDocfectado($related->series.'-'.$related->correlative)
                ->setValorVenta((float) $sale->saleValue())
                ->setSubTotal((float) Decimal::add($sale->saleValue(), $sale->igv_amount, 2));

            $this->fill($note, $sale, $company);

            if (bccomp($sale->service_charge, '0', 2) > 0) {
                $note->setSumOtrosCargos((float) $sale->service_charge);
            }

            return $note;
        }

        $invoice = (new Invoice)
            ->setTipoOperacion('0101')
            ->setFormaPago(new FormaPagoContado)
            ->setValorVenta((float) $sale->saleValue())
            ->setSubTotal((float) Decimal::add($sale->saleValue(), $sale->igv_amount, 2));

        $this->fill($invoice, $sale, $company);

        if (bccomp($sale->service_charge, '0', 2) > 0 && bccomp($sale->saleValue(), '0', 2) > 0) {
            $invoice
                ->setCargos([(new Charge)
                    ->setCodTipo(self::SERVICE_CHARGE_CODE)
                    ->setFactor((float) Decimal::div($sale->service_charge, $sale->saleValue(), 5))
                    ->setMonto((float) $sale->service_charge)
                    ->setMontoBase((float) $sale->saleValue())])
                ->setSumOtrosCargos((float) $sale->service_charge);
        }

        return $invoice;
    }

    /**
     * Firma el XML, guarda el archivo y el hash (DigestValue) que va en el ticket.
     * Requiere items (y relatedSale en notas de crédito) cargados.
     */
    public function sign(Sale $sale, Company $company): void
    {
        try {
            $xml = $this->see()->getXmlSigned($this->buildDocument($sale, $company));
        } catch (BillingException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw BillingException::signingFailed();
        }

        if ($xml === null || $xml === '') {
            throw BillingException::signingFailed();
        }

        $path = sprintf('sunat/%s/%s.xml', $company->ruc, $this->fileName($sale, $company));
        Storage::disk($this->disk())->put($path, $xml);

        $sale->update([
            'sunat_hash' => (new XmlUtils)->getHashSign($xml),
            'sunat_xml_path' => $path,
        ]);
    }

    /**
     * El XML UBL 2.1 se rechaza sin domicilio fiscal completo: se valida antes de
     * reservar el correlativo para no dejar huecos en la serie.
     */
    public function assertIssuerAddress(Company $company): void
    {
        if (! $company->hasCompleteFiscalAddress()) {
            throw BillingException::issuerAddressIncomplete();
        }
    }

    public function disk(): string
    {
        return config()->string('sunat.xml_disk');
    }

    /**
     * Cabecera, totales por afectación, líneas y leyenda comunes a factura, boleta y nota.
     */
    private function fill(BaseSale $document, Sale $sale, Company $company): void
    {
        $document
            ->setUblVersion('2.1')
            ->setTipoDoc($sale->document_type->value)
            ->setSerie($sale->series)
            ->setCorrelativo((string) $sale->correlative)
            ->setFechaEmision(new DateTimeImmutable($sale->issued_at->toIso8601String())->setTimezone(new DateTimeZone($company->settings->timezone)))
            ->setTipoMoneda($sale->currency)
            ->setCompany($this->issuer($company))
            ->setClient($this->client($sale))
            ->setMtoOperGravadas((float) $sale->taxable_amount)
            ->setMtoOperExoneradas((float) $sale->exempt_amount)
            ->setMtoOperInafectas((float) $sale->unaffected_amount)
            ->setMtoIGV((float) $sale->igv_amount)
            ->setTotalImpuestos((float) $sale->igv_amount)
            ->setMtoImpVenta((float) $sale->total_amount)
            ->setDetails($this->details($sale))
            ->setLegends([(new Legend)->setCode('1000')->setValue(AmountInWords::soles($sale->total_amount))]);
    }

    /**
     * @return list<SaleDetail>
     */
    private function details(Sale $sale): array
    {
        $details = [];

        foreach ($sale->items as $index => $item) {
            $value = Decimal::sub($item->line_total, $item->igv_amount, 2);
            $taxed = $item->tax_affectation_type->isTaxed();

            $details[] = (new SaleDetail)
                ->setCodProducto(($item->order_item_id === null ? 'CONS-' : 'ITEM-').str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT))
                ->setUnidad($item->unit_code)
                ->setCantidad((float) $item->quantity)
                ->setDescripcion($item->description)
                ->setMtoValorUnitario((float) $item->unit_value)
                ->setMtoBaseIgv((float) $value)
                ->setPorcentajeIgv($taxed ? (float) $sale->igv_rate : 0.0)
                ->setIgv((float) $item->igv_amount)
                ->setTipAfeIgv($item->tax_affectation_type->value)
                ->setTotalImpuestos((float) $item->igv_amount)
                ->setMtoValorVenta((float) $value)
                ->setMtoPrecioUnitario((float) $item->unit_price);
        }

        return $details;
    }

    private function fileName(Sale $sale, Company $company): string
    {
        return sprintf('%s-%s-%s-%d', $company->ruc, $sale->document_type->value, $sale->series, $sale->correlative);
    }

    private function see(): See
    {
        $path = config()->string('sunat.certificate_path');
        $certificate = is_file($path) ? file_get_contents($path) : false;

        if ($certificate === false || $certificate === '') {
            throw BillingException::certificateMissing();
        }

        $see = new See;
        $see->setCertificate($certificate);
        $see->setCachePath(null);

        return $see;
    }

    private function issuer(Company $company): GreenterCompany
    {
        $this->assertIssuerAddress($company);

        return (new GreenterCompany)
            ->setRuc($company->ruc)
            ->setRazonSocial($company->business_name)
            ->setNombreComercial($company->trade_name ?? $company->business_name)
            ->setEmail($company->email)
            ->setTelephone($company->phone)
            ->setAddress((new Address)
                ->setUbigueo($company->ubigeo)
                ->setCodigoPais('PE')
                ->setDepartamento(mb_strtoupper((string) $company->department))
                ->setProvincia(mb_strtoupper((string) $company->province))
                ->setDistrito(mb_strtoupper((string) $company->district))
                ->setUrbanizacion($company->urbanization === null ? null : mb_strtoupper($company->urbanization))
                ->setDireccion(mb_strtoupper((string) $company->fiscal_address))
                ->setCodLocal(self::FISCAL_ESTABLISHMENT_CODE));
    }

    private function client(Sale $sale): Client
    {
        return (new Client)
            ->setTipoDoc($sale->customer_document_type)
            ->setNumDoc($sale->customer_document_number ?? '-')
            ->setRznSocial($sale->customer_name ?? CustomerData::ANONYMOUS_NAME);
    }

    /**
     * @param  numeric-string  $total
     */
    private function assertCustomer(DocumentType $type, CustomerData $customer, string $total): void
    {
        if ($type === DocumentType::Invoice && $customer->documentType !== '6') {
            throw BillingException::invoiceRequiresRuc();
        }

        if ($type === DocumentType::Receipt && bccomp($total, '700.00', 2) > 0 && ! $customer->isIdentified()) {
            throw BillingException::receiptRequiresCustomer();
        }
    }

    /**
     * Una línea por plato cobrado (con su afectación) y una de empaque gravada.
     * El cobro que liquida la cuenta ajusta la última línea a los céntimos exactos.
     *
     * @return list<array{order_item_id: int|null, description: string, unit_code: string, quantity: numeric-string, gross: numeric-string, affectation: TaxAffectationType}>
     */
    private function detailedLines(ChargeBreakdown $charge): array
    {
        $lines = [];
        $packaging = '0.00';

        foreach ($charge->lines as $line) {
            $item = $line['item'];
            $modifiers = $item->modifiers_json === [] ? '' : ' ('.implode(', ', $item->modifiers_json).')';

            $lines[] = [
                'order_item_id' => $item->id,
                'description' => mb_substr(mb_strtoupper($item->menuItem->name.$modifiers), 0, 250),
                'unit_code' => 'NIU',
                'quantity' => $line['quantity'],
                'gross' => $line['gross'],
                'affectation' => $item->tax_affectation_type,
            ];
            $packaging = Decimal::add($packaging, $line['packaging'], 2);
        }

        if (bccomp($packaging, '0', 2) > 0) {
            $lines[] = ['order_item_id' => null, 'description' => self::PACKAGING_DESCRIPTION, 'unit_code' => 'ZZ', 'quantity' => '1.00', 'gross' => $packaging, 'affectation' => TaxAffectationType::Taxed];
        }

        $difference = Decimal::sub($charge->gross, $this->sum($lines, 'gross'), 2);
        $last = array_key_last($lines);

        if ($last !== null && bccomp($difference, '0', 2) !== 0) {
            $lines[$last]['gross'] = Decimal::add($lines[$last]['gross'], $difference, 2);
        }

        return $lines;
    }

    /**
     * Glosa "Por consumo": una línea por afectación. Con platos elegidos el importe
     * de cada afectación es exacto; en un cobro por monto se prorratea según la
     * composición de la comanda y la última afectación toma el residuo.
     *
     * @return list<array{order_item_id: null, description: string, unit_code: string, quantity: numeric-string, gross: numeric-string, affectation: TaxAffectationType}>
     */
    private function consumptionLines(Order $order, ChargeBreakdown $charge, TaxSettingsData $tax): array
    {
        $weights = $charge->lines === [] ? $this->orderComposition($order, $tax) : $this->chargedComposition($charge);
        $weights = array_values(array_filter($weights, static fn (array $weight): bool => bccomp($weight['amount'], '0', 2) > 0));

        if (count($weights) <= 1) {
            return [$this->consumptionLine($weights[0]['affectation'] ?? TaxAffectationType::Taxed, $charge->gross)];
        }

        $total = array_reduce($weights, static fn (string $sum, array $weight): string => Decimal::add($sum, $weight['amount'], 2), '0.00');
        $exact = bccomp($total, $charge->gross, 2) === 0;
        $allocated = '0.00';
        $lines = [];
        $last = array_key_last($weights);

        foreach ($weights as $index => $weight) {
            $gross = match (true) {
                $index === $last => Decimal::sub($charge->gross, $allocated, 2),
                $exact => $weight['amount'],
                default => Decimal::mul($charge->gross, Decimal::div($weight['amount'], $total, 10), 2),
            };
            $allocated = Decimal::add($allocated, $gross, 2);

            if (bccomp($gross, '0', 2) > 0) {
                $lines[] = $this->consumptionLine($weight['affectation'], $gross);
            }
        }

        return $lines;
    }

    /**
     * Importe cobrado por afectación (el empaque es un servicio gravado).
     *
     * @return list<array{affectation: TaxAffectationType, amount: numeric-string}>
     */
    private function chargedComposition(ChargeBreakdown $charge): array
    {
        $amounts = [];

        foreach ($charge->lines as $line) {
            $code = $line['item']->tax_affectation_type->value;
            $amounts[$code] = Decimal::add($amounts[$code] ?? '0', $line['gross'], 2);
            $amounts[TaxAffectationType::Taxed->value] = Decimal::add($amounts[TaxAffectationType::Taxed->value] ?? '0', $line['packaging'], 2);
        }

        return $this->byAffectation($amounts);
    }

    /**
     * Importe final (IGV incluido) de los platos vigentes por afectación.
     *
     * @return list<array{affectation: TaxAffectationType, amount: numeric-string}>
     */
    private function orderComposition(Order $order, TaxSettingsData $tax): array
    {
        $factor = Decimal::add('1', Decimal::div($tax->igvRate, '100', 6), 6);
        $items = OrderItem::query()->with('menuItem')->where('order_id', $order->id)->where('status', '!=', OrderItemStatus::Voided)->get();
        $amounts = [];

        foreach ($items as $item) {
            $amount = Decimal::mul($item->unit_price, $item->quantity, 4);
            $packaging = $item->usesPackaging($order->order_type) ? Decimal::mul($item->menuItem->packaging_surcharge, $item->quantity, 4) : '0';

            if (! $tax->pricesIncludeTax) {
                $amount = $item->tax_affectation_type->isTaxed() ? Decimal::mul($amount, $factor, 4) : $amount;
                $packaging = Decimal::mul($packaging, $factor, 4);
            }

            $code = $item->tax_affectation_type->value;
            $amounts[$code] = Decimal::add($amounts[$code] ?? '0', $amount, 4);
            $amounts[TaxAffectationType::Taxed->value] = Decimal::add($amounts[TaxAffectationType::Taxed->value] ?? '0', $packaging, 4);
        }

        return $this->byAffectation($amounts);
    }

    /**
     * Ordena por catálogo (gravado, exonerado, inafecto) y redondea a céntimos.
     *
     * @param  array<array-key, numeric-string>  $amounts  indexado por código de afectación
     * @return list<array{affectation: TaxAffectationType, amount: numeric-string}>
     */
    private function byAffectation(array $amounts): array
    {
        $weights = [];

        foreach (TaxAffectationType::cases() as $case) {
            if (array_key_exists($case->value, $amounts)) {
                $weights[] = ['affectation' => $case, 'amount' => Decimal::round($amounts[$case->value], 2)];
            }
        }

        return $weights;
    }

    /**
     * @param  numeric-string  $gross
     * @return array{order_item_id: null, description: string, unit_code: string, quantity: numeric-string, gross: numeric-string, affectation: TaxAffectationType}
     */
    private function consumptionLine(TaxAffectationType $affectation, string $gross): array
    {
        return [
            'order_item_id' => null,
            'description' => $affectation->consumptionGloss(),
            'unit_code' => 'ZZ',
            'quantity' => '1.00',
            'gross' => $gross,
            'affectation' => $affectation,
        ];
    }

    /**
     * Desglosa el IGV de una línea con precio final (tasa total IGV + IPM). Las
     * líneas exoneradas e inafectas no llevan impuesto: valor de venta = importe.
     *
     * @param  array{order_item_id: int|null, description: string, unit_code: string, quantity: numeric-string, gross: numeric-string, affectation: TaxAffectationType}  $line
     * @return array{order_item_id: int|null, description: string, unit_code: string, quantity: numeric-string, gross: numeric-string, affectation: TaxAffectationType, value: numeric-string, igv: numeric-string, unit_price: numeric-string, unit_value: numeric-string}
     */
    private function withTax(array $line, TaxSettingsData $tax): array
    {
        $value = $line['affectation']->isTaxed()
            ? Decimal::div($line['gross'], Decimal::add('1', Decimal::div($tax->igvRate, '100', 6), 6), 2)
            : $line['gross'];

        return [
            ...$line,
            'value' => $value,
            'igv' => Decimal::sub($line['gross'], $value, 2),
            'unit_price' => Decimal::div($line['gross'], $line['quantity'], 2),
            'unit_value' => Decimal::div($value, $line['quantity'], 4),
        ];
    }

    /**
     * Suma una columna de las líneas; con $affectation solo las de esa afectación.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return numeric-string
     */
    private function sum(array $lines, string $key, ?TaxAffectationType $affectation = null): string
    {
        $total = '0.00';

        foreach ($lines as $line) {
            if ($affectation !== null && $line['affectation'] !== $affectation) {
                continue;
            }

            /** @var numeric-string $amount */
            $amount = $line[$key];
            $total = Decimal::add($total, $amount, 2);
        }

        return $total;
    }
}
