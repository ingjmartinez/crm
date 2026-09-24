<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarZonaGeograficaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $zonaGeografica = $this->route('zonaGeografica');

        return [
            'region' => ['required', 'string', 'max:80'],
            'provincia' => ['required', 'string', 'max:100'],
            'municipio' => ['required', 'string', 'max:120'],
            'ciudad_seccion' => ['required', 'string', 'max:160'],
            'sector_barrio_paraje' => [
                'required',
                'string',
                'max:190',
                Rule::unique('zonas_geograficas')->where(fn ($query) => $query
                    ->where('region', $this->string('region')->trim()->toString())
                    ->where('provincia', $this->string('provincia')->trim()->toString())
                    ->where('municipio', $this->string('municipio')->trim()->toString())
                    ->where('ciudad_seccion', $this->string('ciudad_seccion')->trim()->toString()))
                    ->ignore($zonaGeografica?->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'sector_barrio_paraje.unique' => 'Esta combinación geográfica ya está registrada.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->only([
            'region',
            'provincia',
            'municipio',
            'ciudad_seccion',
            'sector_barrio_paraje',
        ]))->map(fn ($valor) => is_string($valor) ? trim($valor) : $valor)->all());
    }
}
