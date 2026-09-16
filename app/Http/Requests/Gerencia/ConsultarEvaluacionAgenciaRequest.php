<?php

namespace App\Http\Requests\Gerencia;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultarEvaluacionAgenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meses' => ['nullable', 'integer', 'min:1', 'max:24'],
            'meta' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'analizar' => ['nullable', 'boolean'],
            'empresa' => ['nullable', 'string', 'max:150'],
            'seleccion_productos' => ['nullable', 'boolean'],
            'productos' => ['required_if:seleccion_productos,1', 'array', 'min:1'],
            'productos.*' => ['string', Rule::in(['tradicional', 'no_tradicional', 'recargas'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['empresa' => trim((string) $this->query('empresa', ''))]);
    }

    public function messages(): array
    {
        return [
            'meses.max' => 'Puedes evaluar un máximo de 24 meses.',
            'meta.numeric' => 'El parámetro de venta debe ser un monto válido.',
        ];
    }
}
