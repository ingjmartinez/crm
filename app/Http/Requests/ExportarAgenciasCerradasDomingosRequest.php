<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class ExportarAgenciasCerradasDomingosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'fecha' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && Carbon::hasFormat($value, 'Y-m-d') && ! Carbon::parse($value)->isSunday()) {
                        $fail('La fecha seleccionada debe ser domingo.');
                    }
                },
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fecha.required' => 'Seleccione el domingo que desea exportar.',
            'fecha.date_format' => 'La fecha seleccionada no es válida.',
            'fecha.before_or_equal' => 'No puede exportar una fecha futura.',
        ];
    }
}
