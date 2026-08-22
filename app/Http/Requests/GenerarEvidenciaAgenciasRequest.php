<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerarEvidenciaAgenciasRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tipo' => [
                'required',
                'string',
                Rule::in([
                    'inactivas',
                    'sin_venta',
                    'inactivas_con_venta',
                    'no_registradas_con_venta',
                ]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.required' => 'Debe indicar el tipo de evidencia que desea generar.',
            'tipo.in' => 'El tipo de evidencia seleccionado no es valido.',
        ];
    }
}
