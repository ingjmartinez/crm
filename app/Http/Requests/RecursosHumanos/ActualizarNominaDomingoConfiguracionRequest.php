<?php

namespace App\Http\Requests\RecursosHumanos;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarNominaDomingoConfiguracionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'horas_requeridas' => ['required', 'numeric', 'min:0.01', 'max:24'],
            'monto_fijo' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'horas_requeridas.required' => 'Indique la cantidad de horas requeridas.',
            'horas_requeridas.max' => 'Las horas requeridas no pueden superar 24.',
            'monto_fijo.required' => 'Indique el monto fijo a pagar.',
        ];
    }
}
