<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class CargarVolantesPagoSociosRequest extends FormRequest
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
        return ['archivo_csv' => ['required', File::types(['csv', 'txt'])->max(20 * 1024)]];
    }

    public function messages(): array
    {
        return [
            'archivo_csv.required' => 'Selecciona el archivo CSV del Banco Santa Cruz.',
            'archivo_csv.mimes' => 'El documento debe estar en formato CSV o TXT.',
            'archivo_csv.max' => 'El documento no puede superar los 20 MB.',
        ];
    }
}
