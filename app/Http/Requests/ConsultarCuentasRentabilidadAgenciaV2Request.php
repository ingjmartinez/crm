<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ConsultarCuentasRentabilidadAgenciaV2Request extends FormRequest
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
            'mes' => ['required', 'date_format:Y-m'],
            'terminal' => ['required', 'string', 'max:100'],
            'empresa' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mes.required' => 'Selecciona el mes de la consulta.',
            'mes.date_format' => 'El mes debe tener el formato año-mes.',
            'terminal.required' => 'Selecciona una terminal.',
            'terminal.max' => 'El número de terminal es demasiado largo.',
        ];
    }
}
