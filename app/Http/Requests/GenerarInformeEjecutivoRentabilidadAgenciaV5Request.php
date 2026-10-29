<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class GenerarInformeEjecutivoRentabilidadAgenciaV5Request extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mes' => ['required', 'date_format:Y-m', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && preg_match('/^\d{4}-\d{2}$/', $value) && $value >= now()->format('Y-m')) {
                    $fail('El informe ejecutivo requiere un mes cerrado.');
                }
            }],
            'empresa' => ['nullable', 'string', 'max:255'],
            'porcentaje_pago' => ['required', 'integer', 'in:60,70,80'],
            'productos_filtrados' => ['nullable', 'boolean'],
            'productos' => ['nullable', 'array', 'max:3'],
            'productos.*' => ['distinct', 'in:Tradicional,No Tradicional,Recarga'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mes.required' => 'Selecciona el mes del informe.',
            'mes.date_format' => 'El mes debe tener el formato año-mes.',
            'porcentaje_pago.required' => 'Selecciona un porcentaje de pago.',
            'porcentaje_pago.in' => 'El porcentaje de pago debe ser 60, 70 u 80.',
            'productos.*.in' => 'Selecciona un producto válido.',
        ];
    }
}
