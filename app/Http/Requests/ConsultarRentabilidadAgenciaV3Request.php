<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ConsultarRentabilidadAgenciaV3Request extends FormRequest
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
            'mes_cierre' => [
                'nullable',
                'date_format:Y-m',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && $value > now()->subMonth()->format('Y-m')) {
                        $fail('Selecciona un mes ya cerrado.');
                    }
                },
            ],
            'empresa' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mes_cierre.date_format' => 'El mes debe tener el formato año-mes.',
            'empresa.max' => 'El nombre de la empresa es demasiado largo.',
        ];
    }
}
