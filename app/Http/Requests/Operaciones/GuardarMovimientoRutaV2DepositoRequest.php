<?php

namespace App\Http\Requests\Operaciones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class GuardarMovimientoRutaV2DepositoRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'ruta_key' => ['required', 'string', 'max:180'],
            'ruta' => ['required', 'string', 'max:180'],
            'monto' => ['required', 'numeric', 'decimal:2', 'gt:0', 'max:9999999999999.99'],
            'banco' => ['required', 'string', 'max:100'],
            'referencia' => ['nullable', 'string', 'max:120'],
            'comprobante' => ['nullable', File::image()->max(10 * 1024)],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'monto.gt' => 'El monto del depósito debe ser mayor que cero.',
            'monto.decimal' => 'El monto del depósito debe contener exactamente dos decimales.',
            'banco.required' => 'Selecciona o escribe el banco del depósito.',
            'comprobante.max' => 'El comprobante no puede superar los 10 MB.',
        ];
    }
}
