<?php

namespace App\Http\Requests\Gerencia;

use Illuminate\Foundation\Http\FormRequest;

class EnviarReporteTelegramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'destinatarios' => ['required', 'string'],
            'mensaje' => ['nullable', 'string'],
            'archivos' => ['nullable', 'array'],
            'archivos.*.nombre' => ['required_with:archivos', 'string'],
            'archivos.*.contenido_base64' => ['required_with:archivos', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'destinatarios.required' => 'Ingresa al menos un número o username de Telegram.',
            'archivos.*.nombre.required_with' => 'Cada archivo adjunto debe tener un nombre.',
            'archivos.*.contenido_base64.required_with' => 'Cada archivo adjunto debe tener contenido.',
        ];
    }
}
