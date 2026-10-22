<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

final class OpenTableRequest extends FormRequest
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
            'diners_count' => ['required', 'integer', 'between:1,99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'diners_count.required' => 'Indique la cantidad de comensales.',
            'diners_count.between' => 'Los comensales deben estar entre 1 y 99.',
        ];
    }
}
