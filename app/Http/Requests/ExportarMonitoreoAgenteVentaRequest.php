<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportarMonitoreoAgenteVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'formato' => ['required', Rule::in(['excel', 'pdf'])],
            'registros' => ['required', 'array', 'min:1', 'max:5000'],
            'registros.*' => ['required', 'array:fecha,fecha_iso,sistema,cedula,agente,entrada,salida,marca_validar,ultima_venta,terminal,agencia,empresa,coordinador,estado,observacion'],
            'registros.*.fecha' => ['required', 'string', 'max:20'],
            'registros.*.fecha_iso' => ['required', 'date_format:Y-m-d'],
            'registros.*.sistema' => ['required', Rule::in(['LOTOBET', 'LOTONET'])],
            'registros.*.cedula' => ['nullable', 'string', 'max:30'],
            'registros.*.agente' => ['required', 'string', 'max:255'],
            'registros.*.entrada' => ['nullable', 'string', 'max:30'],
            'registros.*.salida' => ['nullable', 'string', 'max:30'],
            'registros.*.marca_validar' => ['nullable', 'string', 'max:30'],
            'registros.*.ultima_venta' => ['nullable', 'string', 'max:30'],
            'registros.*.terminal' => ['required', 'string', 'max:50'],
            'registros.*.agencia' => ['required', 'string', 'max:255'],
            'registros.*.empresa' => ['required', 'string', 'max:150'],
            'registros.*.coordinador' => ['required', 'string', 'max:255'],
            'registros.*.estado' => ['required', Rule::in([
                'COMPLETO',
                'SIN ENTRADA',
                'SIN SALIDA',
                'SIN ENTRADA Y SALIDA',
                'REINICIO VALIDADO',
                'SALIDA POR INACTIVIDAD',
                'PENDIENTE DE VALIDACIÓN',
            ])],
            'registros.*.observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'formato.in' => 'El formato debe ser PDF o Excel.',
            'registros.required' => 'No hay agentes para exportar.',
            'registros.min' => 'No hay agentes para exportar.',
            'registros.max' => 'La exportación no puede superar 5,000 registros.',
        ];
    }
}
