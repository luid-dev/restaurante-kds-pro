<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithRestaurant;
use Tests\TestCase;

/**
 * Escenario del día: una factura de 2 x 1/4 de pollo (42.00), una boleta de
 * Inka Kola (12.00) luego anulada con nota de crédito y un 1/4 de pollo anulado
 * en preparación. Verifica KPIs, food cost, mermas, SIRE y kardex 13.1.
 */
final class ProfitabilityReportsTest extends TestCase
{
    use InteractsWithRestaurant, RefreshDatabase;

    private Sale $receipt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRestaurant();

        $first = $this->openTable('M-01');
        $this->sendItems($first, [['sku' => 'BRA-003', 'quantity' => '2']])->assertOk();

        $second = $this->openTable('M-02');
        $this->sendItems($second, [['sku' => 'BEB-001', 'quantity' => '1'], ['sku' => 'BRA-003', 'quantity' => '1']])->assertOk();
        $burnt = $second->items()->whereHas('menuItem', static fn ($query) => $query->where('sku', 'BRA-003'))->sole();

        $this->kitchen();
        $this->postJson('/api/v1/kds/items/advance', ['item_ids' => [$burnt->uuid], 'status' => 'PREPARING'])->assertOk();
        $this->admin();
        $this->postJson("/api/v1/orders/{$second->uuid}/items/{$burnt->uuid}/void", ['reason' => 'Se quemó en el horno'])->assertOk();

        $this->openCashSession();
        $this->charge($first, [
            'mode' => 'FULL', 'series' => 'F001', 'format' => 'DETAILED', 'customer' => $this->invoiceCustomer(),
            'payments' => [['method' => 'CARD', 'amount' => '42.00', 'reference' => 'VISA-1001']],
        ])->assertCreated();
        $receipt = $this->charge($second, [
            'mode' => 'FULL', 'series' => 'B001', 'format' => 'DETAILED', 'customer' => ['document_type' => '0'],
            'payments' => [['method' => 'YAPE', 'amount' => '12.00', 'reference' => '7781']],
        ])->assertCreated();
        $this->receipt = Sale::query()->where('uuid', $receipt->json('data.id'))->sole();

        $this->postJson("/api/v1/sales/{$this->receipt->uuid}/credit-notes", ['reason' => '06', 'description' => 'Bebida devuelta por el cliente'])->assertCreated();
        $this->admin();
    }

    #[Test]
    public function the_dashboard_nets_credit_notes_and_derives_food_cost_and_waste_from_the_kardex(): void
    {
        $this->getJson('/api/v1/reports/dashboard-metrics')
            ->assertOk()
            ->assertJsonPath('data.gross_sales', '42.00')
            ->assertJsonPath('data.net_sales', '38.01')
            ->assertJsonPath('data.credit_notes_total', '12.00')
            // Consumo 22.6130 (recetas) - 12.4710 (reversos por anulación y nota de crédito).
            ->assertJsonPath('data.theoretical_food_cost', '10.14')
            ->assertJsonPath('data.food_cost_percentage', '26.68')
            ->assertJsonPath('data.food_cost_target', '32.00')
            // Merma: 1/4 de pollo quemado (5.0710) + Inka Kola devuelta (7.4000).
            ->assertJsonPath('data.waste_cost', '12.47')
            ->assertJsonPath('data.orders_count', 2)
            ->assertJsonPath('data.average_ticket', '21.00')
            ->assertJsonPath('data.top_dishes.0.sku', 'BRA-003')
            ->assertJsonPath('data.top_dishes.0.quantity', '2.00')
            ->assertJsonPath('data.top_dishes.0.net_revenue', '38.01')
            ->assertJsonPath('data.top_dishes.0.unit_cost', '5.0710')
            ->assertJsonPath('data.top_dishes.0.contribution_margin', '27.87')
            ->assertJsonPath('data.sales_by_channel.0.channel', 'DINE_IN')
            ->assertJsonPath('data.sales_by_channel.0.total', '42.00')
            ->assertJsonPath('data.sales_by_payment_method.*.total', ['0.00', '12.00', '0.00', '42.00']);

        $this->waiter();
        $this->getJson('/api/v1/reports/dashboard-metrics')->assertForbidden();
    }

    #[Test]
    public function the_sire_file_follows_the_40_field_rvie_layout_with_negative_credit_notes(): void
    {
        $period = CarbonImmutable::now('America/Lima')->format('Ym');
        $response = $this->get("/api/v1/reports/sales-sire?period={$period}")->assertOk();

        $this->assertStringContainsString("filename=\"LE20609876543{$period}00140400021112".'01.txt"', (string) $response->headers->get('Content-Disposition'));

        $lines = array_values(array_filter(explode("\r\n", (string) $response->getContent())));
        $this->assertCount(3, $lines);

        foreach ($lines as $line) {
            $this->assertCount(41, explode('|', $line), 'RVIE: 40 campos terminados en "|".');
        }

        $note = explode('|', $lines[2]);
        $this->assertSame(['07', 'BC01', '1'], [$note[6], $note[7], $note[8]]);
        $this->assertSame('-10.86', $note[14]);
        $this->assertSame('-1.14', $note[16]);
        $this->assertSame('-12.00', $note[25]);
        $this->assertSame(['03', 'B001', '1'], [$note[29], $note[30], $note[31]]);

        $invoice = explode('|', $lines[0]);
        $this->assertSame(['01', 'F001', '1', '6', self::CUSTOMER_RUC], [$invoice[6], $invoice[7], $invoice[8], $invoice[10], $invoice[11]]);
        $this->assertSame(['38.01', '3.99', '42.00', 'PEN'], [$invoice[14], $invoice[16], $invoice[25], $invoice[26]]);
    }

    #[Test]
    public function the_kardex_131_export_and_the_waste_audit_trace_every_lost_ingredient(): void
    {
        $csv = (string) $this->get('/api/v1/reports/kardex-13-1')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
        $rows = array_map(static fn (string $line): array => str_getcsv($line, ',', '"', ''), array_values(array_filter(explode("\n", ltrim($csv, "\u{FEFF}")))));

        $this->assertSame('PERIODO', $rows[0][0]);
        $this->assertSame('SALDO COSTO TOTAL', $rows[0][22]);
        $this->assertContains('PROMEDIO PONDERADO', $rows[1]);
        $this->assertNotEmpty(array_filter($rows, static fn (array $row): bool => ($row[10] ?? '') === '07' && ($row[11] ?? '') === 'BC01' && ($row[13] ?? '') === '13'));

        $audit = $this->getJson('/api/v1/reports/waste-audit')
            ->assertOk()
            ->assertJsonPath('data.total_cost', '12.47')
            ->assertJsonPath('data.incidents', 2)
            ->json('data.rows');

        $burnt = $this->rowWhere($audit, 'ingredient.sku', 'POL-001');
        $this->assertSame('VOID', $burnt['origin'] ?? null);
        $this->assertSame('1/4 Pollo a la Brasa (Pecho/Pierna) x 1.00', $burnt['dish'] ?? null);
        $this->assertSame('0.2500', $burnt['quantity'] ?? null);
        $this->assertSame('3.63', $burnt['total_cost'] ?? null);
        $this->assertSame('Gerente General', $burnt['authorized_by'] ?? null);
        $this->assertSame('Se quemó en el horno', $burnt['reason'] ?? null);

        $returned = $this->rowWhere($audit, 'origin', 'CREDIT_NOTE');
        $this->assertSame('Nota de crédito BC01-00000001', $returned['dish'] ?? null);
        $this->assertSame('Cajera Principal', $returned['authorized_by'] ?? null);

        $this->get('/api/v1/reports/waste-audit?format=csv')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    #[Test]
    public function inventory_endpoints_expose_critical_stock_fractional_recipes_and_the_kardex(): void
    {
        $this->getJson('/api/v1/inventory/ingredients')
            ->assertOk()
            ->assertJsonPath('meta.critical_count', 0)
            ->assertJsonFragment(['sku' => 'POL-001', 'stock' => '119.2500', 'average_cost' => '14.5000', 'is_critical' => false]);

        $quarter = $this->rowWhere($this->getJson('/api/v1/inventory/recipes')->assertOk()->json('data'), 'sku', 'BRA-003');
        $this->assertSame('5.0710', $quarter['dine_in_cost'] ?? null);
        $this->assertSame('5.6210', $quarter['takeout_cost'] ?? null, 'En para llevar suma envase 1/4 (0.35) y bolsa (0.20).');
        $container = $this->rowWhere($quarter['lines'] ?? [], 'ingredient.sku', 'ENV-001');
        $this->assertSame(['0.0000', '1.0000'], [$container['dine_in_quantity'] ?? null, $container['takeout_quantity'] ?? null]);

        $this->getJson('/api/v1/inventory/kardex?movement_type=WASTE')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 4)
            ->assertJsonPath('data.0.sunat_operation_type', '13');

        $this->cashier();
        $this->getJson('/api/v1/inventory/ingredients')->assertForbidden();
    }

    /**
     * Fila de una respuesta JSON cuyo valor en $path coincide (falla si no existe).
     *
     * @return array<mixed>
     */
    private function rowWhere(mixed $rows, string $path, string $value): array
    {
        $this->assertIsArray($rows);

        foreach ($rows as $row) {
            if (is_array($row) && data_get($row, $path) === $value) {
                return $row;
            }
        }

        $this->fail("No hay una fila con {$path} = {$value}.");
    }
}
