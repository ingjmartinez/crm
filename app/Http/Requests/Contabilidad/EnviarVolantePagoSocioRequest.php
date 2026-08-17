<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

class EnviarVolantePagoSocioRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['correo' => ['required', 'email:rfc', 'max:255']];
    }

    public function messages(): array
    {
        return [
            'correo.required' => 'Indica el correo al que deseas enviar el volante.',
            'correo.email' => 'Escribe una dirección de correo válida.',
        ];
    }
}
