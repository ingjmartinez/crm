<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarCuadrePremiosRequest extends FormRequest
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
            'sistema' => ['required', 'in:Lotobet,Lotonet'],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'sistema.required' => 'Seleccione un sistema.',
            'sistema.in' => 'El sistema debe ser Lotobet o Lotonet.',
            'fecha_inicio.required' => 'Seleccione la fecha de inicio.',
            'fecha_inicio.date_format' => 'La fecha de inicio debe tener el formato YYYY-MM-DD.',
            'fecha_fin.required' => 'Seleccione la fecha fin.',
            'fecha_fin.date_format' => 'La fecha fin debe tener el formato YYYY-MM-DD.',
            'fecha_fin.after_or_equal' => 'La fecha fin debe ser igual o posterior a la fecha de inicio.',
        ];
    }
}
