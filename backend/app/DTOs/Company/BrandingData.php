<?php

declare(strict_types=1);

namespace App\DTOs\Company;

use InvalidArgumentException;

/**
 * Personalización de marca blanca por restaurante.
 */
final readonly class BrandingData
{
    public ?string $accentColor;

    public function __construct(
        ?string $accentColor = null,
        public ?string $logoPath = null,
    ) {
        if ($accentColor !== null && preg_match('/^#[0-9A-Fa-f]{6}$/', $accentColor) !== 1) {
            throw new InvalidArgumentException('El color de marca debe tener formato hexadecimal #RRGGBB.');
        }

        if ($logoPath !== null && (str_contains($logoPath, '..') || mb_strlen($logoPath) > 255)) {
            throw new InvalidArgumentException('Ruta de logotipo inválida.');
        }

        $this->accentColor = $accentColor !== null ? strtoupper($accentColor) : null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            SettingsInput::nullableString($data, 'accent_color'),
            SettingsInput::nullableString($data, 'logo_path'),
        );
    }

    /**
     * @return array{accent_color: string|null, logo_path: string|null}
     */
    public function toArray(): array
    {
        return [
            'accent_color' => $this->accentColor,
            'logo_path' => $this->logoPath,
        ];
    }
}
