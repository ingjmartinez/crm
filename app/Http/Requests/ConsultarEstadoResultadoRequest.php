<?php

namespace App\Http\Requests;

class ConsultarEstadoResultadoRequest extends ContabilidadEmpresaRequest
{
    public function rules(): array
    {
        $rules = [...parent::rules(), 'tipos' => ['nullable', 'array'], 'tipos.*' => ['nullable', 'string', 'max:50']];
        if ($this->routeIs('contabilidad.reportes.estado-resultado.data')) {
            $rules = [...$rules,
                'fecha_desde' => ['required', 'date_format:Y-m-d'],
                'fecha_hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_desde'],
                'cuenta_offset' => ['nullable', 'integer', 'min:0'],
                'cuenta_limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [...parent::messages(),
            'fecha_desde.required' => 'Selecciona la fecha desde.',
            'fecha_hasta.required' => 'Selecciona la fecha hasta.',
            'fecha_desde.date_format' => 'La fecha desde debe tener formato YYYY-MM-DD.',
            'fecha_hasta.date_format' => 'La fecha hasta debe tener formato YYYY-MM-DD.',
            'fecha_hasta.after_or_equal' => 'La fecha hasta debe ser igual o posterior a la fecha desde.',
            'tipos.array' => 'Selecciona los tipos contables.',
            'tipos.*.string' => 'Selecciona un tipo contable valido.',
            'tipos.*.max' => 'El tipo contable es demasiado largo.',
            'cuenta_offset.integer' => 'El inicio debe ser un numero entero.',
            'cuenta_offset.min' => 'El inicio no puede ser negativo.',
            'cuenta_limit.integer' => 'El limite debe ser un numero entero.',
            'cuenta_limit.min' => 'El limite debe ser al menos 1.',
            'cuenta_limit.max' => 'El limite no puede superar 100.',
        ];
    }
}
