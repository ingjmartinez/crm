<?php

namespace App\Http\Requests\Bi;

use App\Models\BiLimiteProducto;
use App\Services\Bi\AgenciaTerminalCatalogo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarLimiteProductoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(AgenciaTerminalCatalogo $catalogo): array
    {
        return [
            'producto_id' => ['required', 'string', Rule::when(
                is_string($this->input('producto_id')) && array_key_exists($this->input('producto_id'), BiLimiteProducto::GRUPOS),
                [Rule::in(array_keys(BiLimiteProducto::GRUPOS))],
                ['exists:catalogo_juegos,producto_id'],
            )],
            'monto' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99', 'decimal:0,2'],
            'activo' => ['required', 'boolean'],
            'alcance' => ['sometimes', 'required', Rule::in(['global', 'terminal'])],
            'terminal' => ['required_if:alcance,terminal', 'nullable', 'string', 'max:50', Rule::in(array_column($catalogo->paraAlertas(), 'terminal'))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'producto_id.required' => 'Selecciona el producto que deseas vigilar.',
            'producto_id.exists' => 'Selecciona un producto del catálogo.',
            'monto.required' => 'Indica el límite diario de ventas.',
            'monto.numeric' => 'El límite debe ser un monto válido.',
            'monto.min' => 'El límite debe ser mayor que cero.',
            'monto.max' => 'El límite supera el monto permitido.',
            'monto.decimal' => 'Usa como máximo dos decimales.',
            'activo.required' => 'Indica si la alerta está activa.',
            'activo.boolean' => 'El estado de la alerta no es válido.',
            'alcance.in' => 'Selecciona un alcance válido.',
            'terminal.required_if' => 'Selecciona la terminal que deseas vigilar.',
            'terminal.in' => 'Selecciona una terminal de LotoBet del catálogo.',
            'terminal.string' => 'La terminal debe ser un código válido.',
            'terminal.max' => 'El código de terminal es demasiado largo.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('alcance') === 'global') {
            $this->merge(['terminal' => null]);
        } elseif (is_string($this->input('terminal')) && trim($this->input('terminal')) !== '') {
            $this->merge(['terminal' => ltrim(trim($this->input('terminal')), '0') ?: '0']);
        }
    }
}
