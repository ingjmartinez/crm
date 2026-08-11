<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportarMaestraEmpleadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'empresa' => ['nullable', 'string', Rule::in(['168', '169'])],
            'buscar' => ['nullable', 'string', 'max:255'],
        ];
    }
}
