<?php

namespace App\Http\Requests;

use App\Models\IncentivoTerminalTipoPago;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReporteNuevoIncentivoV6Request extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $mes = $this->input('mes');
        if (! is_string($mes) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) !== 1) {
            return;
        }

        $fecha = CarbonImmutable::createFromFormat('!Y-m', $mes);
        $this->merge([
            'fecha_ini' => $fecha->startOfMonth()->toDateString(),
            'fecha_fin' => $fecha->endOfMonth()->toDateString(),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mes' => ['required', 'date_format:Y-m'],
            'fecha_ini' => ['required', 'date_format:Y-m-d'],
            'fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_ini'],
            'sistema' => ['nullable', Rule::in(['Todos', 'Lotobet', 'Lotonet'])],
            'min_dias_venta' => ['nullable', 'integer', 'min:1'],
            'filtro_cumplimiento' => ['nullable', Rule::in(['todos', 'cumplidos', 'no_cumplidos'])],
            'tipo_pago' => ['nullable', Rule::in(IncentivoTerminalTipoPago::TIPOS_PAGO)],
            'rangos_pago' => ['nullable', 'json'],
            'rangos_pago_por_tipo' => ['nullable', 'json'],
            'modo_calculo' => ['nullable', Rule::in(['general', 'separado_empresa'])],
            'terminales_excluidas' => ['nullable', 'json'],
        ];
    }
}
