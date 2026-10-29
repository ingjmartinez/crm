<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ConsultarGraficoRentabilidadRequest extends FormRequest
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
            'periodo' => ['nullable', 'in:mes,rango,anio'],
            'mes' => ['exclude_if:periodo,rango,anio', 'nullable', 'required_if:periodo,mes', 'date_format:Y-m'],
            'mes_desde' => ['exclude_unless:periodo,rango', 'required', 'date_format:Y-m'],
            'mes_hasta' => ['exclude_unless:periodo,rango', 'required', 'date_format:Y-m', 'after_or_equal:mes_desde'],
            'anio' => ['exclude_unless:periodo,anio', 'required', 'integer', 'min:1900', 'max:9999'],
            'alcance' => ['nullable', 'in:todas,terminal'],
            'terminal' => ['nullable', 'required_if:alcance,terminal', 'string', 'max:100'],
            'ruta' => ['nullable', 'string', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'productos_filtrados' => ['nullable', 'boolean'],
            'mostrar_resumen' => ['nullable', 'boolean'],
            'productos' => ['nullable', 'array', 'max:3'],
            'productos.*' => ['distinct', 'in:Tradicional,No Tradicional,Recarga'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mostrar_resumen.boolean' => 'Indica si deseas mostrar el resumen.',
            'mes.date_format' => 'El mes debe tener el formato año-mes.',
            'mes.required_if' => 'Selecciona el mes que deseas consultar.',
            'periodo.in' => 'Selecciona mes, rango de meses o año completo.',
            'mes_desde.required' => 'Selecciona el mes inicial.',
            'mes_desde.date_format' => 'El mes inicial debe tener el formato año-mes.',
            'mes_hasta.required' => 'Selecciona el mes final.',
            'mes_hasta.date_format' => 'El mes final debe tener el formato año-mes.',
            'mes_hasta.after_or_equal' => 'El mes final debe ser igual o posterior al mes inicial.',
            'anio.required' => 'Selecciona el año que deseas consultar.',
            'anio.integer' => 'El año debe ser un número entero.',
            'anio.min' => 'El año debe ser igual o posterior a 1900.',
            'anio.max' => 'El año debe tener cuatro dígitos.',
            'terminal.max' => 'El terminal es demasiado largo.',
            'terminal.required_if' => 'Indica el número de terminal para la consulta individual.',
            'ruta.max' => 'El nombre de la ruta es demasiado largo.',
            'empresa.max' => 'El nombre de la empresa es demasiado largo.',
            'productos.*.in' => 'Selecciona un producto válido.',
        ];
    }
}
