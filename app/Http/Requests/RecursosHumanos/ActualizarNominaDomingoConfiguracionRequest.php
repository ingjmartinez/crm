<?php

namespace App\Http\Requests\RecursosHumanos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ActualizarNominaDomingoConfiguracionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'horas_requeridas_horas' => ['required', 'integer', 'min:1', 'max:24'],
            'doble_turno_horas' => ['required_with:doble_turno_minutos', 'integer', 'min:0', 'max:24'],
            'doble_turno_minutos' => ['required_with:doble_turno_horas', 'integer', 'min:0', 'max:59'],
            'monto_fijo' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $horasDobleTurno = (int) $this->input('doble_turno_horas', 0);
                $minutosDobleTurno = (int) $this->input('doble_turno_minutos', 0);

                if ($this->hasAny(['doble_turno_horas', 'doble_turno_minutos']) && $horasDobleTurno === 0 && $minutosDobleTurno === 0) {
                    $validator->errors()->add('doble_turno_horas', 'El tiempo de doble turno debe ser mayor que cero.');
                }

                if ($horasDobleTurno === 24 && $minutosDobleTurno > 0) {
                    $validator->errors()->add('doble_turno_minutos', 'Con 24 horas, los minutos deben ser cero.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'horas_requeridas_horas.required' => 'Indique la cantidad de horas requeridas.',
            'horas_requeridas_horas.integer' => 'Las horas requeridas deben ser un número entero.',
            'horas_requeridas_horas.min' => 'El tiempo requerido debe ser de al menos una hora.',
            'horas_requeridas_horas.max' => 'Las horas requeridas no pueden superar 24.',
            'doble_turno_horas.required_with' => 'Indique las horas para terminales doble turno.',
            'doble_turno_minutos.required_with' => 'Indique los minutos para terminales doble turno.',
            'monto_fijo.required' => 'Indique el monto fijo a pagar.',
        ];
    }
}
