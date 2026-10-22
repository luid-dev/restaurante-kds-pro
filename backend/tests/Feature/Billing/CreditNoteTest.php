<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\CreditNoteReason;
use App\Enums\DocumentType;
use App\Enums\KardexMovementType;
use App\Models\Ingredient;
use App\Models\KardexMovement;
use App\Models\Order;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\InteractsWithRestaurant;
use Tests\TestCase;

/**
 * Nota de crédito electrónica (07): serie FC01/BC01, XML Note UBL 2.1 enlazado
 * al comprobante, merma en el kardex, auditoría y reglas del catálogo 09.
 */
final class CreditNoteTest extends TestCase
{
    use InteractsWithRestaurant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRestaurant();
    }

    #[Test]
    public function cancelling_an_invoice_issues_a_signed_fc01_note_and_moves_the_cost_to_waste(): void
    {
        $invoice = $this->paidSale('M-01', 'F001', $this->invoiceCustomer(), [['sku' => 'BRA-003', 'quantity' => '2']], '42.00');
        $chickenStock = $this->stockOf('POL-001');

        $this->waiter();
        $this->issue($invoice, '01', 'Cliente reclamó el pedido')->assertForbidden();

        $this->cashier();
        $response = $this->issue($invoice, '01', 'Cliente reclamó el pedido')
            ->assertCreated()
            ->assertJsonPath('data.document_type', '07')
            ->assertJsonPath('data.document_number', 'FC01-00000001')
            ->assertJsonPath('data.related_document.document_number', 'F001-00000001')
            ->assertJsonPath('data.credit_note_reason.code', '01')
            ->assertJsonPath('data.totals.total_amount', '42.00')
            ->assertJsonPath('data.totals.taxable_amount', '38.01')
            ->assertJsonPath('data.sunat.status', 'PENDING');

        $note = Sale::query()->with('items')->where('uuid', $response->json('data.id'))->sole();
        $this->assertSame(CreditNoteReason::OperationCancelled, $note->credit_note_reason);
        $this->assertSame($invoice->id, $note->related_sale_id);
        $this->assertSame($invoice->order_id, $note->order_id);

        $xml = (string) Storage::disk('local')->get((string) $note->sunat_xml_path);
        $this->assertStringContainsString('<CreditNote', $xml);
        $this->assertStringContainsString('<cbc:ID>FC01-1</cbc:ID>', $xml);
        $this->assertStringContainsString('<cbc:ReferenceID>F001-1</cbc:ReferenceID>', $xml);
        $this->assertStringContainsString('<cbc:ResponseCode>01</cbc:ResponseCode>', $xml);
        $this->assertStringContainsString('<cbc:DocumentTypeCode>01</cbc:DocumentTypeCode>', $xml);
        $this->assertStringContainsString('<cac:CreditNoteLine>', $xml);
        $this->assertStringContainsString('<ds:Signature', $xml);
        $this->assertNotEmpty($note->sunat_hash);

        // Lo cocinado no vuelve al almacén: el consumo pasa a merma (tipo 13) con la nota como origen.
        $this->assertSame($chickenStock, $this->stockOf('POL-001'));
        $waste = KardexMovement::query()
            ->where('ingredient_id', Ingredient::query()->where('sku', 'POL-001')->value('id'))
            ->where('movement_type', KardexMovementType::Waste)
            ->sole();
        $this->assertSame('0.5000', $waste->quantity_out);
        $this->assertSame('13', $waste->sunat_operation_type);
        $this->assertSame($note->getMorphClass(), $waste->source_type);
        $this->assertSame($note->id, $waste->source_id);
        $this->assertSame('FC01-00000001', $waste->document_reference);

        $audit = Activity::query()->where('log_name', 'billing')->where('event', 'credit_note_issued')->sole();
        $this->assertSame('F001-00000001', $audit->properties['affected_document'] ?? null);

        // Un comprobante admite una sola nota de crédito total.
        $this->issue($invoice, '06', 'Segundo intento de anulación')->assertConflict();
        $this->assertSame(1, Sale::query()->where('document_type', DocumentType::CreditNote)->count());
    }

    #[Test]
    public function a_ruc_error_note_corrects_the_document_without_touching_the_kardex(): void
    {
        $invoice = $this->paidSale('M-02', 'F001', $this->invoiceCustomer(), [['sku' => 'BEB-001', 'quantity' => '1']], '12.00');
        $movements = KardexMovement::query()->count();

        $this->issue($invoice, '02', 'RUC del cliente digitado con error')->assertCreated()->assertJsonPath('data.document_number', 'FC01-00000001');
        $this->assertSame($movements, KardexMovement::query()->count());
    }

    #[Test]
    public function receipts_use_the_bc01_series_and_reject_the_ruc_error_reason(): void
    {
        $receipt = $this->paidSale('M-03', 'B001', ['document_type' => '0'], [['sku' => 'BEB-002', 'quantity' => '2']], '9.00');

        $this->issue($receipt, '02', 'No aplica a boletas')->assertUnprocessable()->assertJsonPath('code', 'BUSINESS_RULE_VIOLATION');
        $this->issue($receipt, '06', 'Devolución total del pedido')->assertCreated()->assertJsonPath('data.document_number', 'BC01-00000001');
    }

    #[Test]
    public function sales_notes_and_open_accounts_do_not_admit_credit_notes(): void
    {
        $salesNote = $this->paidSale('M-04', 'NV01', ['document_type' => '0'], [['sku' => 'BEB-002', 'quantity' => '1']], '4.50');
        $this->issue($salesNote, '01', 'Documento interno')->assertUnprocessable();

        $order = $this->openTable('M-05');
        $this->sendItems($order, [['sku' => 'BEB-002', 'quantity' => '2']])->assertOk();
        $this->cashier();
        $partial = $this->charge($order, [
            'mode' => 'ITEMS', 'lines' => [['order_item_id' => $order->items()->sole()->uuid, 'quantity' => '1']], 'series' => 'B001', 'format' => 'DETAILED',
            'customer' => ['document_type' => '0'], 'payments' => [['method' => 'CASH', 'amount' => '4.50']],
        ])->assertCreated();

        $this->issue(Sale::query()->where('uuid', $partial->json('data.id'))->sole(), '01', 'Cuenta aún abierta')
            ->assertConflict()
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'aún tiene saldo por cobrar'));
    }

    #[Test]
    public function the_document_register_lists_the_note_and_serves_the_signed_xml(): void
    {
        $invoice = $this->paidSale('M-06', 'F001', $this->invoiceCustomer(), [['sku' => 'BEB-003', 'quantity' => '1']], '14.00');
        $this->issue($invoice, '01', 'Anulación solicitada por el cliente')->assertCreated();

        $this->getJson('/api/v1/sales?document_type=01')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.credit_note.document_number', 'FC01-00000001')
            ->assertJsonPath('meta.pagination.total', 1);

        $this->getJson('/api/v1/sales?search=FC01')->assertOk()->assertJsonPath('data.0.related_document.document_number', 'F001-00000001');

        $xml = $this->get("/api/v1/sales/{$invoice->uuid}/xml")->assertOk()->assertHeader('Content-Type', 'application/xml');
        $this->assertStringContainsString('<Invoice', (string) $xml->getContent());
        $this->assertStringContainsString('attachment; filename="01-F001-00000001.xml"', (string) $xml->headers->get('Content-Disposition'));

        // Sin envío a SUNAT (beta deshabilitado) todavía no hay CDR.
        $this->getJson("/api/v1/sales/{$invoice->uuid}/cdr")->assertNotFound();
    }

    /**
     * @param  array<string, string>  $customer
     * @param  list<array{sku: string, quantity: string}>  $items
     * @param  numeric-string  $total
     */
    private function paidSale(string $table, string $series, array $customer, array $items, string $total): Sale
    {
        $order = $this->openTable($table);
        $this->sendItems($order, $items)->assertOk();

        if (! $this->isCashSessionOpen()) {
            $this->openCashSession();
        }

        $this->cashier();
        $response = $this->charge($order, [
            'mode' => 'FULL', 'series' => $series, 'format' => 'DETAILED', 'customer' => $customer,
            'payments' => [['method' => 'CARD', 'amount' => $total, 'reference' => 'VISA-0001']],
        ])->assertCreated();

        $this->assertSame('PAID', Order::query()->findOrFail($order->id)->status->value);

        return Sale::query()->where('uuid', $response->json('data.id'))->sole();
    }

    private function isCashSessionOpen(): bool
    {
        $this->cashier();

        return $this->getJson('/api/v1/cash-sessions/current')->json('data.session') !== null;
    }

    /**
     * @return TestResponse<JsonResponse>
     */
    private function issue(Sale $sale, string $reason, string $description): TestResponse
    {
        return $this->postJson("/api/v1/sales/{$sale->uuid}/credit-notes", ['reason' => $reason, 'description' => $description]);
    }
}
