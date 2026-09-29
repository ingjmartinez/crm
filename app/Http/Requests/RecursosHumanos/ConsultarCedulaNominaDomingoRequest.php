<?php

namespace App\Http\Requests\RecursosHumanos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarCedulaNominaDomingoRequest extends FormRequest
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
            'fecha' => ['required', 'date_format:Y-m-d'],
            'cedula' => ['required', 'string', 'max:20', 'regex:/^[0-9.\-\s]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.required' => 'Selecciona el domingo que deseas consultar.',
            'fecha.date_format' => 'La fecha debe tener el formato año-mes-día.',
            'cedula.required' => 'Escribe la cédula que deseas consultar.',
            'cedula.regex' => 'La cédula solo puede contener números, guiones, puntos o espacios.',
        ];
    }
}
