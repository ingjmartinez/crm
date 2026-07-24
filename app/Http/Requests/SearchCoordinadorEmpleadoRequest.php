<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchCoordinadorEmpleadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'empresa' => ['required', 'string', Rule::in(['Consorcio Joselito', 'Negosur'])],
            'departamento' => ['required', 'string', 'max:100'],
            'buscar' => ['nullable', 'string', 'max:100'],
        ];
    }
}
