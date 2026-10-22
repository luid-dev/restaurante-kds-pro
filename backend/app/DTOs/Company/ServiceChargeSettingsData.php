<?php

declare(strict_types=1);

namespace App\DTOs\Company;

use App\Enums\OrderType;
use App\Support\Decimal;
use InvalidArgumentException;

/**
 * Recargo al consumo: no forma parte de la base imponible del IGV y se informa
 * en el comprobante como cargo (código SUNAT 46).
 */
final readonly class ServiceChargeSettingsData
{
    /**
     * @var numeric-string
     */
    public string $rate;

    /**
     * @var list<OrderType>
     */
    public array $appliesTo;

    /**
     * @param  list<OrderType>  $appliesTo
     */
    public function __construct(
        public bool $enabled,
        string $rate,
        array $appliesTo,
    ) {
        $rate = Decimal::normalize($rate);

        if (! Decimal::isBetween($rate, '0.00', '20.00')) {
            throw new InvalidArgumentException('El recargo al consumo debe estar entre 0% y 20%.');
        }

        if ($enabled && bccomp($rate, '0', 2) === 0) {
            throw new InvalidArgumentException('Con el recargo al consumo activo, la tasa debe ser mayor a 0%.');
        }

        $unique = [];

        foreach ($appliesTo as $orderType) {
            $unique[$orderType->value] = $orderType;
        }

        if ($enabled && $unique === []) {
            throw new InvalidArgumentException('El recargo al consumo activo debe aplicar al menos a un tipo de pedido.');
        }

        $this->rate = $rate;
        $this->appliesTo = array_values($unique);
    }

    public static function defaults(): self
    {
        return new self(false, '10.00', [OrderType::DineIn]);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $appliesTo = array_map(
            static fn (string $value): OrderType => OrderType::tryFrom($value)
                ?? throw new InvalidArgumentException("Tipo de pedido desconocido: [{$value}]."),
            SettingsInput::listOfStrings($data, 'applies_to'),
        );

        return new self(
            SettingsInput::bool($data, 'enabled', false),
            SettingsInput::decimal($data, 'rate', '10.00'),
            array_key_exists('applies_to', $data) ? $appliesTo : [OrderType::DineIn],
        );
    }

    /**
     * @return array{enabled: bool, rate: string, applies_to: list<string>}
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'rate' => $this->rate,
            'applies_to' => array_map(static fn (OrderType $type): string => $type->value, $this->appliesTo),
        ];
    }
}
