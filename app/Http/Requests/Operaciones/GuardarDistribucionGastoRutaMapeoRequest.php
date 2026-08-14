<?php

namespace App\Http\Requests\Operaciones;

use Illuminate\Foundation\Http\FormRequest;

class GuardarDistribucionGastoRutaMapeoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'ruta_key' => ['required', 'string', 'max:150'],
            'id_grupo' => ['required', 'string', 'max:20', 'regex:/^\d+$/'],
            'id_sub_grupo' => ['required', 'string', 'max:20', 'regex:/^\d+$/'],
            'company_id' => ['required', 'string', 'max:20', 'in:168,169'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'ruta_key.required' => 'Seleccione la ruta del gasto.',
            'id_grupo.required' => 'Digite el ID de Ruta empresa.',
            'id_grupo.regex' => 'El ID de Ruta empresa debe contener solo numeros.',
            'id_sub_grupo.required' => 'Digite el ID del socio.',
            'id_sub_grupo.regex' => 'El ID del socio debe contener solo numeros.',
            'company_id.required' => 'Seleccione la empresa.',
            'company_id.in' => 'La empresa seleccionada no es valida.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ruta_key' => trim((string) $this->input('ruta_key')),
            'id_grupo' => trim((string) $this->input('id_grupo')),
            'id_sub_grupo' => trim((string) $this->input('id_sub_grupo')),
            'company_id' => trim((string) $this->input('company_id')),
        ]);
    }
}
