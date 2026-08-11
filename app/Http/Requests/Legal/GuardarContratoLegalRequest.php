<?php

namespace App\Http\Requests\Legal;

use App\Models\LegalObligacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class GuardarContratoLegalRequest extends FormRequest
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
            'titulo' => ['required', 'string', 'max:180'],
            'numero_contrato' => ['nullable', 'string', 'max:100'],
            'contraparte' => ['nullable', 'string', 'max:180'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'estado' => ['required', 'string', Rule::in(['borrador', 'activo'])],
            'renovacion_automatica' => ['nullable', 'boolean'],
            'documento_pdf' => ['required', File::types(['pdf'])->max(15 * 1024)],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'obligacion_tipo' => ['required', 'string', Rule::in(array_keys(LegalObligacion::TIPOS))],
            'obligacion_descripcion' => ['nullable', 'string', 'max:180'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'frecuencia' => ['required', 'string', Rule::in(array_keys(LegalObligacion::FRECUENCIAS))],
            'fecha_primer_pago' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'fecha_fin_pagos' => ['nullable', 'date', 'after_or_equal:fecha_primer_pago'],
        ];
    }

    public function messages(): array
    {
        return [
            'documento_pdf.required' => 'Adjunta el contrato en formato PDF.',
            'documento_pdf.mimes' => 'El contrato debe ser un documento PDF.',
            'monto.gt' => 'El monto de la obligación debe ser mayor que cero.',
        ];
    }
}
