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
            'estado' => ['required', 'string', Rule::in(['AVISO', 'REQUIERE LLAMADA'])],
            'formato' => ['required', 'string', Rule::in(['pdf', 'excel'])],
            'registros' => ['required', 'array', 'min:1', 'max:5000'],
            'registros.*' => ['required', 'array:agencia,terminal,coordinador,fecha,hora_apertura,hora_ponche,minutos_tardanza,estado'],
            'registros.*.agencia' => ['required', 'string', 'max:255'],
            'registros.*.terminal' => ['required', 'string', 'max:50'],
            'registros.*.coordinador' => ['required', 'string', 'max:255'],
            'registros.*.fecha' => ['required', 'string', 'max:20'],
            'registros.*.hora_apertura' => ['required', 'string', 'max:20'],
            'registros.*.hora_ponche' => ['nullable', 'string', 'max:20'],
            'registros.*.minutos_tardanza' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'registros.*.estado' => ['required', 'string', Rule::in(['AVISO', 'REQUIERE LLAMADA'])],
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
