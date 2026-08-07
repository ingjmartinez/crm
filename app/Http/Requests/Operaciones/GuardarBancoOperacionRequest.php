<?php

namespace App\Http\Requests\Operaciones;

use App\Models\BancoOperacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarBancoOperacionRequest extends FormRequest
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
            'nombre' => [
                'required',
                'string',
                'max:150',
                Rule::unique(BancoOperacion::class, 'nombre'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
        ]);
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe el nombre del banco.',
            'nombre.unique' => 'Este banco ya está registrado.',
        ];
    }
}
