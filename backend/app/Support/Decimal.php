<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use RoundingMode;

/**
 * Aritmética decimal exacta con BCMath. Los montos viajan como string
 * ("12.50", "0.2500"); nunca se usan floats para dinero ni cantidades.
 *
 * bcmul/bcdiv truncan: todo resultado monetario pasa por round() (half-up).
 */
final class Decimal
{
    private const string PATTERN = '/^-?\d+(\.\d+)?$/';

    /** Escala interna para productos y divisiones antes de redondear. */
    private const int WORKING_SCALE = 10;

    /**
     * Solo notación decimal plana: rechaza exponentes ("1e3"), espacios y signos "+".
     *
     * @phpstan-assert-if-true numeric-string $value
     */
    public static function isNumeric(string $value): bool
    {
        return is_numeric($value) && preg_match(self::PATTERN, $value) === 1;
    }

    /**
     * Normaliza a la escala indicada redondeando half-up ("10.5" -> "10.50").
     *
     * @return numeric-string
     */
    public static function normalize(string $value, int $scale = 2): string
    {
        $value = trim($value);

        if (! self::isNumeric($value)) {
            throw new InvalidArgumentException("Valor decimal inválido: [{$value}].");
        }

        return bcround($value, $scale, RoundingMode::HalfAwayFromZero);
    }

    /**
     * @param  numeric-string  $value
     * @return numeric-string
     */
    public static function round(string $value, int $scale = 2): string
    {
        return bcround($value, $scale, RoundingMode::HalfAwayFromZero);
    }

    /**
     * @param  numeric-string  $a
     * @param  numeric-string  $b
     * @return numeric-string
     */
    public static function add(string $a, string $b, int $scale = 4): string
    {
        return bcadd($a, $b, $scale);
    }

    /**
     * @param  numeric-string  $a
     * @param  numeric-string  $b
     * @return numeric-string
     */
    public static function sub(string $a, string $b, int $scale = 4): string
    {
        return bcsub($a, $b, $scale);
    }

    /**
     * Producto redondeado half-up a la escala indicada.
     *
     * @param  numeric-string  $a
     * @param  numeric-string  $b
     * @return numeric-string
     */
    public static function mul(string $a, string $b, int $scale = 4): string
    {
        return self::round(bcmul($a, $b, self::WORKING_SCALE), $scale);
    }

    /**
     * Cociente redondeado half-up a la escala indicada.
     *
     * @param  numeric-string  $a
     * @param  numeric-string  $b
     * @return numeric-string
     */
    public static function div(string $a, string $b, int $scale = 4): string
    {
        if (bccomp($b, '0', self::WORKING_SCALE) === 0) {
            throw new InvalidArgumentException('División entre cero.');
        }

        return self::round(bcdiv($a, $b, self::WORKING_SCALE), $scale);
    }

    /**
     * @param  numeric-string  $value
     * @param  numeric-string  $min
     * @param  numeric-string  $max
     */
    public static function isBetween(string $value, string $min, string $max, int $scale = 4): bool
    {
        return bccomp($value, $min, $scale) >= 0 && bccomp($value, $max, $scale) <= 0;
    }
}
