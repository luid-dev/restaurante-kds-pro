<?php

declare(strict_types=1);

namespace App\DTOs\Reports;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Rango de fechas de negocio (zona horaria de la empresa) convertido a instantes
 * UTC [inicio, fin) para filtrar columnas timestamptz sin perder el último día.
 */
final readonly class ReportPeriod
{
    public const int MAX_DAYS = 366;

    public CarbonImmutable $start;

    public CarbonImmutable $end;

    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        string $timezone,
    ) {
        if ($to->lessThan($from)) {
            throw new InvalidArgumentException('La fecha final no puede ser anterior a la inicial.');
        }

        if ($from->diffInDays($to) >= self::MAX_DAYS) {
            throw new InvalidArgumentException('El rango máximo es de '.self::MAX_DAYS.' días.');
        }

        $this->start = CarbonImmutable::parse($from->toDateString(), $timezone)->startOfDay()->utc();
        $this->end = CarbonImmutable::parse($to->toDateString(), $timezone)->addDay()->startOfDay()->utc();
    }

    public static function fromDates(string $from, string $to, string $timezone): self
    {
        return new self(CarbonImmutable::createFromFormat('!Y-m-d', $from, $timezone) ?: throw new InvalidArgumentException("Fecha inválida: {$from}."), CarbonImmutable::createFromFormat('!Y-m-d', $to, $timezone) ?: throw new InvalidArgumentException("Fecha inválida: {$to}."), $timezone);
    }

    /**
     * Mes tributario completo ("202610") para el registro de ventas SIRE.
     */
    public static function fromTaxPeriod(string $period, string $timezone): self
    {
        $first = CarbonImmutable::createFromFormat('!Ym', $period, $timezone) ?: throw new InvalidArgumentException("Periodo inválido: {$period}.");

        return new self($first, $first->endOfMonth()->startOfDay(), $timezone);
    }

    /**
     * @return array{from: string, to: string}
     */
    public function toArray(): array
    {
        return ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()];
    }
}
