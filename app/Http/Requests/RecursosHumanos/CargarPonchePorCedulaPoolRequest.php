<?php

namespace App\Http\Requests\RecursosHumanos;

use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CargarPonchePorCedulaPoolRequest extends FormRequest
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
            'archivos' => ['required', 'array', 'min:3', 'max:18'],
            'archivos.*' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:51200'],
            'progress_token' => ['nullable', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'archivos.required' => 'Selecciona los documentos que deseas cargar.',
            'archivos.min' => 'Cada fecha necesita tres documentos: Tradicional, No Tradicional y Recargas.',
            'archivos.max' => 'Selecciona hasta 18 documentos (seis fechas) por lote.',
            'archivos.*.uploaded' => 'No se pudo subir uno de los documentos.',
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            try {
                self::agruparArchivos($this->file('archivos', []));
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $campo => $mensajes) {
                    foreach ($mensajes as $mensaje) {
                        $validator->errors()->add($campo, $mensaje);
                    }
                }
            }
        }];
    }

    /** @return array<int, array{fecha: string, tradicional: UploadedFile, no_tradicional: UploadedFile, recargas: UploadedFile}> */
    public function lotes(): array
    {
        return self::agruparArchivos($this->file('archivos', []));
    }

    /**
     * @param  array<int, UploadedFile>  $archivos
     * @return array<int, array{fecha: string, tradicional: UploadedFile, no_tradicional: UploadedFile, recargas: UploadedFile}>
     */
    public static function agruparArchivos(array $archivos): array
    {
        $lotes = [];
        $errores = [];
        foreach ($archivos as $indice => $archivo) {
            $nombre = $archivo->getClientOriginalName();
            if (! preg_match('/^(\d{4}-\d{2}-\d{2})_(tradicional|no_tradicional|recargas)\.(csv|txt|xlsx)$/i', $nombre, $partes)) {
                $errores['archivos.'.$indice][] = 'Nombre inválido: '.$nombre.'. Usa AAAA-MM-DD_categoria.extensión.';

                continue;
            }

            $fecha = $partes[1];
            $categoria = strtolower($partes[2]);
            $extension = strtolower($partes[3]);
            $fechaValida = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
            if ($fechaValida === false || $fechaValida->format('Y-m-d') !== $fecha) {
                $errores['archivos.'.$indice][] = 'Fecha inválida en '.$nombre.'.';
            }
            if ($categoria === 'recargas' && $extension !== 'xlsx') {
                $errores['archivos.'.$indice][] = 'Recargas debe ser un archivo .xlsx: '.$nombre.'.';
            }
            if (isset($lotes[$fecha][$categoria])) {
                $errores['archivos.'.$indice][] = 'Hay dos archivos para '.$fecha.' / '.$categoria.'.';
            }

            $lotes[$fecha]['fecha'] = $fecha;
            $lotes[$fecha][$categoria] = $archivo;
        }
        if (count($lotes) > 6) {
            $errores['archivos'][] = 'Selecciona hasta seis fechas por lote.';
        }
        foreach ($lotes as $fecha => $categorias) {
            $faltantes = array_diff(['tradicional', 'no_tradicional', 'recargas'], array_keys($categorias));
            if ($faltantes !== []) {
                $errores['archivos'][] = 'A '.$fecha.' le faltan: '.implode(', ', $faltantes).'.';
            }
        }
        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
        ksort($lotes);

        return array_values($lotes);
    }
}
