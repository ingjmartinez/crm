<?php

namespace App\Http\Requests;

class SincronizarCuentasRequest extends ContabilidadEmpresaRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'limite' => ['sometimes', 'required', 'integer', 'min:1', 'max:10000']];
    }

    public function messages(): array
    {
        return [...parent::messages(),
            'limite.required' => 'Indica cuantas cuentas deseas solicitar.',
            'limite.integer' => 'El limite debe ser un numero entero.',
            'limite.min' => 'El limite debe ser al menos 1.',
            'limite.max' => 'El limite no puede superar 10000 cuentas.',
        ];
    }
}
