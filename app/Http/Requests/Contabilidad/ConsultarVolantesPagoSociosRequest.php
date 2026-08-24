<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarVolantesPagoSociosRequest extends FormRequest
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
        $reglasFechaHasta = ['nullable', 'date_format:Y-m-d'];
        if ($this->filled('fecha_desde')) {
            $reglasFechaHasta[] = 'after_or_equal:fecha_desde';
        }

        return [
            'nombre' => ['nullable', 'string', 'max:255'],
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => $reglasFechaHasta,
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.max' => 'El nombre no puede superar los 255 caracteres.',
            'fecha_desde.date_format' => 'La fecha inicial debe tener un formato válido.',
            'fecha_hasta.date_format' => 'La fecha final debe tener un formato válido.',
            'fecha_hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
        ];
    }
}
