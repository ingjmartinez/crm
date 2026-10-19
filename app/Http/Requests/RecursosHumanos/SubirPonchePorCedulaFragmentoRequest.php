<?php

namespace App\Http\Requests\RecursosHumanos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubirPonchePorCedulaFragmentoRequest extends FormRequest
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
            'indice' => ['required', 'integer', 'between:0,2'],
            'parte' => ['required', 'integer', 'between:0,49', 'lt:partes'],
            'partes' => ['required', 'integer', 'between:1,50'],
            'nombre' => ['required', 'string', 'max:100', 'regex:/^\d{4}-\d{2}-\d{2}_(tradicional|no_tradicional|recargas)\.(csv|txt|xlsx)$/i'],
            'tamano' => ['required', 'integer', 'between:1,52428800'],
            'fragmento' => ['required', 'file', 'max:1024'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nombre.regex' => 'El nombre debe tener fecha y categoría: AAAA-MM-DD_categoria.extensión.',
            'fragmento.uploaded' => 'No se pudo subir un fragmento del documento.',
            'fragmento.max' => 'Cada fragmento debe medir como máximo 1 MB.',
            'parte.lt' => 'El número del fragmento debe ser menor que el total de fragmentos.',
        ];
    }
}
