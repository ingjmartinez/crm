<?php

namespace App\Http\Requests\Operaciones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ProcesarMovimientosRutasV2Request extends FormRequest
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
        return ['archivo_csv' => ['required', File::types(['csv', 'txt'])->max(50 * 1024)]];
    }

    public function messages(): array
    {
        return [
            'archivo_csv.required' => 'Selecciona el documento CSV que deseas importar.',
            'archivo_csv.max' => 'El documento no puede superar los 50 MB.',
        ];
    }
}
