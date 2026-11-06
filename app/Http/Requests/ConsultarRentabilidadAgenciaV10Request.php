<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ConsultarRentabilidadAgenciaV10Request extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'anio' => ['nullable', 'required_with:mes_inicio,mes_fin', 'integer', 'between:1900,9998'],
            'mes_inicio' => ['nullable', 'required_with:anio', 'integer', 'between:1,12'],
            'mes_fin' => ['nullable', 'required_with:anio', 'integer', 'between:1,12', 'gte:mes_inicio'],
            'anio_completo' => ['nullable', 'boolean'],
            'informe_id' => ['nullable', 'required_if:personalizado,1', 'uuid'],
            'personalizado' => ['nullable', 'boolean'],
            'ruta_impresion' => ['nullable', 'string', 'max:255'],
            'salida' => ['nullable', 'in:pdf,imprimir'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'ruta' => ['nullable', 'string', 'max:255'],
            'agrupacion' => ['sometimes', 'required', 'in:terminal,ruta'],
            'alcance' => ['nullable', 'in:todas,terminal'],
            'terminal' => ['nullable', 'required_if:alcance,terminal', 'string', 'max:100'],
            'productos_filtrados' => ['nullable', 'boolean'],
            'productos' => ['nullable', 'array', 'max:3'],
            'productos.*' => ['distinct', 'in:Tradicional,No Tradicional,Recarga'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('agrupacion') === 'ruta') {
            $this->merge(['alcance' => 'todas', 'terminal' => '']);
        }

        if ($this->boolean('anio_completo')) {
            $this->merge(['mes_inicio' => 1, 'mes_fin' => 12]);
        }
    }

    public function messages(): array
    {
        return [
            'agrupacion.in' => 'Selecciona agrupar por terminal o por ruta.',
            'agrupacion.required' => 'Selecciona como agrupar el reporte.',
            'anio.required' => 'Selecciona el año del reporte.',
            'mes_inicio.required' => 'Selecciona el mes inicial.',
            'mes_fin.required' => 'Selecciona el mes final.',
            'anio.required_with' => 'Selecciona el ano del reporte.',
            'anio.integer' => 'El ano debe ser un numero entero.',
            'anio.between' => 'Selecciona un ano entre 1900 y 9998.',
            'mes_inicio.required_with' => 'Selecciona el mes inicial.',
            'mes_fin.required_with' => 'Selecciona el mes final.',
            'mes_inicio.between' => 'El mes inicial debe estar entre enero y diciembre.',
            'mes_fin.between' => 'El mes final debe estar entre enero y diciembre.',
            'mes_fin.gte' => 'El mes final no puede ser anterior al inicial.',
            'terminal.required' => 'Indica la terminal que deseas consultar.',
            'terminal.required_if' => 'Indica la terminal que deseas consultar.',
            'terminal.max' => 'La terminal es demasiado larga.',
            'empresa.max' => 'El nombre de empresa es demasiado largo.',
            'ruta.string' => 'Selecciona una ruta valida.',
            'ruta.max' => 'El nombre de ruta es demasiado largo.',
            'informe_id.uuid' => 'La referencia del informe no es valida. Vuelve a consultar el reporte.',
            'informe_id.required_if' => 'Consulta el reporte antes de personalizar la impresión.',
            'personalizado.boolean' => 'La opción de impresión no es valida.',
            'ruta_impresion.string' => 'Selecciona una ruta valida para imprimir.',
            'ruta_impresion.max' => 'El nombre de ruta es demasiado largo.',
            'salida.in' => 'Selecciona imprimir o generar PDF.',
            'productos.*.in' => 'Selecciona un producto valido.',
        ];
    }
}
