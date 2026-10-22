<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Reports\ReportPeriod;
use App\Enums\DocumentType;
use App\Enums\KardexMovementType;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\SunatStatus;
use App\Enums\TaxAffectationType;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\KardexMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Recipe;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * Reportes gerenciales y contables con BCMath sobre agregados de PostgreSQL.
 *
 *  - Rentabilidad: venta bruta y neta (descontadas las notas de crédito), food
 *    cost teórico desde el kardex, mermas tipo 13, ticket promedio, canales,
 *    medios de pago y margen de contribución por plato.
 *  - SIRE: archivo de reemplazo de la propuesta del RVIE (anexo 3, 40 campos).
 *  - Kardex valorizado formato 13.1 y auditoría de mermas por anulación.
 */
final readonly class ReportService
{
    public const string FOOD_COST_TARGET = '32.00';

    /** Identificador del libro RVIE en el nombre del archivo SIRE. */
    private const string SIRE_BOOK_CODE = '140400';

    /** Tabla 6 SUNAT: unidad de medida del kardex. */
    private const array SUNAT_UNIT_CODES = ['KGM' => '01', 'NIU' => '07', 'LTR' => '08'];

    /** Tabla 5 SUNAT: insumos de cocina como materias primas y auxiliares. */
    private const string STOCK_TYPE_RAW_MATERIALS = '03';

    public function __construct(private RecipeKardexService $kardex) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboardMetrics(Company $company, ReportPeriod $period): array
    {
        $documents = Sale::query()->toBase()
            ->where('issued_at', '>=', $period->start)
            ->where('issued_at', '<', $period->end)
            ->where('sunat_status', '!=', SunatStatus::Rejected->value)
            ->groupBy('document_type')
            ->selectRaw('document_type, count(*) as documents, coalesce(sum(total_amount), 0) as total, coalesce(sum(taxable_amount + exempt_amount + unaffected_amount), 0) as net, coalesce(sum(igv_amount), 0) as igv, coalesce(sum(service_charge), 0) as service')
            ->get();

        $gross = $net = $igv = $service = $credited = '0.00';
        $documentCount = 0;

        foreach ($documents as $row) {
            $sign = $this->string($row, 'document_type') === DocumentType::CreditNote->value ? '-1' : '1';
            $gross = Decimal::add($gross, Decimal::mul($sign, $this->decimal($row, 'total'), 2), 2);
            $net = Decimal::add($net, Decimal::mul($sign, $this->decimal($row, 'net'), 2), 2);
            $igv = Decimal::add($igv, Decimal::mul($sign, $this->decimal($row, 'igv'), 2), 2);
            $service = Decimal::add($service, Decimal::mul($sign, $this->decimal($row, 'service'), 2), 2);

            if ($sign === '-1') {
                $credited = Decimal::add($credited, $this->decimal($row, 'total'), 2);
            } else {
                $documentCount += $this->integer($row, 'documents');
            }
        }

        $costs = $this->kardexCosts($period);
        $foodCost = Decimal::round(Decimal::sub($costs['consumed'], $costs['reversed'], 4), 2);
        $orders = $this->billedOrders($period);

        return [
            'period' => $period->toArray(),
            'gross_sales' => $gross,
            'net_sales' => $net,
            'igv_total' => $igv,
            'service_charge_total' => $service,
            'credit_notes_total' => $credited,
            'theoretical_food_cost' => $foodCost,
            'food_cost_percentage' => bccomp($net, '0', 2) > 0 ? Decimal::mul(Decimal::div($foodCost, $net, 10), '100', 2) : null,
            'food_cost_target' => self::FOOD_COST_TARGET,
            'waste_cost' => Decimal::round($costs['waste'], 2),
            'average_ticket' => $orders > 0 ? Decimal::div($gross, (string) $orders, 2) : '0.00',
            'orders_count' => $orders,
            'documents_count' => $documentCount,
            'top_dishes' => $this->topDishes($company, $period),
            'sales_by_channel' => $this->salesByChannel($period, $gross),
            'sales_by_payment_method' => $this->salesByPaymentMethod($period),
        ];
    }

    /**
     * Archivo plano de reemplazo de la propuesta del RVIE (SIRE): un comprobante
     * electrónico por línea, 40 campos separados por "|". Las notas de crédito van
     * en negativo con la referencia al comprobante que modifican (campos 29-32).
     *
     * @return array{filename: string, content: string}
     */
    public function salesSire(Company $company, string $taxPeriod): array
    {
        $period = ReportPeriod::fromTaxPeriod($taxPeriod, $company->settings->timezone);
        $sales = Sale::query()
            ->with('relatedSale')
            ->whereIn('document_type', [DocumentType::Invoice, DocumentType::Receipt, DocumentType::CreditNote])
            ->where('sunat_status', '!=', SunatStatus::Rejected)
            ->where('issued_at', '>=', $period->start)
            ->where('issued_at', '<', $period->end)
            ->orderBy('issued_at')
            ->orderBy('id')
            ->get();

        $lines = [];

        foreach ($sales as $sale) {
            $sign = $sale->document_type === DocumentType::CreditNote ? '-1' : '1';
            $amount = fn (string $value): string => $this->signed($sign, $value);
            $related = $sale->relatedSale;

            $fields = [
                $company->ruc,                                                   // 1 RUC del generador
                $company->business_name,                                         // 2 Razón social
                $taxPeriod,                                                      // 3 Periodo AAAAMM
                '',                                                              // 4 CAR SUNAT (lo asigna SUNAT)
                $this->localDate($sale->issued_at->toIso8601String(), $company), // 5 Fecha de emisión
                '',                                                              // 6 Fecha de vencimiento
                $sale->document_type->value,                                     // 7 Tipo de comprobante
                $sale->series,                                                   // 8 Serie
                (string) $sale->correlative,                                     // 9 Número
                '',                                                              // 10 Número final (consolidado)
                $sale->customer_document_number === null ? '' : $sale->customer_document_type, // 11 Tipo doc. identidad
                $sale->customer_document_number ?? '',                           // 12 Número doc. identidad
                $sale->customer_document_number === null ? '' : (string) $sale->customer_name, // 13 Razón social del cliente
                '0.00',                                                          // 14 Valor facturado exportación
                $amount($sale->taxable_amount),                                  // 15 Base imponible gravada
                '0.00',                                                          // 16 Descuento base imponible
                $amount($sale->igv_amount),                                      // 17 IGV / IPM
                '0.00',                                                          // 18 Descuento IGV / IPM
                $amount($sale->exempt_amount),                                   // 19 Monto exonerado
                $amount($sale->unaffected_amount),                               // 20 Monto inafecto
                '0.00',                                                          // 21 ISC
                '0.00',                                                          // 22 Base imponible IVAP
                '0.00',                                                          // 23 IVAP
                '0.00',                                                          // 24 ICBPER
                $amount($sale->service_charge),                                  // 25 Otros tributos y cargos (recargo al consumo)
                $amount($sale->total_amount),                                    // 26 Importe total
                $sale->currency,                                                 // 27 Moneda
                '1.000',                                                         // 28 Tipo de cambio
                $related === null ? '' : $this->localDate($related->issued_at->toIso8601String(), $company), // 29 Fecha doc. modificado
                $related === null ? '' : $related->document_type->value,         // 30 Tipo doc. modificado
                $related === null ? '' : $related->series,                       // 31 Serie doc. modificado
                $related === null ? '' : (string) $related->correlative,         // 32 Número doc. modificado
                '', '', '', '', '', '', '', '',                                  // 33-40 completados por SUNAT
            ];

            $lines[] = implode('|', array_map(static fn (string $field): string => str_replace(['|', "\r", "\n"], ' ', $field), $fields)).'|';
        }

        // LE + RUC + AAAAMM + 00 + libro 140400 + oportunidad 02 (reemplazo) + operativo + con/sin información + soles + nuevo sistema + correlativo.
        $filename = sprintf('LE%s%s00%s021%s1201.txt', $company->ruc, $taxPeriod, self::SIRE_BOOK_CODE, $lines === [] ? '0' : '1');

        return ['filename' => $filename, 'content' => $lines === [] ? '' : implode("\r\n", $lines)."\r\n"];
    }

    /**
     * Registro del inventario permanente valorizado - formato 13.1 (promedio ponderado),
     * en CSV: cabecera del insumo repetida en cada fila para filtrar en una hoja de cálculo.
     *
     * @return array{filename: string, content: string}
     */
    public function kardexFormat131(Company $company, ReportPeriod $period, ?Ingredient $ingredient = null): array
    {
        $movements = KardexMovement::query()
            ->with(['ingredient', 'warehouse', 'source'])
            ->when($ingredient !== null, static fn ($query) => $query->where('ingredient_id', $ingredient?->id))
            ->where('occurred_at', '>=', $period->start)
            ->where('occurred_at', '<', $period->end)
            ->orderBy('ingredient_id')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $rows = [[
            'PERIODO', 'RUC', 'RAZON SOCIAL', 'ESTABLECIMIENTO', 'CODIGO EXISTENCIA', 'TIPO EXISTENCIA (TABLA 5)', 'DESCRIPCION',
            'UNIDAD DE MEDIDA (TABLA 6)', 'METODO DE VALUACION', 'FECHA', 'TIPO DOC. (TABLA 10)', 'SERIE', 'NUMERO', 'TIPO OPERACION (TABLA 12)',
            'ENTRADA CANTIDAD', 'ENTRADA COSTO UNITARIO', 'ENTRADA COSTO TOTAL', 'SALIDA CANTIDAD', 'SALIDA COSTO UNITARIO', 'SALIDA COSTO TOTAL',
            'SALDO CANTIDAD', 'SALDO COSTO UNITARIO', 'SALDO COSTO TOTAL', 'GLOSA',
        ]];

        foreach ($movements as $movement) {
            [$documentType, $series, $number] = $this->kardexDocument($movement);
            $inbound = $movement->movement_type->isInbound();

            $rows[] = [
                $movement->occurred_at->setTimezone($company->settings->timezone)->format('Ym').'00',
                $company->ruc,
                $company->business_name,
                $movement->warehouse->code.' '.$movement->warehouse->name,
                $movement->ingredient->sku,
                self::STOCK_TYPE_RAW_MATERIALS,
                $movement->ingredient->name,
                self::SUNAT_UNIT_CODES[$movement->ingredient->unit_code->value],
                'PROMEDIO PONDERADO',
                $this->localDate($movement->occurred_at->toIso8601String(), $company),
                $documentType,
                $series,
                $number,
                $movement->sunat_operation_type,
                $inbound ? $movement->quantity_in : '0.0000',
                $inbound ? $movement->unit_cost : '0.0000',
                $inbound ? $movement->total_cost : '0.0000',
                $inbound ? '0.0000' : $movement->quantity_out,
                $inbound ? '0.0000' : $movement->unit_cost,
                $inbound ? '0.0000' : $movement->total_cost,
                $movement->balance_quantity,
                $movement->balance_unit_cost,
                $movement->balance_total_cost,
                (string) $movement->notes,
            ];
        }

        $suffix = $ingredient === null ? 'GENERAL' : $ingredient->sku;

        return [
            'filename' => sprintf('KARDEX-13.1-%s-%s-%s_%s.csv', $company->ruc, $suffix, $period->from->format('Ymd'), $period->to->format('Ymd')),
            'content' => $this->csv($rows),
        ];
    }

    /**
     * Mermas valorizadas (tipo SUNAT 13) con el plato, el insumo perdido, quién
     * autorizó la anulación y el motivo.
     *
     * @return array{rows: list<array<string, mixed>>, total_cost: numeric-string, incidents: int}
     */
    public function wasteAudit(ReportPeriod $period): array
    {
        $movements = KardexMovement::query()
            ->with([
                'ingredient',
                'creator',
                'source' => static fn (MorphTo $morph) => $morph->morphWith([OrderItem::class => ['menuItem']]),
            ])
            ->where('movement_type', KardexMovementType::Waste)
            ->where('occurred_at', '>=', $period->start)
            ->where('occurred_at', '<', $period->end)
            ->orderByDesc('occurred_at')
            ->orderBy('id')
            ->get();

        $rows = [];
        $total = '0.00';
        $sources = [];

        foreach ($movements as $movement) {
            $source = $movement->source;
            [$origin, $dish, $reason] = match (true) {
                $source instanceof OrderItem => ['VOID', $source->menuItem->name.' x '.$source->quantity, $source->void_reason],
                $source instanceof Sale => ['CREDIT_NOTE', 'Nota de crédito '.$source->documentNumber(), $source->credit_note_description],
                default => ['VOID', null, $movement->notes],
            };

            $sources[$movement->source_type.'#'.$movement->source_id] = true;
            $total = Decimal::add($total, $movement->total_cost, 4);

            $rows[] = [
                'id' => $movement->uuid,
                'occurred_at' => $movement->occurred_at->toIso8601String(),
                'origin' => $origin,
                'reference' => $movement->document_reference,
                'dish' => $dish,
                'ingredient' => [
                    'sku' => $movement->ingredient->sku,
                    'name' => $movement->ingredient->name,
                    'unit_code' => $movement->ingredient->unit_code->value,
                ],
                'quantity' => $movement->quantity_out,
                'unit_cost' => $movement->unit_cost,
                'total_cost' => Decimal::round($movement->total_cost, 2),
                'authorized_by' => $movement->creator?->name,
                'reason' => $reason,
            ];
        }

        return ['rows' => $rows, 'total_cost' => Decimal::round($total, 2), 'incidents' => count($sources)];
    }

    /**
     * @param  array{rows: list<array<string, mixed>>, total_cost: numeric-string, incidents: int}  $audit
     */
    public function wasteAuditCsv(Company $company, array $audit): string
    {
        $rows = [['FECHA', 'ORIGEN', 'REFERENCIA', 'PLATO', 'SKU INSUMO', 'INSUMO', 'UNIDAD', 'CANTIDAD PERDIDA', 'COSTO UNITARIO', 'COSTO TOTAL S/', 'AUTORIZADO POR', 'MOTIVO']];

        foreach ($audit['rows'] as $row) {
            /** @var array{occurred_at: string, origin: string, reference: string|null, dish: string|null, ingredient: array{sku: string, name: string, unit_code: string}, quantity: string, unit_cost: string, total_cost: string, authorized_by: string|null, reason: string|null} $row */
            $rows[] = [
                $this->localDate($row['occurred_at'], $company, 'd/m/Y H:i'),
                $row['origin'] === 'CREDIT_NOTE' ? 'NOTA DE CREDITO' : 'ANULACION EN COCINA',
                (string) $row['reference'],
                (string) $row['dish'],
                $row['ingredient']['sku'],
                $row['ingredient']['name'],
                $row['ingredient']['unit_code'],
                $row['quantity'],
                $row['unit_cost'],
                $row['total_cost'],
                (string) $row['authorized_by'],
                (string) $row['reason'],
            ];
        }

        $rows[] = ['', '', '', '', '', '', '', '', 'TOTAL', $audit['total_cost'], '', ''];

        return $this->csv($rows);
    }

    /**
     * Consumo de recetas, reversos por anulación o nota de crédito y mermas del periodo.
     *
     * @return array{consumed: numeric-string, reversed: numeric-string, waste: numeric-string}
     */
    private function kardexCosts(ReportPeriod $period): array
    {
        $reversalSources = [(new Order)->getMorphClass(), (new OrderItem)->getMorphClass(), (new Sale)->getMorphClass()];

        $row = KardexMovement::query()->toBase()
            ->where('occurred_at', '>=', $period->start)
            ->where('occurred_at', '<', $period->end)
            ->selectRaw('coalesce(sum(case when movement_type = ? then total_cost else 0 end), 0) as consumed', [KardexMovementType::SaleConsumption->value])
            ->selectRaw('coalesce(sum(case when movement_type = ? and source_type in (?, ?, ?) then total_cost else 0 end), 0) as reversed', [KardexMovementType::AdjustmentIn->value, ...$reversalSources])
            ->selectRaw('coalesce(sum(case when movement_type = ? then total_cost else 0 end), 0) as waste', [KardexMovementType::Waste->value])
            ->first() ?? throw new RuntimeException('Agregado de kardex vacío.');

        return [
            'consumed' => $this->decimal($row, 'consumed', 4),
            'reversed' => $this->decimal($row, 'reversed', 4),
            'waste' => $this->decimal($row, 'waste', 4),
        ];
    }

    /**
     * Cuentas cobradas en el periodo (una comanda dividida en varios comprobantes cuenta una vez).
     */
    private function billedOrders(ReportPeriod $period): int
    {
        return Sale::query()
            ->where('issued_at', '>=', $period->start)
            ->where('issued_at', '<', $period->end)
            ->where('document_type', '!=', DocumentType::CreditNote)
            ->where('sunat_status', '!=', SunatStatus::Rejected)
            ->whereNotNull('order_id')
            ->distinct()
            ->count('order_id');
    }

    /**
     * Ranking por unidades vendidas en comandas pagadas, con margen de contribución
     * sobre el costo estándar de salón (receta valorizada al costo promedio vigente).
     *
     * @return list<array<string, mixed>>
     */
    private function topDishes(Company $company, ReportPeriod $period): array
    {
        $rows = OrderItem::query()->toBase()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->where('order_items.status', '!=', OrderItemStatus::Voided->value)
            ->where('orders.status', OrderStatus::Paid->value)
            ->where('orders.closed_at', '>=', $period->start)
            ->where('orders.closed_at', '<', $period->end)
            ->groupBy('menu_items.id', 'menu_items.uuid', 'menu_items.sku', 'menu_items.name')
            ->selectRaw('menu_items.id, menu_items.uuid, menu_items.sku, menu_items.name, sum(order_items.quantity) as quantity, sum(order_items.quantity * order_items.unit_price) as revenue')
            ->selectRaw('sum(case when order_items.tax_affectation_type = ? then order_items.quantity * order_items.unit_price else 0 end) as taxed_revenue', [TaxAffectationType::Taxed->value])
            ->orderByDesc('quantity')
            ->orderBy('menu_items.name')
            ->limit(10)
            ->get();

        $unitCosts = $this->standardUnitCosts(array_values(array_map(fn (object $row): int => $this->integer($row, 'id'), $rows->all())));
        $tax = $company->settings->tax;
        $factor = Decimal::add('1', Decimal::div($tax->igvRate, '100', 6), 6);
        $dishes = [];

        foreach ($rows as $row) {
            $revenue = Decimal::round($this->decimal($row, 'revenue', 4), 2);
            $taxed = Decimal::round($this->decimal($row, 'taxed_revenue', 4), 2);
            $netRevenue = $tax->pricesIncludeTax
                ? Decimal::add(Decimal::div($taxed, $factor, 2), Decimal::sub($revenue, $taxed, 2), 2)
                : $revenue;
            $quantity = Decimal::round($this->decimal($row, 'quantity'), 2);
            $unitCost = $unitCosts[$this->integer($row, 'id')] ?? '0.0000';
            $cost = Decimal::mul($quantity, $unitCost, 2);
            $margin = Decimal::sub($netRevenue, $cost, 2);

            $dishes[] = [
                'id' => $this->string($row, 'uuid'),
                'sku' => $this->string($row, 'sku'),
                'name' => $this->string($row, 'name'),
                'quantity' => $quantity,
                'gross_revenue' => $revenue,
                'net_revenue' => $netRevenue,
                'unit_cost' => $unitCost,
                'theoretical_cost' => $cost,
                'contribution_margin' => $margin,
                'margin_percentage' => bccomp($netRevenue, '0', 2) > 0 ? Decimal::mul(Decimal::div($margin, $netRevenue, 10), '100', 2) : null,
                'food_cost_percentage' => bccomp($netRevenue, '0', 2) > 0 ? Decimal::mul(Decimal::div($cost, $netRevenue, 10), '100', 2) : null,
            ];
        }

        return $dishes;
    }

    /**
     * Costo estándar por unidad de cada plato en salón (sin envases de para llevar).
     *
     * @param  list<int>  $menuItemIds
     * @return array<int, numeric-string>
     */
    private function standardUnitCosts(array $menuItemIds): array
    {
        if ($menuItemIds === []) {
            return [];
        }

        $averageCosts = $this->averageCosts();
        $costs = [];

        foreach (Recipe::query()->whereIn('menu_item_id', $menuItemIds)->where('apply_only_on_takeout', false)->get() as $line) {
            $lineCost = Decimal::mul($line->quantity_required, $averageCosts[$line->ingredient_id] ?? '0', 4);
            $costs[$line->menu_item_id] = Decimal::add($costs[$line->menu_item_id] ?? '0', $lineCost, 4);
        }

        return $costs;
    }

    /**
     * Costo promedio ponderado vigente por insumo en el almacén principal.
     *
     * @return array<int, numeric-string>
     */
    public function averageCosts(): array
    {
        $costs = [];

        foreach (IngredientStock::query()->where('warehouse_id', $this->kardex->defaultWarehouseId())->get() as $stock) {
            $costs[$stock->ingredient_id] = $stock->average_cost;
        }

        return $costs;
    }

    /**
     * @param  numeric-string  $gross
     * @return list<array{channel: string, label: string, total: numeric-string, orders: int, share_percentage: numeric-string|null}>
     */
    private function salesByChannel(ReportPeriod $period, string $gross): array
    {
        $rows = Sale::query()->toBase()
            ->join('orders', 'orders.id', '=', 'sales.order_id')
            ->where('sales.issued_at', '>=', $period->start)
            ->where('sales.issued_at', '<', $period->end)
            ->where('sales.sunat_status', '!=', SunatStatus::Rejected->value)
            ->groupBy('orders.order_type')
            ->selectRaw('orders.order_type, coalesce(sum(case when sales.document_type = ? then -sales.total_amount else sales.total_amount end), 0) as total', [DocumentType::CreditNote->value])
            ->selectRaw('count(distinct sales.order_id) filter (where sales.document_type <> ?) as orders', [DocumentType::CreditNote->value])
            ->get()
            ->keyBy('order_type');

        return array_map(function (OrderType $type) use ($rows, $gross): array {
            $row = $rows->get($type->value);
            $total = $row === null ? '0.00' : $this->decimal($row, 'total');

            return [
                'channel' => $type->value,
                'label' => $type->label(),
                'total' => $total,
                'orders' => $row === null ? 0 : $this->integer($row, 'orders'),
                'share_percentage' => $this->share($total, $gross),
            ];
        }, OrderType::cases());
    }

    /**
     * @return list<array{method: string, label: string, total: numeric-string, share_percentage: numeric-string|null}>
     */
    private function salesByPaymentMethod(ReportPeriod $period): array
    {
        $rows = SalePayment::query()->toBase()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.issued_at', '>=', $period->start)
            ->where('sales.issued_at', '<', $period->end)
            ->where('sales.sunat_status', '!=', SunatStatus::Rejected->value)
            ->groupBy('sale_payments.method')
            ->selectRaw('sale_payments.method, coalesce(sum(sale_payments.amount), 0) as total')
            ->get()
            ->keyBy('method');

        $collected = '0.00';

        foreach ($rows as $row) {
            $collected = Decimal::add($collected, $this->decimal($row, 'total'), 2);
        }

        return array_map(function (PaymentMethod $method) use ($rows, $collected): array {
            $row = $rows->get($method->value);
            $total = $row === null ? '0.00' : $this->decimal($row, 'total');

            return ['method' => $method->value, 'label' => $method->label(), 'total' => $total, 'share_percentage' => $this->share($total, $collected)];
        }, PaymentMethod::cases());
    }

    /**
     * Tipo (tabla 10), serie y número del documento que sustenta el movimiento.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function kardexDocument(KardexMovement $movement): array
    {
        $source = $movement->source;

        if ($source instanceof Sale) {
            return [$source->document_type->value, $source->series, (string) $source->correlative];
        }

        $reference = (string) $movement->document_reference;
        $dash = strrpos($reference, '-');

        return $dash === false ? ['00', '-', $reference] : ['00', substr($reference, 0, $dash), substr($reference, $dash + 1)];
    }

    /**
     * Importe con signo: las notas de crédito restan en el registro de ventas.
     *
     * @return numeric-string
     */
    private function signed(string $sign, string $value): string
    {
        return Decimal::mul(Decimal::normalize($sign, 0), Decimal::normalize($value, 2), 2);
    }

    /**
     * @param  numeric-string  $part
     * @param  numeric-string  $whole
     * @return numeric-string|null
     */
    private function share(string $part, string $whole): ?string
    {
        return bccomp($whole, '0', 2) > 0 ? Decimal::mul(Decimal::div($part, $whole, 10), '100', 2) : null;
    }

    private function localDate(string $iso, Company $company, string $format = 'd/m/Y'): string
    {
        return CarbonImmutable::parse($iso)->setTimezone($company->settings->timezone)->format($format);
    }

    /**
     * CSV UTF-8 con BOM (las hojas de cálculo en es-PE respetan tildes y eñes).
     *
     * @param  list<list<string>>  $rows
     */
    private function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+') ?: throw new RuntimeException('No se pudo generar el CSV.');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return "\u{FEFF}".($content === false ? '' : $content);
    }

    /**
     * @return numeric-string
     */
    private function decimal(object $row, string $key, int $scale = 2): string
    {
        $value = $row->{$key} ?? '0';

        return Decimal::normalize(is_scalar($value) ? (string) $value : '0', $scale);
    }

    private function integer(object $row, string $key): int
    {
        $value = $row->{$key} ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    private function string(object $row, string $key): string
    {
        $value = $row->{$key} ?? '';

        return is_scalar($value) ? (string) $value : '';
    }
}
