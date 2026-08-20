<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultarValidadorIncentivoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'periodo_id' => ['nullable', 'integer', 'exists:incentivo_periodos,id'],
            'buscar' => ['nullable', 'string', 'max:200'],
            'estado' => ['nullable', Rule::in(['pagado', 'pagado_parcial', 'no_pagado', 'no_califica'])],
            'motivo' => ['nullable', Rule::in(['faltante', 'desvinculado', 'agencia_excluida', 'meta_no_alcanzada'])],
            'empresa' => ['nullable', 'string', 'max:100'],
            'por_pagina' => ['nullable', Rule::in([25, 50, 100])],
            'consultar' => ['nullable', 'boolean'],
        ];
    }
}
