<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SincronizarEmpleadosPorCedulasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cedulas' => ['required', 'array', 'min:1', 'max:50'],
            'cedulas.*' => ['required', 'string', 'regex:/^\d{11}$/', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'cedulas.required' => 'Debe seleccionar al menos una cedula.',
            'cedulas.max' => 'Solo puede consultar hasta 50 cedulas a la vez.',
            'cedulas.*.regex' => 'Cada cedula debe contener 11 digitos.',
            'cedulas.*.distinct' => 'No puede enviar cedulas repetidas.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cedulas' => collect($this->input('cedulas', []))
                ->map(fn ($cedula): string => preg_replace('/\D+/', '', (string) $cedula))
                ->values()
                ->all(),
        ]);
    }
}
