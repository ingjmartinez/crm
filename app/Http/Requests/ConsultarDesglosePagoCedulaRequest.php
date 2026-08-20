<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarDesglosePagoCedulaRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('cedula')) {
            $this->merge([
                'cedula' => preg_replace('/\D+/', '', (string) $this->input('cedula')),
            ]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'consultar' => ['nullable', 'boolean'],
            'periodo_id' => ['nullable', 'integer', 'exists:incentivo_periodos,id'],
            'cedula' => ['nullable', 'required_if:consultar,1', 'regex:/^\d{11}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'cedula.required_if' => 'Indica la cédula que deseas consultar.',
            'cedula.regex' => 'La cédula debe contener 11 dígitos.',
        ];
    }
}
