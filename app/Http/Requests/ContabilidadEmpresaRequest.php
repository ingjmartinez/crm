<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContabilidadEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['empresa' => ['sometimes', 'required', 'string', 'in:168,169']];
    }

    public function messages(): array
    {
        return ['empresa.in' => 'Selecciona la empresa 168 o 169.', 'empresa.required' => 'La empresa es obligatoria.', 'empresa.string' => 'La empresa debe ser un codigo valido.'];
    }

    public function empresa(): string
    {
        return $this->validated('empresa', '168');
    }
}
