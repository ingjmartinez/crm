<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IntercambiarCoordinadorRequest extends FormRequest
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
            'destino_id' => ['required', 'integer', Rule::exists('coordinador_operador', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'destino_id.required' => 'Seleccione el bloque de destino.',
            'destino_id.exists' => 'El bloque de destino ya no existe.',
        ];
    }
}
