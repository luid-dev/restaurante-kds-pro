<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

final class OpenCashSessionRequest extends FormRequest
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
            'cash_register_id' => ['required', 'uuid'],
            'opening_amount' => ['required', 'decimal:0,2', 'min:0', 'max:99999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cash_register_id.required' => 'Elija la caja.',
            'opening_amount.required' => 'Ingrese el fondo de caja inicial.',
            'opening_amount.decimal' => 'El fondo admite hasta 2 decimales.',
        ];
    }
}
