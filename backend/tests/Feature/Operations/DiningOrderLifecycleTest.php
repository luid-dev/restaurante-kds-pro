<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\DTOs\Company\ServiceChargeSettingsData;
use App\Enums\ConsumptionFormat;
use App\Enums\KardexMovementType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\SunatStatus;
use App\Enums\TableStatus;
use App\Events\OrderSentToKitchen;
use App\Events\TableStatusUpdated;
use App\Models\Ingredient;
use App\Models\KardexMovement;
use App\Models\Order;
use App\Models\Sale;
use Greenter\Report\XmlUtils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithRestaurant;
use Tests\TestCase;

/**
 * Ciclo completo de una mesa: abrir M-01, comandar 2 x 1/4 de pollo (descuenta
 * exactamente 0.5000 de pollo entero), dividir la cuenta en 2 pagos con factura
 * "Por consumo" firmada con Greenter y liberar la mesa al llegar a 0.00.
 */
final class DiningOrderLifecycleTest extends TestCase
{
    use InteractsWithRestaurant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRestaurant();
    }

    #[Test]
    public function a_table_goes_from_order_to_split_invoice_and_back_to_available(): void
    {
        Event::fake([OrderSentToKitchen::class, TableStatusUpdated::class]);
        $chickenBefore = $this->stockOf('POL-001');
        $containerBefore = $this->stockOf('ENV-001');

        // 1. El mozo abre M-01 y comanda 2 cuartos de pollo.
        $order = $this->openTable('M-01');
        $this->assertSame(TableStatus::Occupied, $this->table('M-01')->status);

        $this->sendItems($order, [['sku' => 'BRA-003', 'quantity' => '2', 'modifiers' => ['Pecho']]])
            ->assertOk()
            ->assertJsonPath('data.totals.subtotal', '42.00')
            ->assertJsonPath('data.totals.tax_amount', '3.99')
            ->assertJsonPath('data.totals.total_amount', '42.00');

        // 2. Escandallo exacto: 2 x 0.2500 = 0.5000 de pollo; en salón no se usa envase.
        $this->assertSame('0.5000', bcsub($chickenBefore, $this->stockOf('POL-001'), 4));
        $this->assertSame('119.5000', $this->stockOf('POL-001'));
        $this->assertSame($containerBefore, $this->stockOf('ENV-001'));

        $movement = KardexMovement::query()
            ->where('ingredient_id', Ingredient::query()->where('sku', 'POL-001')->value('id'))
            ->where('movement_type', KardexMovementType::SaleConsumption)
            ->sole();
        $this->assertSame('0.5000', $movement->quantity_out);
        $this->assertSame('7.2500', $movement->total_cost);
        $this->assertSame('10', $movement->sunat_operation_type);

        Event::assertDispatched(OrderSentToKitchen::class, fn (OrderSentToKitchen $event): bool => $event->companyUuid === $this->company->uuid
            && array_keys($event->areas) === ['GRILL_OVEN']
            && $event->areas['GRILL_OVEN'][0]['quantity'] === '2.00'
            && $event->broadcastOn()[0]->name === "private-company.{$this->company->uuid}.kds");

        // 3. Precuenta: la mesa pasa a "en precuenta".
        $this->postJson("/api/v1/orders/{$order->uuid}/pre-bill")->assertOk()->assertJsonPath('data.status', 'PRE_BILLED');
        $this->assertSame(TableStatus::PreBill, $this->table('M-01')->status);

        // 4. Caja divide la cuenta: el primer comensal paga su cuarto en efectivo.
        $this->openCashSession();
        $itemId = $order->items()->sole()->uuid;

        $first = $this->charge($order, [
            'mode' => 'ITEMS',
            'lines' => [['order_item_id' => $itemId, 'quantity' => '1']],
            'series' => 'F001',
            'format' => 'BY_CONSUMPTION',
            'customer' => $this->invoiceCustomer(),
            'payments' => [['method' => 'CASH', 'amount' => '21.00', 'received_amount' => '50.00']],
        ])->assertCreated()
            ->assertJsonPath('data.document_number', 'F001-00000001')
            ->assertJsonPath('data.totals.total_amount', '21.00')
            ->assertJsonPath('data.totals.taxable_amount', '19.00')
            ->assertJsonPath('data.totals.igv_amount', '2.00')
            ->assertJsonPath('data.payments.0.change_amount', '29.00')
            ->assertJsonPath('data.order.balance', '21.00');

        $this->assertSame(TableStatus::PreBill, $this->table('M-01')->status, 'Con saldo pendiente la mesa sigue ocupada.');

        // 5. El segundo comensal liquida con pago mixto Yape + tarjeta.
        $second = $this->charge($order, [
            'mode' => 'FULL',
            'series' => 'F001',
            'format' => 'BY_CONSUMPTION',
            'customer' => $this->invoiceCustomer(),
            'payments' => [
                ['method' => 'YAPE', 'amount' => '11.00', 'reference' => '00451287'],
                ['method' => 'CARD', 'amount' => '10.00', 'reference' => 'VISA-4417'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.document_number', 'F001-00000002')
            ->assertJsonPath('data.order.balance', '0.00')
            ->assertJsonPath('data.order.status', 'PAID');

        // 6. Mesa libre y cuenta cerrada exactamente en 0.00.
        $table = $this->table('M-01');
        $this->assertSame(TableStatus::Available, $table->status);
        $this->assertNull($table->current_order_id);
        $this->assertNull($table->opened_at);

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('42.00', $order->paid_amount);
        $this->assertSame('0.00', bcsub($order->total_amount, $order->paid_amount, 2));
        $this->assertSame(['2.00'], $order->items()->pluck('paid_quantity')->all());

        // 7. Ambas facturas: una sola línea UBL con la glosa de consumo, firmadas y con hash.
        foreach ([$first->json('data.id'), $second->json('data.id')] as $saleUuid) {
            $sale = Sale::query()->with('items')->where('uuid', $saleUuid)->sole();

            $this->assertSame(ConsumptionFormat::ByConsumption, $sale->consumption_format);
            $this->assertSame(SunatStatus::Pending, $sale->sunat_status);
            $line = $sale->items->sole();
            $this->assertSame(ConsumptionFormat::CONSUMPTION_GLOSS, $line->description);
            $this->assertSame('ZZ', $line->unit_code);

            $xml = Storage::disk('local')->get((string) $sale->sunat_xml_path);
            $this->assertIsString($xml);
            $this->assertSame(1, substr_count($xml, '<cac:InvoiceLine>'));
            $this->assertStringContainsString(ConsumptionFormat::CONSUMPTION_GLOSS, $xml);
            $this->assertStringContainsString('<cbc:InvoiceTypeCode listID="0101"', $xml);
            $this->assertStringContainsString('<cbc:Percent>10.5</cbc:Percent>', $xml);
            $this->assertStringContainsString('VEINTIUNO CON 00/100 SOLES', $xml);
            $this->assertStringContainsString('<ds:Signature', $xml);
            $this->assertNotEmpty($sale->sunat_hash);
            $this->assertSame((new XmlUtils)->getHashSign($xml), $sale->sunat_hash);
        }

        $secondId = $second->json('data.id');
        $this->assertIsString($secondId);

        $this->getJson("/api/v1/sales/{$secondId}")
            ->assertOk()
            ->assertJsonPath('data.sunat.qr_payload', fn (string $qr): bool => str_starts_with($qr, '20609876543|01|F001|2|2.00|21.00|')
                && str_contains($qr, '|6|'.self::CUSTOMER_RUC.'|'));
    }

    #[Test]
    public function a_detailed_receipt_lists_each_dish_and_the_packaging_charge(): void
    {
        $order = $this->openTable('M-02');
        $this->sendItems($order, [
            ['sku' => 'BRA-003', 'quantity' => '1', 'modifiers' => ['Pierna', 'Para llevar']],
            ['sku' => 'BEB-002', 'quantity' => '2'],
        ])->assertOk()->assertJsonPath('data.totals.packaging_total', '1.00')->assertJsonPath('data.totals.total_amount', '31.00');

        $this->openCashSession();

        $response = $this->charge($order, [
            'mode' => 'FULL',
            'series' => 'B001',
            'format' => 'DETAILED',
            'customer' => ['document_type' => '0'],
            'payments' => [['method' => 'PLIN', 'amount' => '31.00', 'reference' => '88231']],
        ])->assertCreated()
            ->assertJsonPath('data.document_number', 'B001-00000001')
            ->assertJsonPath('data.items.*.description', [
                '1/4 POLLO A LA BRASA (PECHO/PIERNA) (PIERNA, PARA LLEVAR)',
                'INKA KOLA PERSONAL 500ML',
                'SERVICIO DE EMPAQUE PARA LLEVAR',
            ])
            ->assertJsonPath('data.items.*.quantity', ['1.00', '2.00', '1.00'])
            ->assertJsonPath('data.items.*.line_total', ['21.00', '9.00', '1.00']);

        $sale = Sale::query()->where('uuid', $response->json('data.id'))->sole();
        $xml = Storage::disk('local')->get((string) $sale->sunat_xml_path);
        $this->assertSame(3, substr_count((string) $xml, '<cac:InvoiceLine>'));
        $this->assertSame(TableStatus::Available, $this->table('M-02')->status);
    }

    #[Test]
    public function equal_parts_split_only_allows_by_consumption_and_settles_exactly(): void
    {
        $order = $this->openTable('M-03', 3);
        $this->sendItems($order, [['sku' => 'BRA-001', 'quantity' => '1'], ['sku' => 'BEB-001', 'quantity' => '1']])->assertOk();
        $this->openCashSession();

        // 84.00 entre 3: 28.00 cada uno. Detallado no se permite al dividir por monto.
        $this->charge($order, [
            'mode' => 'AMOUNT', 'amount' => '28.00', 'series' => 'B001', 'format' => 'DETAILED',
            'customer' => ['document_type' => '0'], 'payments' => [['method' => 'CASH', 'amount' => '28.00']],
        ])->assertUnprocessable()->assertJsonValidationErrors('mode');

        foreach (['28.00', '28.00', '28.00'] as $index => $part) {
            $this->charge($order, [
                'mode' => 'AMOUNT', 'amount' => $part, 'series' => 'B001', 'format' => 'BY_CONSUMPTION',
                'customer' => ['document_type' => '0'], 'payments' => [['method' => 'YAPE', 'amount' => $part]],
            ])->assertCreated()->assertJsonPath('data.order.balance', ['56.00', '28.00', '0.00'][$index]);
        }

        // Tras dividir por montos ya no se puede cobrar por platos.
        $this->assertSame(OrderStatus::Paid, Order::query()->findOrFail($order->id)->status);
        $this->assertSame(TableStatus::Available, $this->table('M-03')->status);
    }

    #[Test]
    public function the_quote_prorates_the_service_charge_and_the_last_split_takes_the_exact_remainder(): void
    {
        $this->company->update(['settings' => $this->company->settings->withServiceCharge(
            new ServiceChargeSettingsData(true, '10.00', [OrderType::DineIn]),
        )]);

        $order = $this->openTable('M-06', 3);
        // 3 x 1/4 pollo = 63.00 de consumo; recargo 10% = 6.30; total 69.30.
        $this->sendItems($order, [['sku' => 'BRA-003', 'quantity' => '3']])->assertOk()->assertJsonPath('data.totals.total_amount', '69.30');
        $this->openCashSession();
        $itemId = $order->items()->sole()->uuid;

        $this->postJson("/api/v1/orders/{$order->uuid}/charges/quote", ['mode' => 'ITEMS', 'format' => 'BY_CONSUMPTION', 'lines' => [['order_item_id' => $itemId, 'quantity' => '1']]])
            ->assertOk()
            ->assertJsonPath('data.gross', '21.00')
            ->assertJsonPath('data.service_charge', '2.10')
            ->assertJsonPath('data.total', '23.10')
            ->assertJsonPath('data.settles_order', false)
            ->assertJsonPath('data.balance_after', '46.20');

        foreach (['23.10', '23.10', '23.10'] as $index => $total) {
            $this->charge($order, [
                'mode' => 'ITEMS', 'lines' => [['order_item_id' => $itemId, 'quantity' => '1']], 'series' => 'B001', 'format' => 'DETAILED',
                'customer' => ['document_type' => '0'], 'payments' => [['method' => 'CARD', 'amount' => $total]],
            ])->assertCreated()
                ->assertJsonPath('data.totals.service_charge', '2.10')
                ->assertJsonPath('data.order.balance', ['46.20', '23.10', '0.00'][$index]);
        }

        $this->assertSame(TableStatus::Available, $this->table('M-06')->status);
        $this->assertSame('6.30', (string) Sale::query()->sum('service_charge'));
    }

    #[Test]
    public function the_remaining_balance_is_charged_in_full_after_a_dish_was_paid_separately(): void
    {
        $order = $this->openTable('M-05');
        $this->sendItems($order, [['sku' => 'BRA-002', 'quantity' => '1'], ['sku' => 'BEB-002', 'quantity' => '2']])->assertOk();
        $this->openCashSession();
        $chickenId = $order->items()->whereHas('menuItem', fn ($query) => $query->where('sku', 'BRA-002'))->sole()->uuid;

        // Un comensal paga su 1/2 pollo completo: ese plato queda sin saldo.
        $this->charge($order, [
            'mode' => 'ITEMS', 'lines' => [['order_item_id' => $chickenId, 'quantity' => '1']], 'series' => 'B001', 'format' => 'DETAILED',
            'customer' => ['document_type' => '0'], 'payments' => [['method' => 'CASH', 'amount' => '38.00']],
        ])->assertCreated()->assertJsonPath('data.order.balance', '9.00');

        // "Cobrar el resto" solo factura los platos con saldo pendiente.
        $this->postJson("/api/v1/orders/{$order->uuid}/charges/quote", ['mode' => 'FULL', 'format' => 'DETAILED'])
            ->assertOk()
            ->assertJsonPath('data.total', '9.00')
            ->assertJsonPath('data.settles_order', true);

        $this->charge($order, [
            'mode' => 'FULL', 'series' => 'B001', 'format' => 'DETAILED',
            'customer' => ['document_type' => '0'], 'payments' => [['method' => 'YAPE', 'amount' => '9.00']],
        ])->assertCreated()
            ->assertJsonPath('data.items.*.description', ['INKA KOLA PERSONAL 500ML'])
            ->assertJsonPath('data.order.balance', '0.00');

        $this->assertSame(TableStatus::Available, $this->table('M-05')->status);
    }

    #[Test]
    public function an_invalid_invoice_request_charges_nothing_and_consumes_no_correlative(): void
    {
        $order = $this->openTable('M-04');
        $this->sendItems($order, [['sku' => 'BRA-002', 'quantity' => '1']])->assertOk();
        $this->openCashSession();

        $base = ['mode' => 'FULL', 'series' => 'F001', 'format' => 'BY_CONSUMPTION', 'payments' => [['method' => 'CASH', 'amount' => '38.00']]];

        $this->charge($order, [...$base, 'customer' => ['document_type' => '6', 'document_number' => '20601234566', 'name' => 'X']])
            ->assertUnprocessable()->assertJsonValidationErrors('customer.document_number');
        $this->charge($order, [...$base, 'customer' => ['document_type' => '1', 'document_number' => '45781236']])
            ->assertUnprocessable()->assertJsonPath('code', 'BUSINESS_RULE_VIOLATION');
        $this->charge($order, [...$base, 'customer' => $this->invoiceCustomer(), 'payments' => [['method' => 'CASH', 'amount' => '30.00']]])
            ->assertUnprocessable()->assertJsonPath('message', 'Los pagos (S/ 30.00) no coinciden con el total a cobrar (S/ 38.00).');

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame('0.00', $order->refresh()->paid_amount);

        $this->charge($order, [...$base, 'customer' => $this->invoiceCustomer()])->assertCreated()->assertJsonPath('data.document_number', 'F001-00000001');
    }

    #[Test]
    public function charging_requires_an_open_cash_session_and_a_signing_certificate(): void
    {
        $order = $this->openTable('M-05');
        $this->sendItems($order, [['sku' => 'BEB-003', 'quantity' => '1']])->assertOk();
        $payload = [
            'mode' => 'FULL', 'series' => 'B001', 'format' => 'DETAILED', 'customer' => ['document_type' => '0'],
            'payments' => [['method' => 'CASH', 'amount' => '14.00']],
        ];

        $this->cashier();
        $this->charge($order, $payload)->assertConflict()->assertJsonPath('message', 'Abra su turno de caja antes de cobrar.');

        $this->openCashSession();
        config(['sunat.certificate_path' => storage_path('framework/testing/no-existe.pem')]);
        $this->charge($order, $payload)->assertServiceUnavailable();
        $this->assertSame(0, Sale::query()->count());

        // La nota de venta no se firma: no depende del certificado.
        $this->charge($order, [...$payload, 'series' => 'NV01'])->assertCreated()->assertJsonPath('data.sunat.status', 'NOT_APPLICABLE');
    }
}
