<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ProcesarValidadorAgenciaRequest extends FormRequest
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
            'archivo_csv' => [
                'required',
                File::types(['csv', 'txt'])->max(50 * 1024),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo_csv.required' => 'Selecciona el documento CSV que deseas validar.',
            'archivo_csv.file' => 'El documento cargado no es un archivo válido.',
            'archivo_csv.mimes' => 'El documento debe estar en formato CSV o TXT.',
            'archivo_csv.max' => 'El documento no puede superar los 50 MB.',
        ];
    }
}
