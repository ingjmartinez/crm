<?php

namespace App\Http\Requests\Gerencia;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultarDetalleSeguimientoAgenciaRequest extends FormRequest
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
            'mes' => ['required', 'date_format:Y-m'],
            'terminal' => ['required', 'string', 'max:80'],
            'sistema' => ['required', Rule::in(['lotobet', 'lotonet'])],
            'meta_tradicional' => ['required', 'numeric', 'min:0'],
            'meta_no_tradicional' => ['required', 'numeric', 'min:0'],
            'meta_recargas' => ['required', 'numeric', 'min:0'],
        ];
    }
}
