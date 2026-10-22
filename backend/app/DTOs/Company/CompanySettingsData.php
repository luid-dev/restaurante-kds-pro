<?php

declare(strict_types=1);

namespace App\DTOs\Company;

use App\Enums\ProductionArea;
use DateTimeZone;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Configuración por empresa (columna JSONB companies.settings).
 *
 * Inmutable y siempre válida: toda instancia cumple las reglas tributarias y de
 * referencias entre áreas e impresoras. Los cambios se hacen con los métodos with*.
 */
final readonly class CompanySettingsData implements JsonSerializable
{
    private const array CURRENCIES = ['PEN', 'USD'];

    /**
     * @param  list<PrinterData>  $printers
     * @param  list<ProductionAreaData>  $productionAreas
     */
    public function __construct(
        public TaxSettingsData $tax,
        public ServiceChargeSettingsData $serviceCharge,
        public array $printers,
        public array $productionAreas,
        public BrandingData $branding,
        public string $currency = 'PEN',
        public string $timezone = 'America/Lima',
    ) {
        if (! in_array($currency, self::CURRENCIES, true)) {
            throw new InvalidArgumentException("Moneda no soportada: [{$currency}].");
        }

        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException("Zona horaria inválida: [{$timezone}].");
        }

        $printerCodes = array_map(static fn (PrinterData $printer): string => $printer->code, $printers);

        if (count($printerCodes) !== count(array_unique($printerCodes))) {
            throw new InvalidArgumentException('Los códigos de impresora deben ser únicos.');
        }

        $areas = array_map(static fn (ProductionAreaData $area): string => $area->area->value, $productionAreas);

        if (count($areas) !== count(array_unique($areas))) {
            throw new InvalidArgumentException('Cada área de producción solo puede configurarse una vez.');
        }

        foreach ($productionAreas as $area) {
            if ($area->printerCode !== null && ! in_array($area->printerCode, $printerCodes, true)) {
                throw new InvalidArgumentException("El área [{$area->area->value}] referencia una impresora inexistente: [{$area->printerCode}].");
            }
        }
    }

    /**
     * Valores de una pollería nueva: régimen general, sin recargo, áreas Horno/Parrilla, Cocina Caliente y Barra Fría.
     */
    public static function defaults(): self
    {
        return new self(
            TaxSettingsData::defaults(),
            ServiceChargeSettingsData::defaults(),
            printers: [],
            productionAreas: [
                ProductionAreaData::defaultFor(ProductionArea::GrillOven),
                ProductionAreaData::defaultFor(ProductionArea::HotKitchen),
                ProductionAreaData::defaultFor(ProductionArea::ColdBar),
            ],
            branding: new BrandingData,
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $defaults = self::defaults();

        return new self(
            tax: array_key_exists('tax', $data)
                ? TaxSettingsData::fromArray(SettingsInput::object($data, 'tax'))
                : $defaults->tax,
            serviceCharge: array_key_exists('service_charge', $data)
                ? ServiceChargeSettingsData::fromArray(SettingsInput::object($data, 'service_charge'))
                : $defaults->serviceCharge,
            printers: array_map(
                PrinterData::fromArray(...),
                SettingsInput::listOfObjects($data, 'printers'),
            ),
            productionAreas: array_key_exists('production_areas', $data)
                ? array_map(ProductionAreaData::fromArray(...), SettingsInput::listOfObjects($data, 'production_areas'))
                : $defaults->productionAreas,
            branding: array_key_exists('branding', $data)
                ? BrandingData::fromArray(SettingsInput::object($data, 'branding'))
                : $defaults->branding,
            currency: SettingsInput::string($data, 'currency', $defaults->currency),
            timezone: SettingsInput::string($data, 'timezone', $defaults->timezone),
        );
    }

    public function withTax(TaxSettingsData $tax): self
    {
        return new self($tax, $this->serviceCharge, $this->printers, $this->productionAreas, $this->branding, $this->currency, $this->timezone);
    }

    public function withServiceCharge(ServiceChargeSettingsData $serviceCharge): self
    {
        return new self($this->tax, $serviceCharge, $this->printers, $this->productionAreas, $this->branding, $this->currency, $this->timezone);
    }

    /**
     * Representación persistida (JSONB).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tax' => $this->tax->toArray(),
            'service_charge' => $this->serviceCharge->toArray(),
            'printers' => array_map(static fn (PrinterData $printer): array => $printer->toArray(), $this->printers),
            'production_areas' => array_map(static fn (ProductionAreaData $area): array => $area->toArray(), $this->productionAreas),
            'branding' => $this->branding->toArray(),
            'currency' => $this->currency,
            'timezone' => $this->timezone,
        ];
    }

    /**
     * Subconjunto apto para el cliente: omite la topología de red de las impresoras.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        $settings = $this->toArray();
        unset($settings['printers']);

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
