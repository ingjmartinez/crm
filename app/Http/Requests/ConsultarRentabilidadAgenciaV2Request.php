<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ConsultarRentabilidadAgenciaV2Request extends FormRequest
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
            'mes' => ['nullable', 'date_format:Y-m'],
            'alcance' => ['nullable', 'in:todas,terminal'],
            'terminal' => ['nullable', 'required_if:alcance,terminal', 'string', 'max:100'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'productos_filtrados' => ['nullable', 'boolean'],
            'productos' => ['nullable', 'array', 'max:3'],
            'productos.*' => ['distinct', 'in:Tradicional,No Tradicional,Recarga'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mes.date_format' => 'El mes debe tener el formato año-mes.',
            'terminal.max' => 'El terminal es demasiado largo.',
            'terminal.required_if' => 'Indica el número de terminal para la consulta individual.',
            'empresa.max' => 'El nombre de la empresa es demasiado largo.',
            'productos.*.in' => 'Selecciona un producto válido.',
        ];
    }
}
