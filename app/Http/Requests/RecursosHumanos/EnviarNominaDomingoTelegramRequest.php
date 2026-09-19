<?php

namespace App\Http\Requests\RecursosHumanos;

use Illuminate\Foundation\Http\FormRequest;

class EnviarNominaDomingoTelegramRequest extends FormRequest
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
        return [
            'chat_id' => ['required', 'string', 'max:255'],
            'coordinador' => ['required', 'string', 'max:255'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'empresa' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'chat_id.required' => 'Ingresa el chat ID o username de Telegram.',
            'coordinador.required' => 'Selecciona un coordinador.',
            'fecha.required' => 'Selecciona la fecha del reporte.',
        ];
    }
}
