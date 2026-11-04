<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImprimirRentabilidadAgenciaV8Request extends FormRequest
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
            'informe_id' => ['required', 'uuid'],
            'ruta_impresion' => ['nullable', 'string', 'max:255'],
            'salida' => ['required', 'in:pdf,imprimir'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'informe_id.required' => 'Vuelve a consultar el reporte antes de imprimir.',
            'informe_id.uuid' => 'La referencia de la consulta no es válida.',
            'salida.required' => 'Selecciona Imprimir o Generar PDF.',
            'salida.in' => 'Selecciona Imprimir o Generar PDF.',
            'ruta_impresion.max' => 'La ruta seleccionada es demasiado larga.',
        ];
    }
}
