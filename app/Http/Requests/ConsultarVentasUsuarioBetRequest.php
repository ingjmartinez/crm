<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultarVentasUsuarioBetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'empresa' => ['nullable', Rule::in(['todos', 'grupo_joselito', 'negosur'])],
            'coordinador' => [
                'nullable',
                'integer',
                Rule::exists('coordinador_operador', 'id')->where('puesto', 'coordinador'),
            ],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'tipo' => ['nullable', 'string', 'max:100'],
            'fecha' => ['nullable', 'date'],
            'mes' => ['nullable', 'date_format:Y-m'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'empresa.in' => 'Seleccione una empresa válida.',
            'coordinador.exists' => 'Seleccione un coordinador válido.',
            'fecha_fin.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
        ];
    }
}
