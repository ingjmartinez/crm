<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class ConsultarDesglosePagoCedulaRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('cedula')) {
            $this->merge([
                'cedula' => preg_replace('/\D+/', '', (string) $this->input('cedula')),
            ]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'consultar' => ['nullable', 'boolean'],
            'periodo_id' => ['nullable', 'integer', 'exists:incentivo_periodos,id'],
            'cedula' => ['nullable', 'regex:/^\d{11}$/'],
            'cedulas_manual' => ['nullable', 'string', 'max:100000'],
            'archivo_cedulas' => ['nullable', File::types(['xlsx', 'xls', 'csv'])->max(4 * 1024)],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->boolean('consultar') || $validator->errors()->isNotEmpty()) {
                    return;
                }

                if (
                    blank($this->input('cedula'))
                    && blank($this->input('cedulas_manual'))
                    && ! $this->hasFile('archivo_cedulas')
                ) {
                    $validator->errors()->add(
                        'cedulas_manual',
                        'Escribe al menos una cédula o selecciona un archivo.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'cedula.regex' => 'La cédula debe contener 11 dígitos.',
            'cedulas_manual.max' => 'El listado manual es demasiado extenso.',
            'archivo_cedulas.mimes' => 'El archivo debe estar en formato XLSX, XLS o CSV.',
            'archivo_cedulas.max' => 'El archivo no puede superar los 4 MB.',
        ];
    }
}
