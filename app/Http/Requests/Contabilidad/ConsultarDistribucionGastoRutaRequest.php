<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

class ConsultarDistribucionGastoRutaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'fecha_ini' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_ini'],
            'empresa' => ['nullable', 'in:todas,GJ,NG'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fecha_ini.required' => 'Seleccione la fecha inicial.',
            'fecha_fin.required' => 'Seleccione la fecha final.',
            'fecha_fin.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            'empresa.in' => 'La empresa seleccionada no es valida.',
        ];
    }
}
