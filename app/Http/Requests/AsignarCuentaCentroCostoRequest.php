<?php

namespace App\Http\Requests;

use App\Models\CentroDeCosto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsignarCuentaCentroCostoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var CentroDeCosto $centro */
        $centro = $this->route('centro');
        $empresa = substr(trim((string) $centro->company_id), 0, 3);

        return ['cuenta_ruta_id' => ['present', 'nullable', 'integer', Rule::exists('cuentas_contables', 'id')->where('company_id', $empresa)]];
    }

    public function messages(): array
    {
        return [
            'cuenta_ruta_id.present' => 'Selecciona una cuenta o quita la asociacion.',
            'cuenta_ruta_id.integer' => 'Selecciona una cuenta valida.',
            'cuenta_ruta_id.exists' => 'La cuenta debe pertenecer a la empresa del centro de costo.',
        ];
    }
}
