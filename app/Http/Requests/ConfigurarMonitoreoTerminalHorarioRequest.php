<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfigurarMonitoreoTerminalHorarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'hora' => ['required', 'date_format:H:i'],
            'tipo_horario' => ['required', 'in:AM,PM'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'hora.required' => 'Debe indicar el horario.',
            'hora.date_format' => 'El horario debe tener un formato válido.',
            'tipo_horario.required' => 'Debe indicar si pertenece al horario AM o PM.',
            'tipo_horario.in' => 'El tipo de horario seleccionado no es válido.',
        ];
    }
}
