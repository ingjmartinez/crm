<?php

namespace App\Http\Requests\Operaciones;

use App\Models\MovimientoRutaV2Gasto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClasificarMovimientoRutaV2GastoRequest extends FormRequest
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
            'cuenta_codigo' => ['required', 'string', Rule::in(MovimientoRutaV2Gasto::CUENTAS_DISTRIBUCION)],
            'distribucion_tipo' => ['required', 'string', Rule::in(['ruta', 'terminal'])],
            'centro_costo_id' => ['nullable', 'integer', 'min:1', 'required_if:distribucion_tipo,terminal'],
        ];
    }
}
