<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportarMonitoreoTerminalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', Rule::in(['TODOS', 'AVISO', 'SIN AGENTE DE VENTA'])],
            'formato' => ['required', 'string', Rule::in(['pdf', 'excel'])],
            'registros' => ['required', 'array', 'min:1', 'max:5000'],
            'registros.*' => ['required', 'array:agencia,terminal,coordinador,comentario,fecha,hora_apertura,hora_ponche,hora_monitoreo,tipo_horario,minutos_tardanza,estado'],
            'registros.*.agencia' => ['required', 'string', 'max:255'],
            'registros.*.terminal' => ['required', 'string', 'max:50'],
            'registros.*.coordinador' => ['required', 'string', 'max:255'],
            'registros.*.comentario' => ['nullable', 'string', 'max:2000'],
            'registros.*.fecha' => ['required', 'string', 'max:20'],
            'registros.*.hora_apertura' => ['required', 'string', 'max:20'],
            'registros.*.hora_ponche' => ['nullable', 'string', 'max:20'],
            'registros.*.hora_monitoreo' => ['nullable', 'string', 'max:20'],
            'registros.*.tipo_horario' => ['nullable', 'string', Rule::in(['AM', 'PM'])],
            'registros.*.minutos_tardanza' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'registros.*.estado' => ['required', 'string', Rule::in([
                'CUMPLE',
                'AVISO',
                'FALTA',
                'SIN AGENTE DE VENTA',
            ])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'estado.in' => 'El estado solicitado no se puede exportar.',
            'formato.in' => 'El formato debe ser PDF o Excel.',
            'registros.required' => 'No hay terminales para exportar.',
            'registros.min' => 'No hay terminales para exportar.',
            'registros.max' => 'La exportación no puede superar 5,000 registros.',
        ];
    }
}
