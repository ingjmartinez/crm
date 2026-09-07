<?php

namespace App\Http\Requests\Operaciones;

use Illuminate\Foundation\Http\FormRequest;

class ConsultarDistribucionGastoRutaSubgruposRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'id_grupo' => ['required', 'string', 'max:20', 'regex:/^\d+$/'],
            'company_id' => ['required', 'string', 'in:168,169'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'id_grupo.required' => 'Digite el ID de Ruta empresa.',
            'id_grupo.regex' => 'El ID de Ruta empresa debe contener solo números.',
            'company_id.required' => 'Seleccione la empresa.',
            'company_id.in' => 'La empresa seleccionada no es válida.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id_grupo' => trim((string) $this->input('id_grupo')),
            'company_id' => trim((string) $this->input('company_id')),
        ]);
    }
}
