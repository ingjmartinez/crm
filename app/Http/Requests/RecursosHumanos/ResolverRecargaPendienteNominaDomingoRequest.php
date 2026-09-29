<?php

namespace App\Http\Requests\RecursosHumanos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResolverRecargaPendienteNominaDomingoRequest extends FormRequest
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
            'venta_id' => ['required', 'integer', 'min:1'],
            'terminal' => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'venta_id.required' => 'Selecciona la recarga pendiente.',
            'terminal.required' => 'Selecciona el terminal al que pertenece la recarga.',
        ];
    }
}
