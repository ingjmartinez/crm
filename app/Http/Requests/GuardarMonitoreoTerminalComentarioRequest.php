<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarMonitoreoTerminalComentarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'agencia_id' => ['required', 'integer', 'exists:agencias,id'],
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'comentario' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'comentario' => trim((string) $this->input('comentario')) ?: null,
        ]);
    }
}
