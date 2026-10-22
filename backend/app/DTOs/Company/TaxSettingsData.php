<?php

declare(strict_types=1);

namespace App\DTOs\Company;

use App\Enums\IgvRegime;
use App\Support\Decimal;
use InvalidArgumentException;

/**
 * Configuración tributaria. `igvRate` es la tasa total del comprobante (IGV + IPM).
 */
final readonly class TaxSettingsData
{
    /**
     * @var numeric-string
     */
    public string $igvRate;

    public function __construct(
        public IgvRegime $regime,
        string $igvRate,
        public bool $pricesIncludeTax = true,
    ) {
        $rate = Decimal::normalize($igvRate);

        if ($regime === IgvRegime::General && $rate !== IgvRegime::GENERAL_RATE) {
            throw new InvalidArgumentException('El régimen general exige una tasa de IGV de 18.00%.');
        }

        if ($regime === IgvRegime::MypeRestaurant && ! Decimal::isBetween($rate, '0.01', '17.99')) {
            throw new InvalidArgumentException('La tasa del régimen MYPE debe estar entre 0.01% y 17.99%.');
        }

        $this->igvRate = $rate;
    }

    public static function defaults(): self
    {
        return new self(IgvRegime::General, IgvRegime::GENERAL_RATE);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $regimeValue = SettingsInput::string($data, 'regime', IgvRegime::General->value);
        $regime = IgvRegime::tryFrom($regimeValue)
            ?? throw new InvalidArgumentException("Régimen de IGV desconocido: [{$regimeValue}].");

        return new self(
            $regime,
            SettingsInput::decimal($data, 'igv_rate', $regime->suggestedRate()),
            SettingsInput::bool($data, 'prices_include_tax', true),
        );
    }

    /**
     * @return array{regime: string, igv_rate: string, prices_include_tax: bool}
     */
    public function toArray(): array
    {
        return [
            'regime' => $this->regime->value,
            'igv_rate' => $this->igvRate,
            'prices_include_tax' => $this->pricesIncludeTax,
        ];
    }
}
