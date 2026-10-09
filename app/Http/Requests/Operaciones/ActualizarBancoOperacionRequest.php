<?php

namespace App\Http\Requests\Operaciones;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarBancoOperacionRequest extends FormRequest
{
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
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'empresa_id.required' => 'Selecciona la empresa.',
            'empresa_id.in' => 'La empresa seleccionada no es válida.',
            'cuenta_codigo.required' => 'Selecciona la cuenta bancaria de la empresa.',
            'cuenta_codigo.regex' => 'Selecciona una cuenta bancaria válida.',
        ];
    }
}
