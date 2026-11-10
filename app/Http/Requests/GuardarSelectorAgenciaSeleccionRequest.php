<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GuardarSelectorAgenciaSeleccionRequest extends FormRequest
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
            'terminales' => ['present', 'array', 'max:10000'],
            'terminales.*' => ['required', 'array:empresa,terminal'],
            'terminales.*.empresa' => ['present', 'nullable', 'string', 'max:255'],
            'terminales.*.terminal' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'terminales.present' => 'Envía la lista de terminales que deseas guardar.',
            'terminales.array' => 'La selección debe ser una lista de terminales.',
            'terminales.max' => 'Puedes guardar hasta 10,000 terminales.',
            'terminales.*.array' => 'Cada selección debe incluir únicamente empresa y terminal.',
            'terminales.*.empresa.present' => 'Indica la empresa de cada terminal.',
            'terminales.*.empresa.string' => 'La empresa debe ser un texto válido.',
            'terminales.*.empresa.max' => 'El nombre de la empresa es demasiado largo.',
            'terminales.*.terminal.required' => 'Indica el número de cada terminal.',
            'terminales.*.terminal.string' => 'El número de terminal debe enviarse como texto.',
            'terminales.*.terminal.max' => 'El número de terminal es demasiado largo.',
        ];
    }
}
