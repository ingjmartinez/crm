<?php

namespace App\Http\Requests\Gerencia;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ProcesarBeneficioBrutoRequest extends FormRequest
{
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
        $reglaArchivo = File::types(['csv', 'txt'])->max(50 * 1024);

        return [
            'archivo_joselito' => ['required_without_all:archivo_negosur,archivo_higuey', $reglaArchivo],
            'archivo_negosur' => ['required_without_all:archivo_joselito,archivo_higuey', $reglaArchivo],
            'archivo_higuey' => ['required_without_all:archivo_joselito,archivo_negosur', $reglaArchivo],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo_joselito.required_without_all' => 'Selecciona al menos uno de los tres documentos CSV.',
            'archivo_negosur.required_without_all' => 'Selecciona al menos uno de los tres documentos CSV.',
            'archivo_higuey.required_without_all' => 'Selecciona al menos uno de los tres documentos CSV.',
            'archivo_joselito.file' => 'El documento de Joselito no es un archivo válido.',
            'archivo_negosur.file' => 'El documento de Negosur no es un archivo válido.',
            'archivo_higuey.file' => 'El documento de Higuey no es un archivo válido.',
            'archivo_joselito.mimes' => 'El documento de Joselito debe estar en formato CSV o TXT.',
            'archivo_negosur.mimes' => 'El documento de Negosur debe estar en formato CSV o TXT.',
            'archivo_higuey.mimes' => 'El documento de Higuey debe estar en formato CSV o TXT.',
            'archivo_joselito.max' => 'El documento de Joselito no puede superar los 50 MB.',
            'archivo_negosur.max' => 'El documento de Negosur no puede superar los 50 MB.',
            'archivo_higuey.max' => 'El documento de Higuey no puede superar los 50 MB.',
        ];
    }
}
