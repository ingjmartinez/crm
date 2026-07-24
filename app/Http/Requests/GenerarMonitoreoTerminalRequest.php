<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class GenerarMonitoreoTerminalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'fecha_inicio' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio', 'before_or_equal:today'],
            'hora_monitoreo' => ['required', 'date_format:H:i'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $inicio = Carbon::parse((string) $this->input('fecha_inicio'));
                $fin = Carbon::parse((string) $this->input('fecha_fin'));

                if ($inicio->diffInDays($fin) > 30) {
                    $validator->errors()->add('fecha_fin', 'El rango no puede superar 31 días.');
                }

                if ($fin->isToday()) {
                    $horaMonitoreo = Carbon::createFromFormat(
                        'Y-m-d H:i',
                        $fin->toDateString().' '.$this->input('hora_monitoreo')
                    );

                    if ($horaMonitoreo->isFuture()) {
                        $validator->errors()->add('hora_monitoreo', 'La hora evaluada no puede ser futura para el día de hoy.');
                    }
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fecha_inicio.before_or_equal' => 'La fecha inicial no puede ser futura.',
            'fecha_fin.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'fecha_fin.before_or_equal' => 'La fecha final no puede ser futura.',
            'hora_monitoreo.required' => 'Debe configurar la hora que desea evaluar.',
            'hora_monitoreo.date_format' => 'La hora evaluada debe tener un formato válido.',
        ];
    }
}
