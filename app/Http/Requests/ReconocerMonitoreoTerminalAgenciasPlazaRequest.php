<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReconocerMonitoreoTerminalAgenciasPlazaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'archivo' => ['nullable', 'required_without:terminales_manual', 'file', 'mimes:xlsx,xls,csv', 'max:4096'],
            'terminales_manual' => ['nullable', 'required_without:archivo', 'string', 'max:50000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'archivo.required_without' => 'Seleccione un archivo o escriba al menos una terminal.',
            'archivo.mimes' => 'El archivo debe ser XLSX, XLS o CSV.',
            'archivo.max' => 'El archivo no puede superar 4 MB.',
            'terminales_manual.required_without' => 'Seleccione un archivo o escriba al menos una terminal.',
        ];
    }
}
