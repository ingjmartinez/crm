<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class GuardarRentabilidadAgenciaR20Request extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mes' => ['required', 'date_format:Y-m'],
            'monto' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'ruta' => ['nullable', 'string', 'max:255'],
            'ingresos_contables' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mes.required' => 'Selecciona el mes del reporte.',
            'mes.date_format' => 'El mes debe tener el formato año-mes.',
            'monto.required' => 'Escribe el monto de R20.',
            'monto.numeric' => 'El monto de R20 debe ser numérico.',
            'monto.min' => 'El monto de R20 no puede ser negativo.',
            'monto.decimal' => 'El monto de R20 puede tener hasta dos decimales.',
        ];
    }
}
