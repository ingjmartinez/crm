<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImprimirRentabilidadAgenciaV10Request extends FormRequest
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
            'tamano_hoja' => ['sometimes', 'required', 'string', 'in:letter,legal'],
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
            'tamano_hoja.required' => 'Selecciona el tamaño de hoja.',
            'tamano_hoja.string' => 'Selecciona un tamaño de hoja válido.',
            'tamano_hoja.in' => 'Selecciona Carta (8.5 x 11) o Legal (8.5 x 14).',
        ];
    }
}
