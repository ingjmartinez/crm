<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConsultarIngresoLotekaRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $montoLoteka = $this->input('monto_loteka');

        if (! is_string($montoLoteka) || trim($montoLoteka) === '') {
            return;
        }

        $this->merge([
            'monto_loteka' => str_replace([',', 'RD$', ' '], '', $montoLoteka),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'consultar' => ['nullable', 'boolean'],
            'fecha_inicio' => ['nullable', 'required_if:consultar,1', 'date_format:Y-m-d', 'before_or_equal:today'],
            'fecha_fin' => ['nullable', 'required_if:consultar,1', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio', 'before_or_equal:today'],
            'empresa' => ['nullable', Rule::in(['todas', '168', '169'])],
            'monto_loteka' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->boolean('consultar') || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $fechaInicio = Carbon::parse((string) $this->input('fecha_inicio'));
                $fechaFin = Carbon::parse((string) $this->input('fecha_fin'));

                if ($fechaInicio->diffInDays($fechaFin) > 366) {
                    $validator->errors()->add('fecha_fin', 'El período no puede superar 366 días.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fecha_inicio.required_if' => 'Selecciona la fecha inicial.',
            'fecha_inicio.before_or_equal' => 'La fecha inicial no puede ser futura.',
            'fecha_fin.required_if' => 'Selecciona la fecha final.',
            'fecha_fin.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'fecha_fin.before_or_equal' => 'La fecha final no puede ser futura.',
            'empresa.in' => 'Selecciona una empresa válida.',
            'monto_loteka.numeric' => 'El monto Loteka debe ser numérico.',
            'monto_loteka.min' => 'El monto Loteka no puede ser negativo.',
            'monto_loteka.max' => 'El monto Loteka supera el límite permitido.',
        ];
    }
}
