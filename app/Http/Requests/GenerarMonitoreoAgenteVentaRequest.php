<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GenerarMonitoreoAgenteVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'fecha_inicio' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio', 'before_or_equal:today'],
            'sistema' => ['required', Rule::in(['todos', 'lotobet', 'lotonet'])],
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
            'sistema.in' => 'El sistema seleccionado no es válido.',
        ];
    }
}
