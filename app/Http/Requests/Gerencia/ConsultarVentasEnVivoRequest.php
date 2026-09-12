<?php

namespace App\Http\Requests\Gerencia;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultarVentasEnVivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'consultar' => ['nullable', 'boolean'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'sistema' => ['nullable', Rule::in(['todos', 'lotobet', 'lotonet'])],
            'empresa' => ['nullable', 'string', 'max:150'],
            'ciudad' => ['nullable', 'string', 'max:150'],
            'ruta' => ['nullable', 'string', 'max:150'],
            'tipo_producto' => ['nullable', Rule::in(['todos', 'tradicional', 'no_tradicional'])],
            'buscar' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.date_format' => 'Selecciona una fecha valida con formato YYYY-MM-DD.',
            '*.max' => 'El valor indicado es demasiado largo.',
        ];
    }
}
