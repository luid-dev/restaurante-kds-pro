<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\TaxAffectationType;
use App\Models\Sale;
use App\Models\SaleItem;
use Database\Seeders\RestaurantDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithRestaurant;
use Tests\TestCase;

/**
 * Platos gravados (10), exonerados (20, tributo 9997 EXO) e inafectos (30,
 * tributo 9998 INA) en una misma cuenta, y domicilio fiscal completo del emisor.
 */
final class TaxAffectationTest extends TestCase
{
    use InteractsWithRestaurant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRestaurant();
        $this->createMenuItem('EXO-001', 'Fruta de Estación', '8.00', TaxAffectationType::Exempt);
        $this->createMenuItem('INA-001', 'Donación Olla Común', '5.00', TaxAffectationType::Unaffected);
    }

    #[Test]
    public function a_detailed_receipt_keeps_each_dish_affectation_and_taxes_only_the_taxed_ones(): void
    {
        $order = $this->openTable('M-07');
        $this->sendItems($order, [
            ['sku' => 'BRA-003', 'quantity' => '1'],
            ['sku' => 'EXO-001', 'quantity' => '1'],
            ['sku' => 'INA-001', 'quantity' => '1'],
        ])->assertOk()
            ->assertJsonPath('data.totals.subtotal', '34.00')
            ->assertJsonPath('data.totals.tax_amount', '2.00')
            ->assertJsonPath('data.totals.total_amount', '34.00');

        $this->openCashSession();
        $response = $this->charge($order, [
            'mode' => 'FULL', 'series' => 'B001', 'format' => 'DETAILED', 'customer' => ['document_type' => '0'],
            'payments' => [['method' => 'CASH', 'amount' => '34.00']],
        ])->assertCreated()
            ->assertJsonPath('data.totals.taxable_amount', '19.00')
            ->assertJsonPath('data.totals.exempt_amount', '8.00')
            ->assertJsonPath('data.totals.unaffected_amount', '5.00')
            ->assertJsonPath('data.totals.igv_amount', '2.00')
            ->assertJsonPath('data.items.*.tax_affectation_type', ['10', '20', '30']);

        $sale = Sale::query()->with('items')->where('uuid', $response->json('data.id'))->sole();
        $this->assertSame(['0.00', '0.00'], $sale->items->filter(static fn (SaleItem $item): bool => ! $item->tax_affectation_type->isTaxed())->pluck('igv_amount')->values()->all());

        $xml = (string) Storage::disk('local')->get((string) $sale->sunat_xml_path);
        $this->assertStringContainsString('<cbc:TaxExemptionReasonCode>20</cbc:TaxExemptionReasonCode>', $xml);
        $this->assertStringContainsString('<cbc:TaxExemptionReasonCode>30</cbc:TaxExemptionReasonCode>', $xml);
        $this->assertStringContainsString('<cbc:ID>9997</cbc:ID>', $xml);
        $this->assertStringContainsString('<cbc:Name>EXO</cbc:Name>', $xml);
        $this->assertStringContainsString('<cbc:ID>9998</cbc:ID>', $xml);
        $this->assertStringContainsString('<cbc:Name>INA</cbc:Name>', $xml);
        $this->assertStringContainsString('<cbc:LineExtensionAmount currencyID="PEN">32.00</cbc:LineExtensionAmount>', $xml);
        $this->assertStringContainsString('<cbc:PayableAmount currencyID="PEN">34.00</cbc:PayableAmount>', $xml);
    }

    #[Test]
    public function a_by_consumption_invoice_splits_the_gloss_by_affectation_and_prorates_amount_splits(): void
    {
        $order = $this->openTable('M-08', 2);
        $this->sendItems($order, [
            ['sku' => 'BRA-003', 'quantity' => '1'],
            ['sku' => 'EXO-001', 'quantity' => '1'],
            ['sku' => 'INA-001', 'quantity' => '1'],
        ])->assertOk();
        $this->openCashSession();

        // Mitad de 34.00 por monto: 21/34, 8/34 y el residuo inafecto.
        $this->charge($order, [
            'mode' => 'AMOUNT', 'amount' => '17.00', 'series' => 'F001', 'format' => 'BY_CONSUMPTION',
            'customer' => $this->invoiceCustomer(), 'payments' => [['method' => 'YAPE', 'amount' => '17.00']],
        ])->assertCreated()
            ->assertJsonPath('data.items.*.description', [
                'POR CONSUMO DE ALIMENTOS Y BEBIDAS',
                'POR CONSUMO DE ALIMENTOS Y BEBIDAS - EXONERADO',
                'POR CONSUMO DE ALIMENTOS Y BEBIDAS - INAFECTO',
            ])
            ->assertJsonPath('data.items.*.line_total', ['10.50', '4.00', '2.50'])
            ->assertJsonPath('data.totals.taxable_amount', '9.50')
            ->assertJsonPath('data.totals.igv_amount', '1.00');

        $this->charge($order, [
            'mode' => 'AMOUNT', 'amount' => '17.00', 'series' => 'B001', 'format' => 'BY_CONSUMPTION',
            'customer' => ['document_type' => '0'], 'payments' => [['method' => 'CASH', 'amount' => '17.00']],
        ])->assertCreated()->assertJsonPath('data.order.balance', '0.00');

        $this->assertSame('34.00', (string) Sale::query()->sum('total_amount'));
        $this->assertSame('8.00', (string) Sale::query()->sum('exempt_amount'), 'Las dos mitades suman exactamente el plato exonerado.');
    }

    #[Test]
    public function the_xml_carries_the_complete_fiscal_address_of_the_issuer(): void
    {
        $order = $this->openTable('M-09');
        $this->sendItems($order, [['sku' => 'BEB-002', 'quantity' => '1']])->assertOk();
        $this->openCashSession();

        $response = $this->charge($order, [
            'mode' => 'FULL', 'series' => 'B001', 'format' => 'DETAILED', 'customer' => ['document_type' => '0'],
            'payments' => [['method' => 'CASH', 'amount' => '4.50']],
        ])->assertCreated()->assertJsonPath('data.issuer.fiscal_address', 'Av. El Sol 520, CUSCO - CUSCO - CUSCO');

        $xml = (string) Storage::disk('local')->get((string) Sale::query()->where('uuid', $response->json('data.id'))->sole()->sunat_xml_path);
        $address = RestaurantDemoSeeder::FISCAL_ADDRESS;

        $this->assertStringContainsString("<cbc:ID>{$address['ubigeo']}</cbc:ID>", $xml);
        $this->assertStringContainsString('<cbc:AddressTypeCode>0000</cbc:AddressTypeCode>', $xml);
        $this->assertStringContainsString('<cbc:CitySubdivisionName>CENTRO HISTORICO</cbc:CitySubdivisionName>', $xml);
        $this->assertStringContainsString('<cbc:CityName>CUSCO</cbc:CityName>', $xml);
        $this->assertStringContainsString('<cbc:CountrySubentity>CUSCO</cbc:CountrySubentity>', $xml);
        $this->assertStringContainsString('<cbc:District>CUSCO</cbc:District>', $xml);
        $this->assertStringContainsString('AV. EL SOL 520', $xml);
    }

    #[Test]
    public function an_incomplete_fiscal_address_blocks_electronic_documents_without_burning_a_correlative(): void
    {
        $this->company->update(['department' => null]);
        $order = $this->openTable('M-10');
        $this->sendItems($order, [['sku' => 'BEB-002', 'quantity' => '1']])->assertOk();
        $this->openCashSession();

        $payload = ['mode' => 'FULL', 'series' => 'B001', 'format' => 'DETAILED', 'customer' => ['document_type' => '0'], 'payments' => [['method' => 'CASH', 'amount' => '4.50']]];

        $this->charge($order, $payload)->assertServiceUnavailable()->assertJsonPath('code', 'SERVICE_UNAVAILABLE');
        $this->assertSame(0, Sale::query()->count());

        $this->company->update(['department' => 'CUSCO']);
        $this->charge($order, $payload)->assertCreated()->assertJsonPath('data.document_number', 'B001-00000001');
    }
}
