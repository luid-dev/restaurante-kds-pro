<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class PinLoginRequest extends FormRequest
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
            'company_ruc' => ['required', 'string', 'digits:11'],
            'pin_code' => ['required', 'string', 'digits:6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_ruc.required' => 'Ingrese el RUC de la empresa.',
            'company_ruc.string' => 'El RUC debe tener 11 dígitos.',
            'company_ruc.digits' => 'El RUC debe tener 11 dígitos.',
            'pin_code.required' => 'Ingrese su PIN.',
            'pin_code.string' => 'El PIN debe tener 6 dígitos.',
            'pin_code.digits' => 'El PIN debe tener 6 dígitos.',
        ];
    }
}
