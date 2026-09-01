<?php

namespace App\Http\Requests\Contabilidad;

use App\Models\VolantePagoSocioCarga;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class CargarVolantesPagoSociosRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'banco' => ['required', Rule::in(array_keys(VolantePagoSocioCarga::BANCOS))],
            'archivo_csv' => ['required', File::types(['csv', 'txt'])->max(20 * 1024)],
            'fecha_correspondiente' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'banco.required' => 'Selecciona el banco correspondiente al archivo.',
            'banco.in' => 'El banco seleccionado no es válido.',
            'archivo_csv.required' => 'Selecciona el archivo CSV del banco.',
            'archivo_csv.mimes' => 'El documento debe estar en formato CSV o TXT.',
            'archivo_csv.max' => 'El documento no puede superar los 20 MB.',
            'fecha_correspondiente.required' => 'Indica la fecha que corresponde a estos volantes.',
            'fecha_correspondiente.date_format' => 'La fecha correspondiente debe tener un formato válido.',
        ];
    }
}
