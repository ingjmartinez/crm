<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarClasificacionCuentaRequest extends FormRequest
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
            'cuenta' => ['required', 'string', 'max:50', Rule::exists('cuentas_contables', 'cuenta')],
            'clasificacion' => ['required', Rule::in(['FIJO', 'VARIABLE'])],
            'criterio' => ['required', Rule::in(['FIJO', 'VARIABLE', 'EXCLUIDA'])],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'cuenta.required' => 'Selecciona una cuenta.',
            'cuenta.exists' => 'La cuenta no existe en el catálogo contable.',
            'clasificacion.required' => 'Selecciona una clasificación.',
            'clasificacion.in' => 'La clasificación debe ser fijo o variable.',
            'criterio.required' => 'Selecciona un criterio.',
            'criterio.in' => 'El criterio seleccionado no es válido.',
            'observacion.max' => 'La observación no puede superar 1000 caracteres.',
        ];
    }
}
