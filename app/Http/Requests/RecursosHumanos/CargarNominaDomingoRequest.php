<?php

namespace App\Http\Requests\RecursosHumanos;

use Illuminate\Foundation\Http\FormRequest;

class CargarNominaDomingoRequest extends FormRequest
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
            'fecha_nomina' => ['required', 'date_format:Y-m-d'],
            'tradicional' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:51200'],
            'no_tradicional' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:51200'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tradicional.uploaded' => 'No se pudo subir el archivo Tradicional. Revisa que no supere el límite de subida configurado en PHP.',
            'no_tradicional.uploaded' => 'No se pudo subir el archivo No Tradicional. Revisa que no supere el límite de subida configurado en PHP.',
        ];
    }
}
