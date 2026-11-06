<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsultarMovimientoMayorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cuenta' => ['nullable', 'string', 'max:100', 'prohibits:cuentas'],
            'cuentas' => ['sometimes', 'required', 'array', 'min:1', 'max:50'],
            'cuentas.*' => ['required', 'string', 'max:100', 'distinct'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('cuentas'))) {
            $this->merge(['cuentas' => array_map(fn (mixed $cuenta): mixed => is_string($cuenta) ? trim($cuenta) : $cuenta, $this->input('cuentas'))]);
        }
    }

    public function messages(): array
    {
        return [
            'cuenta.string' => 'Indica un numero de cuenta valido.',
            'cuenta.max' => 'La cuenta no puede tener mas de 100 caracteres.',
            'cuenta.prohibits' => 'Usa una cuenta o una lista de cuentas, no ambos filtros.',
            'cuentas.required' => 'Indica al menos una cuenta.',
            'cuentas.array' => 'Las cuentas deben enviarse como una lista.',
            'cuentas.min' => 'Indica al menos una cuenta.',
            'cuentas.max' => 'Puedes seleccionar hasta 50 cuentas.',
            'cuentas.*.required' => 'No dejes cuentas vacias.',
            'cuentas.*.string' => 'Cada cuenta debe ser un texto valido.',
            'cuentas.*.max' => 'Cada cuenta puede tener hasta 100 caracteres.',
            'cuentas.*.distinct' => 'No repitas cuentas en la lista.',
        ];
    }
}
