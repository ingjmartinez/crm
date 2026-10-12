<?php

namespace App\Http\Requests\Operaciones;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class GuardarMovimientoRutaV2TransferenciaRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'empresa' => ['nullable', 'in:GJ,NG'],
            'ruta_origen_key' => ['required', 'string', 'max:180'],
            'ruta_destino_key' => ['required', 'string', 'max:180', 'different:ruta_origen_key'],
            'monto' => ['required', 'numeric', 'decimal:2', 'gt:0', 'max:9999999999999.99'],
            'recibo' => ['required', File::image()->max(10 * 1024)],
        ];
    }

    public function messages(): array
    {
        return [
            'ruta_destino_key.different' => 'Selecciona una ruta distinta de la ruta de origen.',
            'monto.gt' => 'El monto a transferir debe ser mayor que cero.',
            'recibo.required' => 'Adjunta el recibo de la transferencia.',
        ];
    }
}
