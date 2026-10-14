<?php

namespace App\Http\Requests\Operaciones;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class GuardarMovimientoRutaV2DepositoCentralRequest extends FormRequest
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
            'ruta_key' => ['required', 'string', 'max:180'],
            'id_trans' => ['required', 'string', 'max:180', Rule::unique('movimiento_ruta_v2_deposito_centrals', 'id_trans')->where('fecha', $this->input('fecha'))],
            'monto' => ['required', 'numeric', 'decimal:2', 'gt:0', 'max:9999999999999.99'],
            'recibo' => ['required', File::image()->max(10 * 1024)],
        ];
    }

    public function messages(): array
    {
        return [
            'ruta_key.required' => 'Selecciona una ruta.',
            'id_trans.required' => 'Selecciona la transacción de depósito que vas a aplicar.',
            'id_trans.unique' => 'Esta transacción de depósito ya fue aplicada por central.',
            'monto.gt' => 'El monto debe ser mayor que cero.',
            'recibo.required' => 'Adjunta la foto del recibo de central.',
        ];
    }
}
