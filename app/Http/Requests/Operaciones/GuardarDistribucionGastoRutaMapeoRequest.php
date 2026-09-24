<?php

namespace App\Http\Requests\Operaciones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarDistribucionGastoRutaMapeoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'ruta_key' => ['required', 'string', 'max:150', Rule::unique('distribucion_gasto_ruta_mapeos', 'ruta_key')],
            'id_grupo' => ['required', 'string', 'max:20', 'regex:/^\d+$/'],
            'id_sub_grupo' => ['required_without:id_sub_grupos', 'string', 'max:20', 'regex:/^\d+$/'],
            'id_sub_grupos' => ['required_without:id_sub_grupo', 'array', 'min:1'],
            'id_sub_grupos.*' => ['required', 'string', 'max:20', 'regex:/^\d+$/', 'distinct:strict'],
            'company_id' => ['required', 'string', 'max:20', 'in:168,169'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'ruta_key.required' => 'Seleccione la ruta del gasto.',
            'ruta_key.unique' => 'Esta ruta ya fue agregada. Elimine su configuración actual antes de volver a registrarla.',
            'id_grupo.required' => 'Digite el ID de Ruta empresa.',
            'id_grupo.regex' => 'El ID de Ruta empresa debe contener solo numeros.',
            'id_sub_grupo.required' => 'Digite el ID del socio.',
            'id_sub_grupo.regex' => 'El ID del socio debe contener solo numeros.',
            'id_sub_grupos.required_without' => 'Seleccione al menos un socio.',
            'id_sub_grupos.min' => 'Seleccione al menos un socio.',
            'id_sub_grupos.*.regex' => 'Los ID de socios deben contener solo números.',
            'id_sub_grupos.*.distinct' => 'No puede seleccionar el mismo socio más de una vez.',
            'company_id.required' => 'Seleccione la empresa.',
            'company_id.in' => 'La empresa seleccionada no es valida.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $datos = [
            'ruta_key' => trim((string) $this->input('ruta_key')),
            'id_grupo' => trim((string) $this->input('id_grupo')),
            'company_id' => trim((string) $this->input('company_id')),
        ];

        if ($this->has('id_sub_grupo')) {
            $datos['id_sub_grupo'] = trim((string) $this->input('id_sub_grupo'));
        }

        if ($this->has('id_sub_grupos')) {
            $datos['id_sub_grupos'] = collect($this->input('id_sub_grupos'))
                ->map(fn (mixed $idSubGrupo): string => trim((string) $idSubGrupo))
                ->all();
        }

        $this->merge($datos);
    }
}
