<?php

namespace App\Http\Requests\RecursosHumanos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CargarPonchePorCedulaVentasRequest extends FormRequest
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
            'fecha' => ['required', 'date_format:Y-m-d'],
            'tradicional' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:51200'],
            'no_tradicional' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:51200'],
            'recargas' => ['required', 'file', 'mimes:xlsx', 'max:51200'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tradicional.uploaded' => 'No se pudo subir el archivo Tradicional.',
            'no_tradicional.uploaded' => 'No se pudo subir el archivo No Tradicional.',
            'recargas.uploaded' => 'No se pudo subir el archivo Recargas.',
        ];
    }
}
