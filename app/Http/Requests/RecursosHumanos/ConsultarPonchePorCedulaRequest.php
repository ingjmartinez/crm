<?php

namespace App\Http\Requests\RecursosHumanos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarPonchePorCedulaRequest extends FormRequest
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
            'cedula' => ['required', 'digits:11'],
            'sistema' => ['required', 'in:todos,lotonet,lotobet'],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['cedula' => preg_replace('/\D/', '', (string) $this->input('cedula', ''))]);
    }

    public function messages(): array
    {
        return [
            'cedula.required' => 'Ingrese una cédula.',
            'cedula.digits' => 'La cédula debe tener 11 dígitos.',
            'sistema.in' => 'Seleccione un sistema válido.',
            'fecha_inicio.required' => 'Seleccione la fecha de inicio.',
            'fecha_fin.required' => 'Seleccione la fecha de fin.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
        ];
    }
}
