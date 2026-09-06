<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSolicitudTerminalRequest extends FormRequest
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
            'prefijo_seleccionado' => ['required', 'regex:/^\d{2}$/'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:500'],
            'codigos' => ['required', 'array', 'min:1', 'max:500'],
            'codigos.*' => ['required', 'string', 'distinct', 'regex:/^05\d{6}$/'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $codigos = $this->input('codigos', []);
                $cantidad = (int) $this->input('cantidad');
                $prefijo = '05'.(string) $this->input('prefijo_seleccionado');

                if (is_array($codigos) && count($codigos) !== $cantidad) {
                    $validator->errors()->add('codigos', 'La cantidad de códigos confirmados no coincide con la solicitud.');
                }

                if (is_array($codigos) && collect($codigos)->contains(
                    fn (mixed $codigo): bool => ! str_starts_with((string) $codigo, $prefijo)
                )) {
                    $validator->errors()->add('codigos', 'Todos los códigos deben usar el prefijo seleccionado.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'prefijo_seleccionado.required' => 'Selecciona los dos dígitos del prefijo.',
            'prefijo_seleccionado.regex' => 'El prefijo seleccionado debe tener dos dígitos.',
            'cantidad.required' => 'Indica la cantidad de códigos solicitados.',
            'cantidad.integer' => 'La cantidad debe ser un número entero.',
            'cantidad.min' => 'Debes solicitar al menos un código.',
            'cantidad.max' => 'Puedes solicitar hasta 500 códigos a la vez.',
            'codigos.required' => 'Primero genera y revisa los códigos sugeridos.',
            'codigos.*.distinct' => 'Los códigos sugeridos no pueden repetirse.',
            'codigos.*.regex' => 'Cada código debe tener ocho dígitos y comenzar con 05.',
        ];
    }
}
