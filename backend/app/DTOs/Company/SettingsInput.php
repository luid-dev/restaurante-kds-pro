<?php

declare(strict_types=1);

namespace App\DTOs\Company;

use InvalidArgumentException;

/**
 * Lectura tipada del JSON de companies.settings. Las claves ausentes toman el
 * valor por defecto; los tipos incorrectos se rechazan en lugar de coaccionarse.
 *
 * @internal
 */
final class SettingsInput
{
    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function string(array $data, string $key, string $default): string
    {
        $value = $data[$key] ?? $default;

        if (! is_string($value)) {
            throw self::invalid($key, 'texto');
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if ($value !== null && ! is_string($value)) {
            throw self::invalid($key, 'texto o null');
        }

        return $value === '' ? null : $value;
    }

    /**
     * Acepta montos como string o número JSON y los devuelve como string para BCMath.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function decimal(array $data, string $key, string $default): string
    {
        $value = $data[$key] ?? $default;

        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            default => throw self::invalid($key, 'decimal'),
        };
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function bool(array $data, string $key, bool $default): bool
    {
        $value = $data[$key] ?? $default;

        if (! is_bool($value)) {
            throw self::invalid($key, 'booleano');
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function int(array $data, string $key, int $default): int
    {
        $value = $data[$key] ?? $default;

        if (! is_int($value)) {
            throw self::invalid($key, 'entero');
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        if ($value !== null && ! is_int($value)) {
            throw self::invalid($key, 'entero o null');
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function object(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        if (! is_array($value)) {
            throw self::invalid($key, 'objeto');
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<array<array-key, mixed>>
     */
    public static function listOfObjects(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        if (! is_array($value) || ! array_is_list($value)) {
            throw self::invalid($key, 'lista');
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_array($item)) {
                throw self::invalid($key, 'lista de objetos');
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    public static function listOfStrings(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        if (! is_array($value) || ! array_is_list($value)) {
            throw self::invalid($key, 'lista');
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw self::invalid($key, 'lista de textos');
            }

            $items[] = $item;
        }

        return $items;
    }

    private static function invalid(string $key, string $expected): InvalidArgumentException
    {
        return new InvalidArgumentException("settings.{$key} debe ser {$expected}.");
    }
}
