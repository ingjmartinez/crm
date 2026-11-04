<?php

namespace App\Http\Requests;

class ConsultarRentabilidadAgenciaV8Request extends ConsultarRentabilidadAgenciaV7Request
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'anio' => ['nullable', 'required_with:mes_inicio,mes_fin', 'integer', 'between:1900,9998'],
            'mes_inicio' => ['nullable', 'required_with:anio', 'integer', 'between:1,12'],
            'mes_fin' => ['nullable', 'required_with:anio', 'integer', 'between:1,12', 'gte:mes_inicio'],
            'terminal' => ['nullable', 'required_if:alcance,terminal', 'string', 'max:100'],
            'agrupacion' => ['sometimes', 'required', 'in:terminal,ruta'],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->input('agrupacion') === 'ruta') {
            $this->merge(['alcance' => 'todas', 'terminal' => '']);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [...parent::messages(), 'agrupacion.in' => 'Selecciona agrupar por terminal o por ruta.', 'agrupacion.required' => 'Selecciona cómo agrupar el reporte.'];
    }
}
