<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarMonitoreoTerminalAgenciasPlazaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'agencias' => ['present', 'array', 'max:2000'],
            'agencias.*' => [
                'required',
                'integer',
                'distinct:strict',
                Rule::exists('agencias', 'id')->where(function ($query): void {
                    $query->whereNotNull('terminal')
                        ->whereRaw('UPPER(TRIM(sistema)) = ?', ['LOTOBET']);
                }),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'agencias.present' => 'Debe enviar la lista de agencias en plaza.',
            'agencias.array' => 'La selección de agencias no es válida.',
            'agencias.*.exists' => 'Una de las agencias seleccionadas no pertenece a Lotobet o no tiene terminal.',
            'agencias.*.distinct' => 'La lista contiene agencias repetidas.',
        ];
    }
}
