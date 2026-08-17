<?php

namespace App\Services\Contabilidad;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class VolantePagoSocioCsvService
{
    /** @return array{carga: array<string, mixed>, detalles: array<int, array<string, mixed>>} */
    public function procesar(UploadedFile $archivo): array
    {
        $ruta = $archivo->getRealPath();
        $contenido = $ruta !== false ? file($ruta, FILE_IGNORE_NEW_LINES) : false;

        if ($contenido === false) {
            throw ValidationException::withMessages(['archivo_csv' => 'No se pudo leer el archivo cargado.']);
        }

        $lineas = array_map(fn (string $linea): string => $this->utf8($linea), $contenido);
        $indiceCabecera = collect($lineas)->search(
            fn (string $linea): bool => str_starts_with($this->normalizar($linea), 'nombres,tipo identificacion')
        );

        if ($indiceCabecera === false) {
            throw ValidationException::withMessages(['archivo_csv' => 'No se encontró la cabecera de transacciones esperada.']);
        }

        $metadatos = array_values(array_filter(array_slice($lineas, 0, $indiceCabecera)));
        if (count($metadatos) < 6) {
            throw ValidationException::withMessages(['archivo_csv' => 'El encabezado general del archivo está incompleto.']);
        }

        $cuentaEmpresa = $this->valorMetadata($metadatos[0]);
        preg_match('/^(.*?)\s*\(([^()]*)\)\s*$/u', $cuentaEmpresa, $coincidencias);
        $empresa = trim($coincidencias[1] ?? $cuentaEmpresa);
        $rnc = trim($coincidencias[2] ?? '');
        $cabeceras = array_map(
            fn (string $valor): string => $this->normalizar($valor),
            str_getcsv($lineas[$indiceCabecera])
        );
        $detalles = [];

        foreach (array_slice($lineas, $indiceCabecera + 1) as $indice => $linea) {
            if (trim($linea, " \t\n\r\0\x0B,") === '') {
                continue;
            }

            $valores = array_slice(array_pad(str_getcsv($linea), count($cabeceras), ''), 0, count($cabeceras));
            $fila = array_combine($cabeceras, $valores);
            if ($fila === false || trim((string) ($fila['nombres'] ?? '')) === '') {
                continue;
            }

            $detalles[] = [
                'numero_linea' => $indice + 1,
                'nombre' => trim((string) $fila['nombres']),
                'tipo_identificacion' => trim((string) $fila['tipo identificacion']),
                'identificacion' => trim((string) $fila['no identificacion']),
                'cuenta' => trim((string) $fila['no cuenta']),
                'tipo_cuenta' => trim((string) $fila['tipo cuenta']),
                'monto' => $this->monto((string) $fila['monto']),
                'estado' => trim((string) $fila['estado']),
            ];
        }

        if ($detalles === []) {
            throw ValidationException::withMessages(['archivo_csv' => 'El archivo no contiene transacciones para generar volantes.']);
        }

        $montoTotal = $this->monto($this->valorMetadata($metadatos[4]));
        if (abs($montoTotal - round((float) collect($detalles)->sum('monto'), 2)) > 0.01) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'El total del encabezado no coincide con la suma de las transacciones.',
            ]);
        }

        try {
            $fecha = CarbonImmutable::createFromFormat('d/m/Y h:i:s A', $this->valorMetadata($metadatos[5]));
        } catch (\Throwable) {
            throw ValidationException::withMessages(['archivo_csv' => 'La fecha general del archivo no tiene el formato esperado.']);
        }

        return [
            'carga' => [
                'empresa_origen' => $empresa,
                'rnc_origen' => $rnc !== '' ? $rnc : null,
                'cuenta_origen' => $this->valorMetadata($metadatos[1]),
                'tipo_transaccion' => $this->valorMetadata($metadatos[2]),
                'estado' => $this->valorMetadata($metadatos[3]),
                'monto_total' => $montoTotal,
                'fecha_transaccion' => $fecha,
                'cantidad_transacciones' => count($detalles),
            ],
            'detalles' => $detalles,
        ];
    }

    private function valorMetadata(string $linea): string
    {
        return trim(Str::after($linea, ':'));
    }

    private function monto(string $valor): float
    {
        return round((float) preg_replace('/[^0-9.-]/', '', $valor), 2);
    }

    private function normalizar(string $valor): string
    {
        return Str::of($valor)->replace("\u{FEFF}", '')->ascii()->lower()->replace('.', '')->trim()->toString();
    }

    private function utf8(string $valor): string
    {
        return mb_check_encoding($valor, 'UTF-8')
            ? $valor
            : mb_convert_encoding($valor, 'UTF-8', 'Windows-1252');
    }
}
