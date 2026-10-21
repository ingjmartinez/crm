<?php

namespace App\Http\Requests\Bi;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GuardarHorarioRequest extends FormRequest
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
            'tipo' => ['required', 'in:empleado,terminal,empleado_terminal'],
            'cedula' => ['required_if:tipo,empleado,empleado_terminal', 'nullable', 'digits:11'],
            'terminal' => ['required_if:tipo,terminal,empleado_terminal', 'nullable', 'string', 'max:30'],
            'entrada' => ['required', 'date_format:H:i'],
            'salida' => ['required', 'date_format:H:i'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cedula' => preg_replace('/\D/', '', (string) $this->input('cedula', '')) ?: null,
            'terminal' => preg_replace('/\D/', '', (string) $this->input('terminal', '')) ?: null,
        ]);
    }

    public function messages(): array
    {
        return [
            'cedula.required_if' => 'Ingrese la cédula para este horario.',
            'cedula.digits' => 'La cédula debe tener 11 dígitos.',
            'terminal.required_if' => 'Ingrese la terminal para este horario.',
            'entrada.date_format' => 'Ingrese una hora de entrada válida.',
            'salida.date_format' => 'Ingrese una hora de salida válida.',
        ];
    }
}
