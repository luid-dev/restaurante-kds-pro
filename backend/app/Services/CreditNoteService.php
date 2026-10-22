<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CreditNoteReason;
use App\Enums\DocumentType;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\SunatStatus;
use App\Exceptions\BillingException;
use App\Models\Company;
use App\Models\DocumentSeries;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Nota de crédito electrónica (07) que anula por completo una factura o boleta.
 *
 *  - Serie según el comprobante afectado: FC01 para facturas, BC01 para boletas.
 *  - Motivos del catálogo 09: 01 anulación de la operación, 02 error en el RUC
 *    (solo facturas) y 06 devolución total.
 *  - Replica líneas, afectaciones y totales del original y firma el XML (Note UBL 2.1).
 *  - Anulación y devolución reclasifican el costo de los insumos consumidos como
 *    merma (tipo SUNAT 13) en el kardex: lo cocinado no vuelve al almacén.
 *
 * El comprobante original se bloquea con SELECT ... FOR UPDATE y un índice único
 * parcial impide una segunda nota sobre el mismo documento.
 */
final readonly class CreditNoteService
{
    public function __construct(
        private SunatService $sunat,
        private RecipeKardexService $kardex,
        private CashSessionService $cashSessions,
    ) {}

    public function issue(Sale $sale, CreditNoteReason $reason, string $description, User $actor): Sale
    {
        $creditNote = DB::transaction(function () use ($sale, $reason, $description, $actor): Sale {
            $original = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $this->assertCreditable($original, $reason);

            $company = Company::query()->findOrFail($original->company_id);
            $this->sunat->assertIssuerAddress($company);
            $original->load('items');

            $prefix = $original->document_type === DocumentType::Invoice ? 'F' : 'B';
            $series = DocumentSeries::query()
                ->where('document_type', DocumentType::CreditNote)
                ->where('is_active', true)
                ->where('series', 'like', $prefix.'%')
                ->orderBy('series')
                ->lockForUpdate()
                ->first() ?? throw BillingException::creditNoteSeriesMissing($prefix);

            $correlative = $series->next_correlative;
            $series->update(['next_correlative' => $correlative + 1]);

            $creditNote = Sale::query()->create([
                'order_id' => $original->order_id,
                'cash_register_session_id' => $this->cashSessions->currentFor($actor)?->id,
                'issued_by' => $actor->id,
                'document_series_id' => $series->id,
                'related_sale_id' => $original->id,
                'credit_note_reason' => $reason,
                'credit_note_description' => $description,
                'document_type' => DocumentType::CreditNote,
                'series' => $series->series,
                'correlative' => $correlative,
                'consumption_format' => $original->consumption_format,
                'charge_mode' => $original->charge_mode,
                'customer_document_type' => $original->customer_document_type,
                'customer_document_number' => $original->customer_document_number,
                'customer_name' => $original->customer_name,
                'currency' => $original->currency,
                'igv_rate' => $original->igv_rate,
                'taxable_amount' => $original->taxable_amount,
                'exempt_amount' => $original->exempt_amount,
                'unaffected_amount' => $original->unaffected_amount,
                'igv_amount' => $original->igv_amount,
                'service_charge' => $original->service_charge,
                'total_amount' => $original->total_amount,
                'sunat_status' => SunatStatus::Pending,
                'issued_at' => CarbonImmutable::now(),
            ]);

            foreach ($original->items as $item) {
                SaleItem::query()->create([
                    'sale_id' => $creditNote->id,
                    'order_item_id' => $item->order_item_id,
                    'description' => $item->description,
                    'unit_code' => $item->unit_code,
                    'quantity' => $item->quantity,
                    'unit_value' => $item->unit_value,
                    'unit_price' => $item->unit_price,
                    'tax_affectation_type' => $item->tax_affectation_type,
                    'igv_amount' => $item->igv_amount,
                    'line_total' => $item->line_total,
                ]);
            }

            $this->sunat->sign($creditNote->load('items')->setRelation('relatedSale', $original), $company);
            $wasted = $this->registerInventoryImpact($original, $creditNote, $reason, $description, $actor);

            activity('billing')
                ->event('credit_note_issued')
                ->performedOn($creditNote)
                ->causedBy($actor)
                ->withProperties([
                    'document' => $creditNote->documentNumber(),
                    'affected_document' => $original->documentNumber(),
                    'reason_code' => $reason->value,
                    'reason' => $description,
                    'total_amount' => $creditNote->total_amount,
                    'ingredients_to_waste' => $wasted,
                ])
                ->log("Nota de crédito {$creditNote->documentNumber()} sobre {$original->documentNumber()}");

            return $creditNote;
        });

        $this->sunat->queueDelivery($creditNote);

        return $creditNote->load(['items', 'payments', 'order.diningTable', 'relatedSale']);
    }

    private function assertCreditable(Sale $original, CreditNoteReason $reason): void
    {
        if ($original->document_type !== DocumentType::Invoice && $original->document_type !== DocumentType::Receipt) {
            throw BillingException::creditNoteNotAllowed($original);
        }

        if (! $reason->appliesTo($original->document_type)) {
            throw BillingException::creditNoteReasonNotApplicable($reason, $original);
        }

        if ($original->sunat_status === SunatStatus::Rejected) {
            throw BillingException::rejectedBySunat($original);
        }

        if (Sale::query()->where('related_sale_id', $original->id)->where('document_type', DocumentType::CreditNote)->exists()) {
            throw BillingException::alreadyCredited($original);
        }

        if ($original->order_id !== null) {
            $order = Order::query()->findOrFail($original->order_id);

            // Con la cuenta abierta el saldo dejaría de cuadrar: se anula una vez liquidada.
            if ($order->status === OrderStatus::Open || $order->status === OrderStatus::PreBilled) {
                throw BillingException::creditNoteOnOpenOrder($order);
            }
        }
    }

    /**
     * Insumos del consumo anulado. En formato detallado se usan los platos y
     * cantidades exactas del comprobante; "Por consumo" cubre una fracción de la
     * comanda (total del comprobante / total de la comanda).
     *
     * @return array<int, numeric-string>
     */
    private function registerInventoryImpact(Sale $original, Sale $creditNote, CreditNoteReason $reason, string $description, User $actor): array
    {
        if (! $reason->reclassifiesConsumptionAsWaste() || $original->order_id === null) {
            return [];
        }

        $order = Order::query()->findOrFail($original->order_id);
        $linked = $original->items->filter(static fn (SaleItem $line): bool => $line->order_item_id !== null);
        $portions = [];

        if ($linked->isNotEmpty()) {
            $items = OrderItem::query()->with('menuItem.recipes')->whereIn('id', $linked->pluck('order_item_id')->all())->get()->keyBy('id');

            foreach ($linked as $line) {
                $item = $items->get((int) $line->order_item_id);

                if ($item !== null) {
                    $portions[] = ['item' => $item, 'quantity' => $line->quantity];
                }
            }
        } elseif (bccomp($order->total_amount, '0', 2) > 0) {
            $share = Decimal::div($original->total_amount, $order->total_amount, 10);
            $share = bccomp($share, '1', 10) > 0 ? '1' : $share;
            $items = OrderItem::query()->with('menuItem.recipes')->where('order_id', $order->id)->where('status', '!=', OrderItemStatus::Voided)->get();

            foreach ($items as $item) {
                $portions[] = ['item' => $item, 'quantity' => Decimal::mul($item->quantity, $share, 4)];
            }
        }

        $requirements = $this->kardex->requirementsFor($order->order_type, $portions);

        if ($requirements !== []) {
            $this->kardex->reclassifyCreditedConsumption($creditNote, $requirements, $actor, $description);
        }

        return $requirements;
    }
}
