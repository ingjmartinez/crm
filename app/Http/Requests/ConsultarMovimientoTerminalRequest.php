<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarMovimientoTerminalRequest extends FormRequest
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
            'empresa' => ['required', 'string', 'max:255'],
            'terminal' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:30'],
            ...($this->routeIs('reportes.movimiento-terminal.agencia') ? [] : [
                'fecha_inicio' => ['required', 'date_format:Y-m-d'],
                'fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
                'periodo' => ['required', 'in:dia,mes'],
            ]),
        ];
    }

    public function messages(): array
    {
        return [
            'empresa.required' => 'Seleccione una empresa.',
            'terminal.required' => 'Ingrese el código de la terminal.',
            'terminal.regex' => 'El código de la terminal solo debe contener números.',
            'fecha_inicio.required' => 'Seleccione la fecha de inicio.',
            'fecha_inicio.date_format' => 'La fecha de inicio debe tener el formato año-mes-día.',
            'fecha_fin.required' => 'Seleccione la fecha de fin.',
            'fecha_fin.date_format' => 'La fecha de fin debe tener el formato año-mes-día.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'periodo.required' => 'Seleccione el período.',
            'periodo.in' => 'El período debe ser día o mes.',
        ];
    }
}
