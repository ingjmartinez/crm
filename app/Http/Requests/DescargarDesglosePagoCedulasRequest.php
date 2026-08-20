<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DescargarDesglosePagoCedulasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'periodo_id' => ['required', 'integer', 'exists:incentivo_periodos,id'],
            'cedulas' => ['required', 'array', 'min:1', 'max:500'],
            'cedulas.*' => ['required', 'regex:/^\d{11}$/', 'distinct'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cedulas.required' => 'No hay cédulas seleccionadas para generar el PDF.',
            'cedulas.max' => 'El PDF consolidado admite un máximo de 500 cédulas.',
            'cedulas.*.regex' => 'El listado contiene una cédula inválida.',
        ];
    }
}
