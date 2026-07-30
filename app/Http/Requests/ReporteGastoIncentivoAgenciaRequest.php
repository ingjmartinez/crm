<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReporteGastoIncentivoAgenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'fecha_ini' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_ini'],
            'sistema' => ['nullable', 'in:Todos,Lotobet,Lotonet'],
            'min_dias_venta' => ['nullable', 'integer', 'min:1'],
            'tipo_pago' => ['nullable', 'in:tramos_60,tramos_70,tramos_80'],
            'rangos_pago' => ['nullable', 'string', 'json'],
            'terminales_excluidas' => ['nullable', 'string', 'json'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_ini.required' => 'Seleccione la fecha de inicio.',
            'fecha_fin.required' => 'Seleccione la fecha final.',
            'fecha_fin.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha de inicio.',
        ];
    }
}
