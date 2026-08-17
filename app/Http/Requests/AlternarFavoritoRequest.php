<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AlternarFavoritoRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['favorito_key' => ['required', 'string', 'max:191']];
    }

    public function messages(): array
    {
        return ['favorito_key.required' => 'No se identificó la página que deseas guardar como favorita.'];
    }
}
