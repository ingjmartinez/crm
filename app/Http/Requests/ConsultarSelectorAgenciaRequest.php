<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarSelectorAgenciaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'anio' => ['nullable', 'required_with:mes_inicio,mes_fin', 'integer', 'between:1900,9998'],
            'mes_inicio' => ['nullable', 'required_with:anio', 'integer', 'between:1,12'],
            'mes_fin' => ['nullable', 'required_with:anio', 'integer', 'between:1,12', 'gte:mes_inicio'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'ruta' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'anio.required_with' => 'Selecciona el año de la consulta.',
            'anio.integer' => 'El año debe ser un número entero.',
            'anio.between' => 'Selecciona un año entre 1900 y 9998.',
            'mes_inicio.required_with' => 'Selecciona el mes inicial.',
            'mes_inicio.integer' => 'Selecciona un mes inicial válido.',
            'mes_inicio.between' => 'El mes inicial debe estar entre enero y diciembre.',
            'mes_fin.required_with' => 'Selecciona el mes final.',
            'mes_fin.integer' => 'Selecciona un mes final válido.',
            'mes_fin.between' => 'El mes final debe estar entre enero y diciembre.',
            'mes_fin.gte' => 'El mes final no puede ser anterior al inicial.',
            'empresa.string' => 'Selecciona una empresa válida.',
            'empresa.max' => 'El nombre de la empresa es demasiado largo.',
            'ruta.string' => 'Selecciona una ruta válida.',
            'ruta.max' => 'El nombre de la ruta es demasiado largo.',
        ];
    }
}
