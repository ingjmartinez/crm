<?php

namespace App\Http\Requests\Operaciones;

use App\Models\MovimientoRutaV2Gasto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'empresa' => ['nullable', 'string', 'in:GJ,NG'],
            'ruta_key' => ['required', 'string', 'max:180'],
            'ruta' => ['required', 'string', 'max:180'],
            'monto' => ['required', 'numeric', 'decimal:2', 'gt:0', 'max:9999999999999.99'],
            'cuenta_codigo' => ['required', 'string', Rule::in(MovimientoRutaV2Gasto::CUENTAS_DISTRIBUCION)],
            'distribucion_tipo' => ['required', 'string', Rule::in(['ruta', 'terminal'])],
            'centro_costo_id' => ['nullable', 'integer', 'min:1', 'required_if:distribucion_tipo,terminal'],
            'comprobante' => ['nullable', File::image()->max(10 * 1024)],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'monto.gt' => 'El monto del gasto debe ser mayor que cero.',
            'monto.decimal' => 'El monto del gasto debe contener exactamente dos decimales.',
            'cuenta_codigo.required' => 'Selecciona la cuenta contable del gasto.',
            'centro_costo_id.required_if' => 'Selecciona la terminal que recibirá el gasto.',
            'comprobante.max' => 'El comprobante no puede superar los 10 MB.',
        ];
    }
}
