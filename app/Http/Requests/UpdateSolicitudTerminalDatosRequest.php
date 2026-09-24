<?php

namespace App\Http\Requests;

use App\Models\ZonaGeografica;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSolicitudTerminalDatosRequest extends FormRequest
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
            'codigos' => ['required', 'array'],
            'codigos.*.id' => ['required', 'integer', 'distinct', 'exists:solicitud_terminal_codigos,id'],
            'codigos.*.nombre_banca' => ['nullable', 'string', 'max:255'],
            'codigos.*.region' => ['nullable', 'string', 'max:80'],
            'codigos.*.provincia' => ['nullable', 'string', 'max:100'],
            'codigos.*.municipio' => ['nullable', 'string', 'max:120'],
            'codigos.*.ciudad' => ['nullable', 'string', 'max:160'],
            'codigos.*.sector' => ['nullable', 'string', 'max:190'],
            'codigos.*.calle' => ['nullable', 'string', 'max:255'],
            'codigos.*.direccion_local' => ['nullable', 'string', 'max:255'],
            'codigos.*.latitud' => ['nullable', 'numeric', 'between:-90,90', 'regex:/^-?\d{2}\.\d{1,7}$/'],
            'codigos.*.longitud' => ['nullable', 'numeric', 'between:-99.99999999,-10', 'regex:/^-\d{2}\.\d{1,8}$/'],
            'codigos.*.rja' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->input('codigos', []) as $indice => $codigo) {
                $campos = [
                    'region' => $codigo['region'] ?? null,
                    'provincia' => $codigo['provincia'] ?? null,
                    'municipio' => $codigo['municipio'] ?? null,
                    'ciudad_seccion' => $codigo['ciudad'] ?? null,
                    'sector_barrio_paraje' => $codigo['sector'] ?? null,
                ];

                $seleccionados = array_filter($campos, fn (mixed $valor): bool => filled($valor));

                if ($seleccionados === []) {
                    continue;
                }

                $existe = ZonaGeografica::query()
                    ->where($seleccionados)
                    ->exists();

                if (! $existe) {
                    $validator->errors()->add(
                        "codigos.{$indice}.region",
                        'La ubicacion seleccionada no respeta la jerarquia de Zonas Geograficas.'
                    );
                }
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'codigos.*.latitud.regex' => 'La latitud debe tener dos digitos antes del punto. Ejemplo: 18.4763890.',
            'codigos.*.longitud.regex' => 'La longitud debe iniciar con menos y tener dos digitos antes del punto. Ejemplo: -50.77777777.',
        ];
    }
}
