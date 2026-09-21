<?php

namespace App\Http\Requests\RecursosHumanos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReconocerNominaDomingoTerminalesExcluidasRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['nullable', 'file', 'mimes:xlsx,xls,csv', 'max:4096', 'required_without:terminales_manual'],
            'terminales_manual' => ['nullable', 'string', 'required_without:file'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required_without' => 'Selecciona un archivo o escribe al menos una terminal.',
            'terminales_manual.required_without' => 'Selecciona un archivo o escribe al menos una terminal.',
        ];
    }
}
