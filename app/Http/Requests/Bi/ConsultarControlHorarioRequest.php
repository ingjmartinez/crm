<?php

namespace App\Http\Requests\Bi;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarControlHorarioRequest extends FormRequest
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
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'entrada' => ['required', 'date_format:H:i'],
            'salida' => ['required', 'date_format:H:i'],
            'sistema' => ['required', 'in:todos,lotobet,lotonet'],
            'cedula' => ['nullable', 'digits:11'],
            'empresa' => ['nullable', 'string', 'max:100'],
            'zona' => ['nullable', 'string', 'max:100'],
            'ruta' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['cedula' => preg_replace('/\D/', '', (string) $this->input('cedula', '')) ?: null]);
    }

    public function messages(): array
    {
        return [
            'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'cedula.digits' => 'La cédula debe tener 11 dígitos.',
            'entrada.date_format' => 'Ingrese una hora de entrada válida.',
            'salida.date_format' => 'Ingrese una hora de salida válida.',
        ];
    }
}
