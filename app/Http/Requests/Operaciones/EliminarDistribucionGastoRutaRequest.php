<?php

namespace App\Http\Requests\Operaciones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EliminarDistribucionGastoRutaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'ruta_key' => ['required', 'string', 'max:150', Rule::exists('distribucion_gasto_ruta_mapeos', 'ruta_key')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'ruta_key.required' => 'Debe indicar la ruta que desea eliminar.',
            'ruta_key.exists' => 'La ruta seleccionada ya no existe en la lista.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['ruta_key' => trim((string) $this->input('ruta_key'))]);
    }
}
