<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations;

use App\DTOs\Billing\ChargeLineData;
use App\DTOs\Billing\ChargeRequestData;
use App\DTOs\Billing\CustomerData;
use App\DTOs\Billing\PaymentData;
use App\Enums\ChargeMode;
use App\Enums\ConsumptionFormat;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;

final class ChargeOrderRequest extends FormRequest
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
            'series' => ['required', 'string', 'regex:/^[A-Z0-9]{4}$/'],
            'format' => ['required', 'string', 'in:DETAILED,BY_CONSUMPTION'],
            'amount' => ['required_if:mode,AMOUNT', 'nullable', 'decimal:0,2', 'gt:0'],
            'lines' => ['required_if:mode,ITEMS', 'array', 'max:100'],
            'lines.*.order_item_id' => ['required', 'uuid'],
            'lines.*.quantity' => ['required', 'decimal:0,2', 'gt:0'],
            'customer' => ['present', 'array'],
            'customer.document_type' => ['required', 'string', 'in:0,1,4,6,7'],
            'customer.document_number' => ['nullable', 'string', 'max:15'],
            'customer.name' => ['nullable', 'string', 'max:200'],
            'customer.address' => ['nullable', 'string', 'max:255'],
            'payments' => ['required', 'array', 'min:1', 'max:6'],
            'payments.*.method' => ['required', 'string', 'in:CASH,YAPE,PLIN,CARD'],
            'payments.*.amount' => ['required', 'decimal:0,2', 'gt:0'],
            'payments.*.received_amount' => ['nullable', 'decimal:0,2'],
            'payments.*.reference' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'series.required' => 'Elija el comprobante a emitir.',
            'amount.required_if' => 'Indique el monto a cobrar.',
            'lines.required_if' => 'Seleccione los platos a cobrar.',
            'payments.required' => 'Registre al menos un medio de pago.',
            'payments.*.amount.decimal' => 'Los montos admiten hasta 2 decimales.',
        ];
    }

    public function toData(): ChargeRequestData
    {
        /** @var array{document_type: string, document_number?: string|null, name?: string|null, address?: string|null} $customer */
        $customer = $this->validated('customer');
        /** @var list<array{order_item_id: string, quantity: int|float|string}> $lines */
        $lines = $this->validated('lines', []);
        /** @var list<array{method: string, amount: int|float|string, received_amount?: int|float|string|null, reference?: string|null}> $payments */
        $payments = $this->validated('payments');

        $customerData = $this->buildData('customer.document_number', static fn (): CustomerData => new CustomerData(
            $customer['document_type'],
            self::clean($customer['document_number'] ?? null),
            self::clean($customer['name'] ?? null),
            self::clean($customer['address'] ?? null),
        ));

        $paymentData = $this->buildData('payments', static fn (): array => array_map(static fn (array $payment): PaymentData => new PaymentData(
            PaymentMethod::from($payment['method']),
            (string) $payment['amount'],
            isset($payment['received_amount']) ? (string) $payment['received_amount'] : null,
            self::clean($payment['reference'] ?? null),
        ), $payments));

        $lineData = array_map(static fn (array $line): ChargeLineData => new ChargeLineData($line['order_item_id'], (string) $line['quantity']), $lines);

        return $this->buildData('mode', fn (): ChargeRequestData => new ChargeRequestData(
            ChargeMode::from($this->string('mode')->toString()),
            $this->string('series')->toString(),
            ConsumptionFormat::from($this->string('format')->toString()),
            $customerData,
            $paymentData,
            $lineData,
            $this->filled('amount') ? $this->string('amount')->toString() : null,
        ));
    }

    private static function clean(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
