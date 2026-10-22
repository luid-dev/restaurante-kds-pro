<?php

declare(strict_types=1);

namespace App\Support\Billing;

/**
 * RUC peruano: 11 dígitos, prefijo de contribuyente válido y dígito verificador (módulo 11).
 */
final class Ruc
{
    private const array WEIGHTS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    private const array PREFIXES = ['10', '15', '16', '17', '20'];

    public static function isValid(string $ruc): bool
    {
        if (preg_match('/^\d{11}$/', $ruc) !== 1 || ! in_array(substr($ruc, 0, 2), self::PREFIXES, true)) {
            return false;
        }

        $sum = 0;

        foreach (self::WEIGHTS as $index => $weight) {
            $sum += (int) $ruc[$index] * $weight;
        }

        $check = 11 - ($sum % 11);
        $expected = match ($check) {
            10 => 0,
            11 => 1,
            default => $check,
        };

        return (int) $ruc[10] === $expected;
    }
}
