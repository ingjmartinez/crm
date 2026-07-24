<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCoordinadorOperadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'empresa' => ['required', 'string', Rule::in(['Consorcio Joselito', 'Negosur'])],
            'departamento' => ['required', 'string', 'max:100'],
            'empleado_id' => ['required', 'integer'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'empresa.required' => 'Debe seleccionar una empresa.',
            'departamento.required' => 'Debe seleccionar un departamento.',
            'empleado_id.required' => 'Debe seleccionar un empleado activo.',
        ];
    }
}
