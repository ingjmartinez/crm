<?php

namespace App\Http\Requests\Operaciones;

use Illuminate\Foundation\Http\FormRequest;

class GuardarBancoReporteDiarioRequest extends FormRequest
{
    protected $errorBag = 'guardarBanco';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'empresa_id' => ['required', 'in:168,169'],
            'cuenta_codigo' => ['required', 'string', 'max:50', 'regex:/^10021[0-9]+$/'],
            'fecha' => ['nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'empresa_id.required' => 'Selecciona la empresa.',
            'cuenta_codigo.required' => 'Selecciona una cuenta bancaria.',
        ];
    }
}
