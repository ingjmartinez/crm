<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EnviarSolicitudTerminalCorreoRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'solicitud_correo_id' => ['required', 'integer'],
            'correos' => ['required', 'string', 'max:2000'],
            'destinatarios' => ['required', 'array', 'min:1', 'max:20'],
            'destinatarios.*' => ['required', 'email', 'distinct'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'correos.required' => 'Escribe al menos un correo electrónico.',
            'destinatarios.required' => 'Escribe al menos un correo electrónico.',
            'destinatarios.max' => 'Puedes enviar la solicitud a un máximo de 20 correos.',
            'destinatarios.*.email' => 'Una de las direcciones de correo no es válida.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $destinatarios = preg_split(
            '/[\s,;]+/',
            trim((string) $this->input('correos')),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        $this->merge([
            'destinatarios' => collect($destinatarios ?: [])
                ->map(fn (string $correo): string => mb_strtolower($correo))
                ->unique()
                ->values()
                ->all(),
        ]);
    }
}
