<?php

namespace App\Http\Requests\RecursosHumanos;

use Illuminate\Foundation\Http\FormRequest;

class ConsultarNominaDomingoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'consultar' => ['nullable', 'boolean'],
            'estatus' => ['nullable', 'in:todos,cumple,no_cumple'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.date_format' => 'La fecha debe tener el formato año-mes-día.',
            'estatus.in' => 'El filtro de cumplimiento seleccionado no es válido.',
        ];
    }
}
