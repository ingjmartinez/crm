<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BuscarCuentaCentroCostoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['buscar' => ['nullable', 'string', 'max:150']];
    }

    public function messages(): array
    {
        return ['buscar.string' => 'Escribe un codigo o descripcion.', 'buscar.max' => 'La busqueda no puede superar 150 caracteres.'];
    }
}
