<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations;

use App\Enums\OrderItemStatus;
use Illuminate\Foundation\Http\FormRequest;

final class AdvanceKitchenItemsRequest extends FormRequest
{
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
            'item_ids' => ['required', 'array', 'min:1', 'max:100'],
            'item_ids.*' => ['required', 'uuid', 'distinct'],
            'status' => ['required', 'string', 'in:PREPARING,READY,SERVED'],
        ];
    }

    /**
     * @return list<string>
     */
    public function itemUuids(): array
    {
        /** @var list<string> $ids */
        $ids = $this->validated('item_ids');

        return $ids;
    }

    public function target(): OrderItemStatus
    {
        return OrderItemStatus::from($this->string('status')->toString());
    }
}
