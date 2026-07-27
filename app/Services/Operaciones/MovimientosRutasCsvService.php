<?php

namespace App\Services\Operaciones;

use DateTimeImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MovimientosRutasCsvService
{
    private const TIPO_RETIRO = 'RETIRO DE EFECTIVO DE LA AGENCIA E INGRESO A LA CAJA';

    private const TIPO_DEPOSITO = 'DEPOSITO DE EFECTIVO DEL COLECTOR/CAJA A LA AGENCIA';

    /**
     * @return array{
     *     resumen: array<string, float|int|string|null>,
     *     rutas: array<int, array<string, float|int|string>>,
     *     transacciones: array<int, array<string, float|string>>,
     *     grafico_rutas: array<int, array<string, float|int|string>>,
     *     tendencia_diaria: array<int, array<string, float|string>>,
     *     control: array<string, int>
     * }
     */
    public function procesar(UploadedFile $archivo): array
    {
        $path = $archivo->getRealPath();

        if ($path === false) {
            $this->fallar('No se pudo leer el archivo cargado.');
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            $this->fallar('No se pudo abrir el archivo cargado.');
        }

        try {
            $delimiter = $this->detectarSeparador($path);
            $headers = fgetcsv($handle, 0, $delimiter);

            if ($headers === false) {
                $this->fallar('El archivo no contiene encabezados válidos.');
            }

            $columnas = $this->mapearColumnas($headers);
            $transacciones = [];
            $rutas = [];
            $dias = [];
            $idsProcesados = [];
            $control = [
                'filas_leidas' => 0,
                'filas_aceptadas' => 0,
                'descartadas_tipo' => 0,
                'descartadas_vacias' => 0,
                'duplicadas' => 0,
                'inconsistentes' => 0,
            ];

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $control['filas_leidas']++;

                if ($this->filaVacia($row)) {
                    $control['descartadas_vacias']++;

                    continue;
                }

                $tipoNormalizado = $this->normalizarTipo(
                    $this->limpiarTexto($row[$columnas['tipo_transaccion']] ?? '')
                );

                if (! in_array($tipoNormalizado, [self::TIPO_RETIRO, self::TIPO_DEPOSITO], true)) {
                    $control['descartadas_tipo']++;

                    continue;
                }

                $ruta = $this->limpiarTexto($row[$columnas['ruta']] ?? '');
                $idTransaccion = $this->limpiarTexto($row[$columnas['id_trans']] ?? '');
                $terminal = $this->limpiarTexto($row[$columnas['terminal']] ?? '');
                $fecha = $this->parsearFecha($row[$columnas['fecha']] ?? '');
                $montoOriginal = $this->limpiarMonto($row[$columnas['monto']] ?? '');
                $esDeposito = $tipoNormalizado === self::TIPO_DEPOSITO;

                if (
                    $ruta === ''
                    || $idTransaccion === ''
                    || $fecha === null
                    || $montoOriginal === null
                    || $montoOriginal === 0.0
                    || ($esDeposito && $montoOriginal < 0)
                    || (! $esDeposito && $montoOriginal > 0)
                ) {
                    $control['inconsistentes']++;

                    continue;
                }

                $idKey = Str::upper($idTransaccion);

                if (isset($idsProcesados[$idKey])) {
                    $control['duplicadas']++;

                    continue;
                }

                $idsProcesados[$idKey] = true;
                $rutaKey = Str::upper($ruta);
                $monto = abs($montoOriginal);
                $tipo = $esDeposito ? 'deposito' : 'retiro';

                $transacciones[] = [
                    'ruta_key' => $rutaKey,
                    'ruta' => $ruta,
                    'id_trans' => $idTransaccion,
                    'terminal' => $terminal,
                    'fecha' => $fecha->format('d/m/Y'),
                    'fecha_iso' => $fecha->format('Y-m-d'),
                    'tipo' => $tipo,
                    'tipo_etiqueta' => $esDeposito ? 'Depósito' : 'Retiro',
                    'monto' => $monto,
                    'monto_original' => $montoOriginal,
                ];

                $rutas[$rutaKey] ??= [
                    'ruta_key' => $rutaKey,
                    'ruta' => $ruta,
                    'transacciones' => 0,
                    'depositos' => 0.0,
                    'retiros' => 0.0,
                    'neto' => 0.0,
                ];
                $rutas[$rutaKey]['transacciones']++;
                $rutas[$rutaKey][$esDeposito ? 'depositos' : 'retiros'] += $monto;
                $rutas[$rutaKey]['neto'] = $rutas[$rutaKey]['retiros'] - $rutas[$rutaKey]['depositos'];

                $fechaKey = $fecha->format('Y-m-d');
                $dias[$fechaKey] ??= [
                    'fecha_iso' => $fechaKey,
                    'fecha' => $fecha->format('d/m/Y'),
                    'depositos' => 0.0,
                    'retiros' => 0.0,
                    'neto' => 0.0,
                ];
                $dias[$fechaKey][$esDeposito ? 'depositos' : 'retiros'] += $monto;
                $dias[$fechaKey]['neto'] = $dias[$fechaKey]['retiros'] - $dias[$fechaKey]['depositos'];
                $control['filas_aceptadas']++;
            }
        } finally {
            fclose($handle);
        }

        uasort($rutas, fn (array $a, array $b): int => strnatcasecmp($a['ruta'], $b['ruta']));
        usort(
            $transacciones,
            fn (array $a, array $b): int => [$a['fecha_iso'], $a['ruta'], $a['id_trans']]
                <=> [$b['fecha_iso'], $b['ruta'], $b['id_trans']]
        );
        ksort($dias);

        $rutasOrdenadas = array_values($rutas);
        $tendenciaDiaria = array_values($dias);
        $totalDepositos = array_sum(array_column($rutasOrdenadas, 'depositos'));
        $totalRetiros = array_sum(array_column($rutasOrdenadas, 'retiros'));
        $graficoRutas = $rutasOrdenadas;

        usort(
            $graficoRutas,
            fn (array $a, array $b): int => ($b['depositos'] + $b['retiros']) <=> ($a['depositos'] + $a['retiros'])
        );
        $graficoRutas = array_slice($graficoRutas, 0, 25);
        $control['filas_descartadas'] = $control['descartadas_tipo']
            + $control['descartadas_vacias']
            + $control['duplicadas']
            + $control['inconsistentes'];
        $fechaHasta = $tendenciaDiaria === []
            ? null
            : $tendenciaDiaria[array_key_last($tendenciaDiaria)]['fecha'];

        return [
            'resumen' => [
                'total_rutas' => count($rutasOrdenadas),
                'total_transacciones' => count($transacciones),
                'total_depositos' => $totalDepositos,
                'total_retiros' => $totalRetiros,
                'retiro_neto' => $totalRetiros - $totalDepositos,
                'fecha_desde' => $tendenciaDiaria[0]['fecha'] ?? null,
                'fecha_hasta' => $fechaHasta,
            ],
            'rutas' => $rutasOrdenadas,
            'transacciones' => $transacciones,
            'grafico_rutas' => $graficoRutas,
            'tendencia_diaria' => $tendenciaDiaria,
            'control' => $control,
        ];
    }

    /**
     * @param  array<int, string|null>  $headers
     * @return array{tipo_transaccion: int, ruta: int, id_trans: int, terminal: int, fecha: int, monto: int}
     */
    private function mapearColumnas(array $headers): array
    {
        $normalizados = [];

        foreach ($headers as $index => $header) {
            $normalizados[$this->normalizarEncabezado((string) $header)] = $index;
        }

        $requeridas = [
            'tipo_transaccion' => 'tipotransaccion',
            'ruta' => 'ruta',
            'id_trans' => 'idtrans',
            'terminal' => 'numeroexterno',
            'fecha' => 'fectransaccion',
            'monto' => 'dmonto2',
        ];
        $columnas = [];
        $faltantes = [];

        foreach ($requeridas as $key => $encabezado) {
            if (! array_key_exists($encabezado, $normalizados)) {
                $faltantes[] = match ($key) {
                    'tipo_transaccion' => 'TipoTransaccion',
                    'id_trans' => 'IdTrans',
                    'terminal' => 'NumeroExterno',
                    'fecha' => 'FecTransaccion',
                    'monto' => 'DMonto2',
                    default => 'Ruta',
                };

                continue;
            }

            $columnas[$key] = $normalizados[$encabezado];
        }

        if ($faltantes !== []) {
            $this->fallar('No se encontraron estas columnas requeridas: '.implode(', ', $faltantes).'.');
        }

        /** @var array{tipo_transaccion: int, ruta: int, id_trans: int, terminal: int, fecha: int, monto: int} $columnas */
        return $columnas;
    }

    private function detectarSeparador(string $path): string
    {
        $handle = fopen($path, 'rb');
        $linea = $handle !== false ? (string) fgets($handle) : '';

        if ($handle !== false) {
            fclose($handle);
        }

        $separadores = [',' => 0, ';' => 0, "\t" => 0, '|' => 0];

        foreach (array_keys($separadores) as $separador) {
            $separadores[$separador] = count(str_getcsv($linea, $separador));
        }

        arsort($separadores);

        return (string) array_key_first($separadores);
    }

    private function normalizarEncabezado(string $valor): string
    {
        $valor = preg_replace('/^\xEF\xBB\xBF/', '', $valor) ?? $valor;
        $valor = strtolower(trim($valor));
        $valor = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor) ?: $valor;

        return preg_replace('/[^a-z0-9]+/', '', $valor) ?? $valor;
    }

    private function normalizarTipo(string $valor): string
    {
        return Str::upper($this->limpiarTexto($valor));
    }

    private function limpiarTexto(mixed $valor): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $valor) ?? '');
    }

    private function limpiarMonto(mixed $valor): ?float
    {
        $texto = $this->limpiarTexto($valor);

        if ($texto === '') {
            return null;
        }

        $negativoPorParentesis = str_starts_with($texto, '(') && str_ends_with($texto, ')');
        $texto = str_ireplace(['RD$', '$', '(', ')', ','], '', $texto);

        if (! is_numeric($texto)) {
            return null;
        }

        $monto = (float) $texto;

        return $negativoPorParentesis ? -abs($monto) : $monto;
    }

    private function parsearFecha(mixed $valor): ?DateTimeImmutable
    {
        $texto = substr($this->limpiarTexto($valor), 0, 10);
        $fecha = DateTimeImmutable::createFromFormat('!d/m/Y', $texto);

        if ($fecha === false || $fecha->format('d/m/Y') !== $texto) {
            return null;
        }

        return $fecha;
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function filaVacia(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function fallar(string $mensaje): never
    {
        throw ValidationException::withMessages([
            'archivo_csv' => $mensaje,
        ]);
    }
}
