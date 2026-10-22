<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations;

use App\DTOs\Orders\OrderLineData;
use Illuminate\Foundation\Http\FormRequest;

final class SendOrderItemsRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1', 'max:60'],
            'items.*.menu_item_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'decimal:0,2', 'gt:0', 'max:99'],
            'items.*.modifiers' => ['present', 'array', 'max:10'],
            'items.*.modifiers.*' => ['string', 'distinct', 'max:60'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Agregue al menos un plato a la comanda.',
            'items.*.quantity.decimal' => 'La cantidad admite hasta 2 decimales.',
            'items.*.quantity.gt' => 'La cantidad debe ser mayor a cero.',
            'items.*.quantity.max' => 'La cantidad máxima por línea es 99.',
            'items.*.notes.max' => 'La nota para cocina admite hasta 255 caracteres.',
        ];
    }

    /**
     * @return list<OrderLineData>
     */
    public function toLines(): array
    {
        /** @var list<array{menu_item_id: string, quantity: int|float|string, modifiers: list<string>, notes?: string|null}> $items */
        $items = $this->validated('items');

        return $this->buildData('items', static fn (): array => array_map(static fn (array $item): OrderLineData => new OrderLineData(
            $item['menu_item_id'],
            (string) $item['quantity'],
            $item['modifiers'],
            isset($item['notes']) && trim($item['notes']) !== '' ? trim($item['notes']) : null,
        ), $items));
    }
}
