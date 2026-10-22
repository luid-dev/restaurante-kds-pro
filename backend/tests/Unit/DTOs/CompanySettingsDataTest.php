<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\Company\CompanySettingsData;
use App\Enums\IgvRegime;
use App\Enums\OrderType;
use App\Enums\ProductionArea;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CompanySettingsDataTest extends TestCase
{
    #[Test]
    public function defaults_use_general_regime_without_service_charge(): void
    {
        $settings = CompanySettingsData::defaults();

        $this->assertSame(IgvRegime::General, $settings->tax->regime);
        $this->assertSame('18.00', $settings->tax->igvRate);
        $this->assertFalse($settings->serviceCharge->enabled);
        $this->assertSame([OrderType::DineIn], $settings->serviceCharge->appliesTo);
        $this->assertSame(
            [ProductionArea::GrillOven, ProductionArea::HotKitchen, ProductionArea::ColdBar],
            array_map(static fn ($area) => $area->area, $settings->productionAreas),
        );
        $this->assertSame('America/Lima', $settings->timezone);
    }

    #[Test]
    public function mype_restaurant_rate_is_normalized_with_bcmath(): void
    {
        $settings = CompanySettingsData::fromArray([
            'tax' => ['regime' => 'MYPE_RESTAURANT', 'igv_rate' => '10.5'],
            'service_charge' => ['enabled' => true, 'rate' => 10, 'applies_to' => ['DINE_IN', 'DINE_IN']],
        ]);

        $this->assertSame('10.50', $settings->tax->igvRate);
        $this->assertSame('10.00', $settings->serviceCharge->rate);
        $this->assertSame([OrderType::DineIn], $settings->serviceCharge->appliesTo);
    }

    #[Test]
    public function round_trip_preserves_every_value(): void
    {
        $payload = [
            'tax' => ['regime' => 'MYPE_RESTAURANT', 'igv_rate' => '10.50', 'prices_include_tax' => true],
            'service_charge' => ['enabled' => true, 'rate' => '10.00', 'applies_to' => ['DINE_IN']],
            'printers' => [
                ['code' => 'PRN-HORNO', 'name' => 'Horno', 'connection' => 'NETWORK', 'host' => '192.168.1.50', 'port' => 9100, 'paper_width_mm' => 80],
            ],
            'production_areas' => [
                ['area' => 'GRILL_OVEN', 'name' => 'Horno', 'printer_code' => 'PRN-HORNO', 'kds_enabled' => true, 'target_minutes' => 25],
            ],
            'branding' => ['accent_color' => '#18181B', 'logo_path' => null],
            'currency' => 'PEN',
            'timezone' => 'America/Lima',
        ];

        $this->assertSame($payload, CompanySettingsData::fromArray($payload)->toArray());
    }

    #[Test]
    public function public_representation_hides_printer_network_topology(): void
    {
        $settings = CompanySettingsData::fromArray([
            'printers' => [['code' => 'PRN-BAR', 'name' => 'Barra', 'connection' => 'NETWORK', 'host' => '10.0.0.20']],
        ]);

        $this->assertArrayNotHasKey('printers', $settings->toPublicArray());
        $this->assertSame(9100, $settings->printers[0]->port);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidSettings(): iterable
    {
        yield 'régimen general con tasa distinta a 18%' => [['tax' => ['regime' => 'GENERAL', 'igv_rate' => '10.00']]];
        yield 'tasa MYPE fuera de rango' => [['tax' => ['regime' => 'MYPE_RESTAURANT', 'igv_rate' => '18.00']]];
        yield 'tasa no numérica' => [['tax' => ['regime' => 'MYPE_RESTAURANT', 'igv_rate' => 'diez']]];
        yield 'recargo activo en cero' => [['service_charge' => ['enabled' => true, 'rate' => '0']]];
        yield 'recargo excesivo' => [['service_charge' => ['enabled' => false, 'rate' => '45.00']]];
        yield 'área con impresora inexistente' => [['production_areas' => [['area' => 'COLD_BAR', 'printer_code' => 'NO-EXISTE']]]];
        yield 'área duplicada' => [['production_areas' => [['area' => 'COLD_BAR'], ['area' => 'COLD_BAR']]]];
        yield 'área desconocida' => [['production_areas' => [['area' => 'SUSHI']]]];
        yield 'impresora de red sin host' => [['printers' => [['code' => 'PRN-1', 'name' => 'Cocina', 'connection' => 'NETWORK']]]];
        yield 'color de marca inválido' => [['branding' => ['accent_color' => 'red']]];
        yield 'moneda no soportada' => [['currency' => 'EUR']];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[Test]
    #[DataProvider('invalidSettings')]
    public function invalid_settings_are_rejected(array $payload): void
    {
        $this->expectException(InvalidArgumentException::class);

        CompanySettingsData::fromArray($payload);
    }
}
