<?php

namespace App\Http\Requests\Operaciones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class GuardarMovimientoRutaV2GastoRequest extends FormRequest
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
            'concepto' => ['required', 'string', 'max:150'],
            'comprobante' => ['nullable', File::image()->max(10 * 1024)],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'monto.gt' => 'El monto del gasto debe ser mayor que cero.',
            'monto.decimal' => 'El monto del gasto debe contener exactamente dos decimales.',
            'concepto.required' => 'Indica el concepto del gasto de ruta.',
            'comprobante.max' => 'El comprobante no puede superar los 10 MB.',
        ];
    }
}
