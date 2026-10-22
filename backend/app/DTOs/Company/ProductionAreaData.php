<?php

declare(strict_types=1);

namespace App\DTOs\Company;

use App\Enums\ProductionArea;
use InvalidArgumentException;

/**
 * Área de producción habilitada (pantalla KDS y/o impresora de comandas).
 */
final readonly class ProductionAreaData
{
    public function __construct(
        public ProductionArea $area,
        public string $name,
        public ?string $printerCode = null,
        public bool $kdsEnabled = true,
        public int $targetMinutes = 15,
    ) {
        if (trim($name) === '' || mb_strlen($name) > 40) {
            throw new InvalidArgumentException("El área [{$area->value}] requiere un nombre de 1 a 40 caracteres.");
        }

        if ($targetMinutes < 1 || $targetMinutes > 240) {
            throw new InvalidArgumentException("El tiempo estándar del área [{$area->value}] debe estar entre 1 y 240 minutos.");
        }
    }

    public static function defaultFor(ProductionArea $area): self
    {
        return new self($area, $area->label(), targetMinutes: $area->defaultTargetMinutes());
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $areaValue = SettingsInput::string($data, 'area', '');
        $area = ProductionArea::tryFrom($areaValue)
            ?? throw new InvalidArgumentException("Área de producción desconocida: [{$areaValue}].");

        return new self(
            $area,
            SettingsInput::string($data, 'name', $area->label()),
            SettingsInput::nullableString($data, 'printer_code'),
            SettingsInput::bool($data, 'kds_enabled', true),
            SettingsInput::int($data, 'target_minutes', $area->defaultTargetMinutes()),
        );
    }

    /**
     * @return array{area: string, name: string, printer_code: string|null, kds_enabled: bool, target_minutes: int}
     */
    public function toArray(): array
    {
        return [
            'area' => $this->area->value,
            'name' => $this->name,
            'printer_code' => $this->printerCode,
            'kds_enabled' => $this->kdsEnabled,
            'target_minutes' => $this->targetMinutes,
        ];
    }
}
