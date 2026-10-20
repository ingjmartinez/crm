<?php

namespace App\Http\Requests\Bi;

use Illuminate\Foundation\Http\FormRequest;

class IndexAgenciaTerminalRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:80'],
            'zona' => ['nullable', 'string', 'max:100'],
            'empresa' => ['nullable', 'string', 'max:100'],
            'ruta' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.string' => 'La búsqueda debe ser texto.',
            'q.max' => 'La búsqueda no puede superar 80 caracteres.',
            'zona.string' => 'La zona debe ser texto.',
            'zona.max' => 'La zona no puede superar 100 caracteres.',
            'empresa.string' => 'La empresa debe ser texto.',
            'empresa.max' => 'La empresa no puede superar 100 caracteres.',
            'ruta.string' => 'La ruta debe ser texto.',
            'ruta.max' => 'La ruta no puede superar 100 caracteres.',
            'page.integer' => 'La página debe ser un número entero.',
            'page.min' => 'La página debe ser mayor que cero.',
            'desde.date_format' => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'hasta.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
        ];
    }
}
