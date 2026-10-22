<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations;

use App\DTOs\Orders\CounterOrderData;
use App\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCounterOrderRequest extends FormRequest
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
            'order_type' => ['required', 'string', 'in:TAKEOUT,DELIVERY'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'delivery_address' => ['required_if:order_type,DELIVERY', 'nullable', 'string', 'max:255'],
            'delivery_phone' => ['required_if:order_type,DELIVERY', 'nullable', 'string', 'regex:/^\+?\d{6,15}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_type.in' => 'El pedido debe ser para llevar o delivery.',
            'delivery_address.required_if' => 'Ingrese la dirección de entrega.',
            'delivery_phone.required_if' => 'Ingrese el teléfono de contacto.',
            'delivery_phone.regex' => 'Teléfono inválido.',
        ];
    }

    public function toData(): CounterOrderData
    {
        return $this->buildData('order_type', fn (): CounterOrderData => new CounterOrderData(
            OrderType::from($this->string('order_type')->toString()),
            $this->filled('customer_name') ? $this->string('customer_name')->trim()->toString() : null,
            $this->filled('delivery_address') ? $this->string('delivery_address')->trim()->toString() : null,
            $this->filled('delivery_phone') ? $this->string('delivery_phone')->toString() : null,
        ));
    }
}
