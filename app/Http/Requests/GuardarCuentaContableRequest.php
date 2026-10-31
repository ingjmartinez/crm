<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class GuardarCuentaContableRequest extends ContabilidadEmpresaRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'cuenta' => ['required', 'string', 'max:50', Rule::unique('cuentas_contables', 'cuenta')->where('company_id', $this->input('empresa', '168'))->ignore($this->route('id'))],
            'descripcion' => ['required', 'string', 'max:255'],
            'ctacontrol' => ['nullable', 'string', 'max:50'],
            'tipo' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [...parent::messages(),
            'cuenta.required' => 'La cuenta es obligatoria.',
            'cuenta.unique' => 'Esta cuenta ya existe en la empresa seleccionada.',
            'descripcion.required' => 'La descripcion es obligatoria.',
            '*.max' => 'El campo supera la longitud permitida.',
            '*.string' => 'El campo debe ser texto.',
        ];
    }
}
