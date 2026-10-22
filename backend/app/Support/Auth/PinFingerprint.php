<?php

declare(strict_types=1);

namespace App\Support\Auth;

use InvalidArgumentException;
use RuntimeException;

/**
 * Huella HMAC-SHA256 del PIN por empresa. Permite ubicar al usuario en O(1) y
 * exigir PIN único por empresa sin guardar el PIN en claro. Sin el pepper
 * (PIN_PEPPER, fuera de la base de datos) la huella no permite recuperar el PIN.
 */
final class PinFingerprint
{
    public const int LENGTH = 6;

    private const string PATTERN = '/^\d{6}$/';

    public static function isValidFormat(string $pin): bool
    {
        return preg_match(self::PATTERN, $pin) === 1;
    }

    public static function make(int $companyId, string $pin): string
    {
        if (! self::isValidFormat($pin)) {
            throw new InvalidArgumentException('El PIN debe tener exactamente 6 dígitos.');
        }

        return hash_hmac('sha256', $companyId.':'.$pin, self::pepper());
    }

    private static function pepper(): string
    {
        $pepper = config('auth.pin.pepper');

        if (! is_string($pepper) || strlen($pepper) < 16) {
            throw new RuntimeException('PIN_PEPPER no está configurado (mínimo 16 caracteres).');
        }

        return $pepper;
    }
}
