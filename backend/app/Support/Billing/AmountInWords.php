<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Support\Decimal;
use InvalidArgumentException;

/**
 * Importe en letras para la leyenda 1000 del comprobante SUNAT:
 * "145.50" -> "CIENTO CUARENTA Y CINCO CON 50/100 SOLES".
 */
final class AmountInWords
{
    private const array UNITS = [
        '', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ',
        'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISEIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE',
        'VEINTIUNO', 'VEINTIDOS', 'VEINTITRES', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISEIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE',
    ];

    private const array TENS = ['', '', '', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];

    private const array HUNDREDS = [
        '', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
        'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS',
    ];

    /**
     * @param  numeric-string  $amount
     */
    public static function soles(string $amount): string
    {
        $normalized = Decimal::normalize($amount, 2);

        if (bccomp($normalized, '0', 2) < 0 || bccomp($normalized, '999999999.99', 2) > 0) {
            throw new InvalidArgumentException("Importe fuera de rango para la leyenda: [{$normalized}].");
        }

        [$integer, $cents] = explode('.', $normalized);
        $words = (int) $integer === 0 ? 'CERO' : self::number((int) $integer);

        return "{$words} CON {$cents}/100 SOLES";
    }

    private static function number(int $value): string
    {
        $millions = intdiv($value, 1_000_000);
        $thousands = intdiv($value % 1_000_000, 1000);
        $rest = $value % 1000;
        $parts = [];

        if ($millions > 0) {
            $parts[] = $millions === 1 ? 'UN MILLON' : self::apocope(self::hundreds($millions)).' MILLONES';
        }

        if ($thousands > 0) {
            $parts[] = $thousands === 1 ? 'MIL' : self::apocope(self::hundreds($thousands)).' MIL';
        }

        if ($rest > 0) {
            $parts[] = self::hundreds($rest);
        }

        return implode(' ', $parts);
    }

    private static function hundreds(int $value): string
    {
        if ($value === 100) {
            return 'CIEN';
        }

        $hundreds = intdiv($value, 100);
        $rest = $value % 100;
        $words = self::HUNDREDS[$hundreds];

        if ($rest === 0) {
            return $words;
        }

        return trim($words.' '.self::tens($rest));
    }

    private static function tens(int $value): string
    {
        if ($value < 30) {
            return self::UNITS[$value];
        }

        $tens = self::TENS[intdiv($value, 10)];
        $units = $value % 10;

        return $units === 0 ? $tens : "{$tens} Y ".self::UNITS[$units];
    }

    /**
     * "VEINTIUNO MIL" -> "VEINTIUN MIL", "TREINTA Y UNO MILLONES" -> "TREINTA Y UN MILLONES".
     */
    private static function apocope(string $words): string
    {
        return (string) preg_replace(['/VEINTIUNO$/', '/UNO$/'], ['VEINTIUN', 'UN'], $words);
    }
}
