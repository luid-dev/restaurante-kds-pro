<?php

declare(strict_types=1);

namespace App\DTOs\Company;

use App\Enums\PrinterConnection;
use InvalidArgumentException;

/**
 * Impresora térmica de comandas/precuentas registrada para la empresa.
 */
final readonly class PrinterData
{
    private const array PAPER_WIDTHS_MM = [58, 80];

    public function __construct(
        public string $code,
        public string $name,
        public PrinterConnection $connection,
        public ?string $host = null,
        public ?int $port = null,
        public int $paperWidthMm = 80,
    ) {
        if (preg_match('/^[A-Z0-9_-]{2,30}$/', $code) !== 1) {
            throw new InvalidArgumentException("Código de impresora inválido: [{$code}]. Use A-Z, 0-9, guion o guion bajo.");
        }

        if (trim($name) === '' || mb_strlen($name) > 60) {
            throw new InvalidArgumentException("La impresora [{$code}] requiere un nombre de 1 a 60 caracteres.");
        }

        if (! in_array($paperWidthMm, self::PAPER_WIDTHS_MM, true)) {
            throw new InvalidArgumentException("La impresora [{$code}] admite papel de 58 u 80 mm.");
        }

        if ($connection === PrinterConnection::Network && ! $this->isValidHost($host)) {
            throw new InvalidArgumentException("La impresora de red [{$code}] requiere una IP o hostname válido.");
        }

        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new InvalidArgumentException("Puerto inválido para la impresora [{$code}].");
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $connectionValue = SettingsInput::string($data, 'connection', PrinterConnection::Network->value);
        $connection = PrinterConnection::tryFrom($connectionValue)
            ?? throw new InvalidArgumentException("Tipo de conexión de impresora desconocido: [{$connectionValue}].");

        return new self(
            SettingsInput::string($data, 'code', ''),
            SettingsInput::string($data, 'name', ''),
            $connection,
            SettingsInput::nullableString($data, 'host'),
            SettingsInput::nullableInt($data, 'port') ?? ($connection === PrinterConnection::Network ? 9100 : null),
            SettingsInput::int($data, 'paper_width_mm', 80),
        );
    }

    /**
     * @return array{code: string, name: string, connection: string, host: string|null, port: int|null, paper_width_mm: int}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'connection' => $this->connection->value,
            'host' => $this->host,
            'port' => $this->port,
            'paper_width_mm' => $this->paperWidthMm,
        ];
    }

    private function isValidHost(?string $host): bool
    {
        if ($host === null) {
            return false;
        }

        return filter_var($host, FILTER_VALIDATE_IP) !== false
            || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }
}
