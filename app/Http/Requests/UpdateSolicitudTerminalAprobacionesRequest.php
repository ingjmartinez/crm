<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSolicitudTerminalAprobacionesRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'codigos_aprobados' => ['sometimes', 'array'],
            'codigos_aprobados.*' => ['integer', 'distinct', 'exists:solicitud_terminal_codigos,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'codigos_aprobados.*.exists' => 'Uno de los códigos seleccionados ya no está disponible.',
        ];
    }
}
