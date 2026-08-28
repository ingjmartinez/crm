<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcesarAgenciasSinCuadrarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'archivo_csv' => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo_csv.required' => 'Debes seleccionar el archivo CSV de agencias sin cuadrar.',
            'archivo_csv.file' => 'El archivo seleccionado no es válido.',
            'archivo_csv.mimes' => 'El documento debe ser un archivo CSV.',
            'archivo_csv.max' => 'El archivo CSV no puede superar los 50 MB.',
        ];
    }
}
