<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Ingrese su correo.',
            'email.string' => 'Ingrese un correo válido.',
            'email.email' => 'Ingrese un correo válido.',
            'email.max' => 'El correo no puede superar 255 caracteres.',
            'password.required' => 'Ingrese su contraseña.',
            'password.string' => 'Ingrese una contraseña válida.',
            'password.max' => 'La contraseña no puede superar 255 caracteres.',
        ];
    }
}
