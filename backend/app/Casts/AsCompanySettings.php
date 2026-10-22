<?php

declare(strict_types=1);

namespace App\Casts;

use App\DTOs\Company\CompanySettingsData;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Cast JSONB <-> CompanySettingsData. Persistir siempre pasa por el DTO, de modo
 * que la base de datos nunca recibe una configuración inválida.
 *
 * @implements CastsAttributes<CompanySettingsData, CompanySettingsData|array<array-key, mixed>>
 */
final class AsCompanySettings implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): CompanySettingsData
    {
        if ($value === null || $value === '') {
            return CompanySettingsData::defaults();
        }

        $decoded = is_string($value) ? json_decode($value, true, 32, JSON_THROW_ON_ERROR) : $value;

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('companies.settings debe ser un objeto JSON.');
        }

        return CompanySettingsData::fromArray($decoded);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $settings = match (true) {
            $value instanceof CompanySettingsData => $value,
            is_array($value) => CompanySettingsData::fromArray($value),
            default => throw new InvalidArgumentException('companies.settings acepta CompanySettingsData o un arreglo.'),
        };

        return json_encode($settings->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
