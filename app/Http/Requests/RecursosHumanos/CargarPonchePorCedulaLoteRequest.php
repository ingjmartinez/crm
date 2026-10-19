<?php

namespace App\Http\Requests\RecursosHumanos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CargarPonchePorCedulaLoteRequest extends FormRequest
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
            'lotes' => ['required', 'array', 'min:1', 'max:6'],
            'lotes.*.fecha' => ['required', 'date_format:Y-m-d', 'distinct'],
            'lotes.*.tradicional' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:51200'],
            'lotes.*.no_tradicional' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:51200'],
            'lotes.*.recargas' => ['required', 'file', 'mimes:xlsx', 'max:51200'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'lotes.*.fecha.distinct' => 'Cada fecha debe aparecer una sola vez en el lote.',
            'lotes.*.tradicional.uploaded' => 'No se pudo subir un archivo Tradicional.',
            'lotes.*.no_tradicional.uploaded' => 'No se pudo subir un archivo No Tradicional.',
            'lotes.*.recargas.uploaded' => 'No se pudo subir un archivo Recargas.',
        ];
    }
}
