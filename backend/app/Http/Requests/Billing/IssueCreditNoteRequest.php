<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\CreditNoteReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class IssueCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::enum(CreditNoteReason::class)],
            'description' => ['required', 'string', 'min:5', 'max:250'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Elija el motivo de la nota de crédito (catálogo SUNAT 09).',
            'description.required' => 'Describa el sustento de la nota de crédito.',
            'description.min' => 'El sustento debe tener al menos 5 caracteres.',
        ];
    }

    public function reason(): CreditNoteReason
    {
        return CreditNoteReason::from($this->string('reason')->toString());
    }

    public function description(): string
    {
        return $this->string('description')->squish()->toString();
    }
}
