<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations;

use App\DTOs\Billing\ChargeLineData;
use App\Enums\ChargeMode;
use App\Enums\ConsumptionFormat;
use App\Support\Decimal;
use Illuminate\Foundation\Http\FormRequest;

final class QuoteChargeRequest extends FormRequest
{
    use BuildsDomainData;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', 'in:FULL,ITEMS,AMOUNT'],
            'format' => ['required', 'string', 'in:DETAILED,BY_CONSUMPTION'],
            'amount' => ['required_if:mode,AMOUNT', 'nullable', 'decimal:0,2', 'gt:0'],
            'lines' => ['required_if:mode,ITEMS', 'array', 'max:100'],
            'lines.*.order_item_id' => ['required', 'uuid'],
            'lines.*.quantity' => ['required', 'decimal:0,2', 'gt:0'],
        ];
    }

    public function chargeMode(): ChargeMode
    {
        return ChargeMode::from($this->string('mode')->toString());
    }

    public function consumptionFormat(): ConsumptionFormat
    {
        return ConsumptionFormat::from($this->string('format')->toString());
    }

    /**
     * @return numeric-string|null
     */
    public function chargeAmount(): ?string
    {
        return $this->filled('amount') ? Decimal::normalize($this->string('amount')->toString(), 2) : null;
    }

    /**
     * @return list<ChargeLineData>
     */
    public function chargeLines(): array
    {
        /** @var list<array{order_item_id: string, quantity: int|float|string}> $lines */
        $lines = $this->validated('lines', []);

        return $this->buildData('lines', static fn (): array => array_map(static fn (array $line): ChargeLineData => new ChargeLineData($line['order_item_id'], (string) $line['quantity']), $lines));
    }
}
