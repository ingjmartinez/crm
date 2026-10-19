<?php

namespace App\Http\Requests\RecursosHumanos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FinalizarPonchePorCedulaLoteRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'uuid'],
            'documentos' => ['required', 'array', 'size:3'],
            'documentos.*.indice' => ['required', 'integer', 'between:0,2', 'distinct'],
            'documentos.*.nombre' => ['required', 'string', 'max:100'],
            'documentos.*.partes' => ['required', 'integer', 'between:1,50'],
            'documentos.*.tamano' => ['required', 'integer', 'between:1,52428800'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'documentos.required' => 'Selecciona los documentos del lote.',
            'documentos.size' => 'Envía solo los tres documentos de una fecha a la vez.',
            'documentos.*.indice.distinct' => 'Hay documentos repetidos en el lote.',
        ];
    }
}
