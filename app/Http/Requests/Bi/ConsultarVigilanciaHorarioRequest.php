<?php

namespace App\Http\Requests\Bi;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarVigilanciaHorarioRequest extends FormRequest
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
            'entrada' => ['required', 'date_format:H:i'],
            'sistema' => ['required', 'in:todos,lotobet,lotonet'],
            'empresa' => ['nullable', 'string', 'max:100'],
            'zona' => ['nullable', 'string', 'max:100'],
            'ruta' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'entrada.date_format' => 'Ingrese una hora de entrada válida.',
            'sistema.in' => 'Seleccione un sistema válido.',
        ];
    }
}
