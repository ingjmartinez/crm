<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcesarAgenciasSinCuadrarRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class OperacionesAgenciasSinCuadrarController extends Controller
{
    public function index(): View
    {
        return view('operaciones.agencias-sin-cuadrar', [
            'filas' => collect(),
            'grupos' => collect(),
            'resumen' => null,
            'nombreArchivo' => null,
            'nombreArchivoConsolidado' => null,
            'cantidadTerminalesSinCuadrar' => null,
        ]);
    }

    public function procesar(ProcesarAgenciasSinCuadrarRequest $request): View
    {
        /** @var UploadedFile $archivo */
        $archivo = $request->validated('archivo_csv');
        /** @var UploadedFile $archivoConsolidado */
        $archivoConsolidado = $request->validated('archivo_consolidado');
        $terminalesSinCuadrar = $this->extraerTerminalesSinCuadrar($archivoConsolidado);
        $filas = $this->consolidarUltimoEstadoPorTerminal($this->procesarCsv($archivo))
            ->whereIn('terminal', $terminalesSinCuadrar)
            ->values();
        $grupos = $this->agruparPorRutaYTipo($filas);

        return view('operaciones.agencias-sin-cuadrar', [
            'filas' => $filas,
            'grupos' => $grupos,
            'resumen' => [
                'total_agencias' => $filas->count(),
                'total_rutas' => $filas
                    ->map(fn (array $fila): string => implode('|', [$fila['ruta'], $fila['fecha']]))
                    ->unique()
                    ->count(),
                'total_depositos' => $filas->where('tipo', 'Depósito')->sum('monto_asignado'),
                'total_retiros' => $filas->where('tipo', 'Retiro')->sum('monto_asignado'),
            ],
            'nombreArchivo' => $archivo->getClientOriginalName(),
            'nombreArchivoConsolidado' => $archivoConsolidado->getClientOriginalName(),
            'cantidadTerminalesSinCuadrar' => $terminalesSinCuadrar->count(),
        ]);
    }

    /**
     * @return Collection<int, string>
     */
    private function extraerTerminalesSinCuadrar(UploadedFile $archivo): Collection
    {
        $path = $archivo->getRealPath();

        if (! $path) {
            throw ValidationException::withMessages(['archivo_consolidado' => 'No se pudo leer el archivo consolidado.']);
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['archivo_consolidado' => 'No se pudo abrir el archivo consolidado.']);
        }

        try {
            $separador = $this->detectarSeparador($path);
            $encabezados = fgetcsv($handle, 0, $separador);

            $columnas = $this->mapearColumnasConsolidado(is_array($encabezados) ? $encabezados : []);

            $terminales = collect();

            while (($fila = fgetcsv($handle, 0, $separador)) !== false) {
                $terminal = $this->limpiarTexto($fila[$columnas['terminal']] ?? '');

                if ($terminal !== '' && $this->valorEsCero($fila[$columnas['balance']] ?? '')) {
                    $terminales->push($terminal);
                }
            }

            return $terminales->unique()->values();
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<int, string|null>  $encabezados
     * @return array{terminal: int, balance: int}
     */
    private function mapearColumnasConsolidado(array $encabezados): array
    {
        $normalizados = collect($encabezados)
            ->mapWithKeys(fn ($encabezado, $indice) => [$this->normalizarEncabezado((string) $encabezado) => $indice]);
        $terminal = $normalizados->get('entidad');
        $balance = $normalizados->get('textbox2139');

        $terminal ??= array_key_exists(1, $encabezados) && array_key_exists(30, $encabezados) ? 1 : null;
        $balance ??= array_key_exists(30, $encabezados) ? 30 : null;

        if ($terminal === null || $balance === null) {
            throw ValidationException::withMessages([
                'archivo_consolidado' => 'El CSV consolidado debe contener las columnas B (terminal) y AE (balance).',
            ]);
        }

        return ['terminal' => $terminal, 'balance' => $balance];
    }

    /**
     * @param  Collection<int, array{ruta_id: string, ruta: string, fecha: string, terminal: string, agencia: string, tipo: string, monto_asignado: float}>  $filas
     * @return Collection<int, array{ruta_id: string, ruta: string, fecha: string, tipo: string, cantidad_terminales: int, total_monto: float, terminales: array<int, array{terminal: string, agencia: string, monto_asignado: float}>}>
     */
    private function agruparPorRutaYTipo(Collection $filas): Collection
    {
        $ultimoSerialPorRuta = $filas
            ->groupBy(fn (array $fila): string => implode('|', [$fila['ruta'], $fila['fecha']]))
            ->map(fn (Collection $filasRuta): string => $this->obtenerUltimoSerial($filasRuta));

        return $filas
            ->groupBy(fn (array $fila): string => implode('|', [
                $fila['ruta'],
                $fila['fecha'],
                $fila['tipo'],
            ]))
            ->map(function (Collection $filasRuta) use ($ultimoSerialPorRuta): array {
                $primeraFila = $filasRuta->first();
                $claveRuta = implode('|', [$primeraFila['ruta'], $primeraFila['fecha']]);
                $terminales = $filasRuta
                    ->groupBy('terminal')
                    ->map(function (Collection $filasTerminal): array {
                        $primeraTerminal = $filasTerminal->first();

                        return [
                            'terminal' => $primeraTerminal['terminal'],
                            'agencia' => $primeraTerminal['agencia'],
                            'monto_asignado' => (float) $filasTerminal->sum('monto_asignado'),
                        ];
                    })
                    ->values();

                return [
                    'ruta_id' => $ultimoSerialPorRuta->get($claveRuta, ''),
                    'ruta' => $primeraFila['ruta'],
                    'fecha' => $primeraFila['fecha'],
                    'tipo' => $primeraFila['tipo'],
                    'cantidad_terminales' => $terminales->count(),
                    'total_monto' => (float) $terminales->sum('monto_asignado'),
                    'terminales' => $terminales->all(),
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, array{ruta_id: string, ruta: string, fecha: string, terminal: string, agencia: string, tipo: string, monto_asignado: float}>  $filas
     * @return Collection<int, array{ruta_id: string, ruta: string, fecha: string, terminal: string, agencia: string, tipo: string, monto_asignado: float}>
     */
    private function consolidarUltimoEstadoPorTerminal(Collection $filas): Collection
    {
        return $filas
            ->groupBy(fn (array $fila): string => implode('|', [
                $fila['fecha'],
                $fila['terminal'],
            ]))
            ->map(function (Collection $estadosTerminal): array {
                return $estadosTerminal->reduce(function (?array $ultimoEstado, array $estado): array {
                    if ($ultimoEstado === null || (int) $estado['ruta_id'] >= (int) $ultimoEstado['ruta_id']) {
                        return $estado;
                    }

                    return $ultimoEstado;
                });
            })
            ->values();
    }

    /**
     * @param  Collection<int, array{ruta_id: string}>  $filas
     */
    private function obtenerUltimoSerial(Collection $filas): string
    {
        return (string) $filas->reduce(function (string $ultimoSerial, array $fila): string {
            return (int) $fila['ruta_id'] >= (int) $ultimoSerial ? $fila['ruta_id'] : $ultimoSerial;
        }, '');
    }

    /**
     * @return Collection<int, array{ruta_id: string, ruta: string, fecha: string, terminal: string, agencia: string, tipo: string, monto_asignado: float}>
     */
    private function procesarCsv(UploadedFile $archivo): Collection
    {
        $path = $archivo->getRealPath();

        if (! $path) {
            throw ValidationException::withMessages(['archivo_csv' => 'No se pudo leer el archivo cargado.']);
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['archivo_csv' => 'No se pudo abrir el archivo cargado.']);
        }

        try {
            $separador = $this->detectarSeparador($path);
            $encabezados = fgetcsv($handle, 0, $separador);

            if (! is_array($encabezados)) {
                throw ValidationException::withMessages(['archivo_csv' => 'El archivo no contiene encabezados válidos.']);
            }

            $columnas = $this->mapearColumnas($encabezados);
            $filas = collect();

            while (($fila = fgetcsv($handle, 0, $separador)) !== false) {
                if ($this->filaVacia($fila)) {
                    continue;
                }

                $terminal = $this->limpiarTexto($fila[$columnas['terminal']] ?? '');
                $monto = $this->limpiarMonto($fila[$columnas['importe']] ?? '');

                if ($terminal === '' || abs($monto) < 0.00001) {
                    continue;
                }

                $datosRuta = $this->extraerRuta($fila[$columnas['ruta']] ?? '');
                $filas->push([
                    'ruta_id' => $datosRuta['id'],
                    'ruta' => $datosRuta['nombre'],
                    'fecha' => $datosRuta['fecha'],
                    'terminal' => $terminal,
                    'agencia' => $this->limpiarTexto($fila[$columnas['agencia']] ?? ''),
                    'tipo' => $monto < 0 ? 'Retiro' : 'Depósito',
                    'monto_asignado' => abs($monto),
                ]);
            }

            return $filas->values();
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<int, string|null>  $encabezados
     * @return array{ruta: int, agencia: int, terminal: int, importe: int}
     */
    private function mapearColumnas(array $encabezados): array
    {
        $normalizados = collect($encabezados)
            ->mapWithKeys(fn ($encabezado, $indice) => [$this->normalizarEncabezado((string) $encabezado) => $indice]);
        $columnas = [
            'ruta' => $normalizados->get('textbox11'),
            'agencia' => $normalizados->get('textbox19'),
            'terminal' => $normalizados->get('nterminal'),
            'importe' => $normalizados->get('ingresoprocesado'),
        ];
        $posiciones = ['ruta' => 0, 'agencia' => 2, 'terminal' => 3, 'importe' => 4];

        foreach ($posiciones as $columna => $posicion) {
            $columnas[$columna] ??= array_key_exists($posicion, $encabezados) ? $posicion : null;
        }

        if (collect($columnas)->containsStrict(null)) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'El CSV debe contener las columnas A (ruta), C (agencia), D (terminal) y E (importe).',
            ]);
        }

        /** @var array{ruta: int, agencia: int, terminal: int, importe: int} $columnas */
        return $columnas;
    }

    /**
     * @return array{id: string, nombre: string, fecha: string}
     */
    private function extraerRuta(mixed $valor): array
    {
        $rutaCompleta = $this->limpiarTexto($valor);

        if (preg_match('/^Ruta:\s*(\d+)\s*-\s*(.*?)\s+Fecha:\s*(\d{4}-\d{2}-\d{2})$/iu', $rutaCompleta, $coincidencias) === 1) {
            return [
                'id' => $coincidencias[1],
                'nombre' => $this->limpiarTexto($coincidencias[2]),
                'fecha' => $coincidencias[3],
            ];
        }

        return ['id' => '', 'nombre' => $rutaCompleta, 'fecha' => ''];
    }

    private function detectarSeparador(string $path): string
    {
        $primeraLinea = '';
        $handle = fopen($path, 'rb');

        if ($handle !== false) {
            $primeraLinea = (string) fgets($handle);
            fclose($handle);
        }

        $separadores = [',' => 0, ';' => 0, "\t" => 0, '|' => 0];

        foreach (array_keys($separadores) as $separador) {
            $separadores[$separador] = count(str_getcsv($primeraLinea, $separador));
        }

        arsort($separadores);

        return (string) array_key_first($separadores);
    }

    private function normalizarEncabezado(string $valor): string
    {
        $valor = preg_replace('/^\xEF\xBB\xBF/', '', $valor) ?? $valor;

        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $valor) ?? $valor);
    }

    private function limpiarTexto(mixed $valor): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $valor) ?? '');
    }

    private function limpiarMonto(mixed $valor): float
    {
        $valor = trim((string) $valor);
        $negativoPorParentesis = str_starts_with($valor, '(') && str_ends_with($valor, ')');
        $numero = (float) str_replace([',', '(', ')', 'RD$', '$', ' '], '', $valor);

        return $negativoPorParentesis ? -abs($numero) : $numero;
    }

    private function valorEsCero(mixed $valor): bool
    {
        $numero = str_replace([',', '(', ')', 'RD$', '$', ' '], '', trim((string) $valor));

        return $numero !== '' && is_numeric($numero) && abs((float) $numero) < 0.00001;
    }

    /**
     * @param  array<int, string|null>  $fila
     */
    private function filaVacia(array $fila): bool
    {
        return collect($fila)->every(fn ($valor) => trim((string) $valor) === '');
    }
}
