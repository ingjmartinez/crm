<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreviewSolicitudTerminalRequest extends FormRequest
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
            'prefijo_seleccionado' => ['required', 'regex:/^\d{2}$/'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'prefijo_seleccionado.required' => 'Selecciona los dos dígitos del prefijo.',
            'prefijo_seleccionado.regex' => 'El prefijo seleccionado debe tener dos dígitos.',
            'cantidad.required' => 'Indica la cantidad de códigos solicitados.',
            'cantidad.integer' => 'La cantidad debe ser un número entero.',
            'cantidad.min' => 'Debes solicitar al menos un código.',
            'cantidad.max' => 'Puedes solicitar hasta 500 códigos a la vez.',
        ];
    }
}
