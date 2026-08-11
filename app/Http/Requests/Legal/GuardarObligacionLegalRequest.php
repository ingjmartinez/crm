<?php

namespace App\Http\Requests\Legal;

use App\Models\LegalObligacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarObligacionLegalRequest extends FormRequest
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
            'tipo' => ['required', 'string', Rule::in(array_keys(LegalObligacion::TIPOS))],
            'descripcion' => ['nullable', 'string', 'max:180'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'frecuencia' => ['required', 'string', Rule::in(array_keys(LegalObligacion::FRECUENCIAS))],
            'fecha_primer_pago' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_primer_pago'],
        ];
    }

    public function messages(): array
    {
        return [
            'monto.gt' => 'El monto de la obligación debe ser mayor que cero.',
        ];
    }
}
