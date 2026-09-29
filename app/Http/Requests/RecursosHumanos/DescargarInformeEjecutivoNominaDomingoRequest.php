<?php

namespace App\Http\Requests\RecursosHumanos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DescargarInformeEjecutivoNominaDomingoRequest extends FormRequest
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
            'empresa' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fecha.required' => 'Selecciona la fecha de cierre del informe.',
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'empresa.required' => 'Selecciona una empresa para generar el informe ejecutivo.',
        ];
    }
}
