<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReporteGastoAgenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_inicio' => ['nullable', 'date_format:Y-m-d'],
            'fecha_fin' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'empresa' => ['nullable', 'in:168,169'],
            'buscar' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_inicio.date_format' => 'La fecha inicial debe tener formato AAAA-MM-DD.',
            'fecha_fin.date_format' => 'La fecha final debe tener formato AAAA-MM-DD.',
            'fecha_fin.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'empresa.in' => 'Selecciona una empresa válida.',
        ];
    }
}
