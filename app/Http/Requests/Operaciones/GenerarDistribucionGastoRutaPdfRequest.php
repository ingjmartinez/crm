<?php

namespace App\Http\Requests\Operaciones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerarDistribucionGastoRutaPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'fecha_ini' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_ini'],
            'ruta_key' => [
                'required',
                'string',
                Rule::exists('movimientos_rutas_v2_gastos', 'ruta_key')->where('estado', 'aplicado'),
            ],
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
            'ruta_key.required' => 'Seleccione la ruta que desea imprimir.',
            'ruta_key.exists' => 'La ruta seleccionada no tiene gastos aplicados.',
            'empresa.in' => 'La empresa seleccionada no es valida.',
        ];
    }
}
