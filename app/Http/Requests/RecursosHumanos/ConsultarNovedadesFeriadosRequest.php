<?php

namespace App\Http\Requests\RecursosHumanos;

use Illuminate\Contracts\Validation\ValidationRule;

class ConsultarNovedadesFeriadosRequest extends ConsultarPonchePorCedulaRequest
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
        return [
            ...parent::rules(),
            'cedula' => ['nullable', 'digits:11'],
            'empresa' => ['nullable', 'in:168,169'],
            'idturno' => ['nullable', 'integer', 'in:7,8,14,9,1'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'empresa.in' => 'Seleccione una empresa válida.',
            'idturno.integer' => 'Seleccione un turno válido.',
            'idturno.in' => 'Seleccione un turno válido.',
        ];
    }
}
