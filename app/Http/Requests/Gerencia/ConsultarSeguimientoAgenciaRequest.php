<?php

namespace App\Http\Requests\Gerencia;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultarSeguimientoAgenciaRequest extends FormRequest
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
            'consultar' => ['nullable', 'boolean'],
            'mes' => ['nullable', 'date_format:Y-m'],
            'sistema' => ['nullable', Rule::in(['todos', 'lotobet', 'lotonet'])],
            'empresa' => ['nullable', 'string', 'max:150'],
            'ciudad' => ['nullable', 'string', 'max:150'],
            'coordinador' => ['nullable', 'string', 'max:150'],
            'ruta' => ['nullable', 'string', 'max:150'],
            'agencia' => ['nullable', 'string', 'max:150'],
            'meta_tradicional' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'meta_no_tradicional' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'meta_recargas' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'mes.date_format' => 'Selecciona un mes válido.',
            '*.max' => 'El valor indicado es demasiado largo.',
            '*.numeric' => 'Las metas deben ser valores numéricos.',
            '*.min' => 'Las metas no pueden ser negativas.',
        ];
    }
}
