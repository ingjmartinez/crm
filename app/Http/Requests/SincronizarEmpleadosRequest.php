<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SincronizarEmpleadosRequest extends FormRequest
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
            'empresa' => ['required', 'in:168,169'],
            'cedula' => ['nullable', 'regex:/^\d{11}$/'],
            'limite' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'empresa.required' => 'Debe seleccionar una empresa.',
            'empresa.in' => 'La empresa debe ser 168 o 169.',
            'cedula.regex' => 'La cédula debe contener 11 dígitos.',
            'limite.required' => 'Debe indicar cuántos registros desea consultar.',
            'limite.integer' => 'La cantidad de registros debe ser un número entero.',
            'limite.min' => 'Debe consultar al menos un registro.',
            'limite.max' => 'Puede consultar un máximo de 10,000 registros.',
        ];
    }
}
