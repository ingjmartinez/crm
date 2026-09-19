<?php

namespace App\Services\RecursosHumanos;

use App\Models\Agencia;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NominaDomingoService
{
    /** @return array{horas_requeridas: float, monto_fijo: float} */
    public function configuracion(): array
    {
        $configuracion = Schema::hasTable('nomina_domingo_configuraciones') ? DB::table('nomina_domingo_configuraciones')->first() : null;
        $horasAlmacenadas = (float) ($configuracion->horas_requeridas ?? 8);
        $horasRequeridas = round($horasAlmacenadas * 60) / 60;

        return ['horas_requeridas' => $horasRequeridas, 'monto_fijo' => (float) ($configuracion->monto_fijo ?? 0)];
    }

    /** @param array{horas_requeridas: float|int|string, monto_fijo: float|int|string, horas_requeridas_horas?: int, horas_requeridas_minutos?: int} $datos */
    public function guardarConfiguracion(array $datos): void
    {
        DB::table('nomina_domingo_configuraciones')->updateOrInsert(['id' => 1], [
            'horas_requeridas' => $datos['horas_requeridas'], 'monto_fijo' => $datos['monto_fijo'],
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return Collection<int, array<string, float|string|null>> */
    public function generar(Carbon $fecha): Collection
    {
        $configuracion = $this->configuracion();
        $ventas = $this->ventas($fecha);
        $ponches = $this->ponches($fecha);
        $claves = $ventas->keys()->merge($ponches->keys())->unique();
        $cedulas = $claves->map(fn (string $clave): string => explode('|', $clave, 2)[1])->all();
        $empleados = $this->empleados($cedulas);
        $agencias = $this->agencias();

        return $claves->map(function (string $clave) use ($ventas, $ponches, $empleados, $agencias, $configuracion): array {
            [$terminal, $cedula] = explode('|', $clave, 2);
            $venta = $ventas->get($clave);
            $ponche = $ponches->get($clave);
            $entrada = $this->instante($ponche['entrada'] ?? null);
            $salidaPonche = $this->instante($ponche['salida'] ?? null);
            $ultimaTransaccion = $this->instante($venta['ultima_transaccion'] ?? null);
            $primeraTransaccion = $this->instante($venta['primera_transaccion'] ?? null);
            $salidaSegunTransaccion = $salidaPonche === null && $ultimaTransaccion !== null
                ? $ultimaTransaccion->copy()->addMinutes(5)
                : $ultimaTransaccion;
            $salidaEfectiva = collect([$salidaPonche, $salidaSegunTransaccion])
                ->filter()
                ->sortByDesc(fn (Carbon $instante): int => $instante->getTimestamp())
                ->first();
            $horas = $entrada && $salidaEfectiva
                ? max(0, $entrada->diffInSeconds($salidaEfectiva, false) / 3600)
                : 0;
            $cumple = $entrada !== null && $salidaEfectiva !== null && $horas >= $configuracion['horas_requeridas'];
            $agencia = $agencias->get($terminal);

            return [
                'terminal' => $terminal, 'cedula' => $this->cedulaParaMostrar($cedula),
                'empleado' => $empleados->get($cedula) ?: ($ponche['empleado'] ?? 'No identificado'),
                'empresa' => trim((string) ($agencia?->empresa ?? '')) ?: 'Sin empresa',
                'coordinador' => trim((string) ($agencia?->coordinador ?? '')) ?: 'Sin coordinador',
                'entrada' => $entrada?->toDateTimeString(), 'salida_ponche' => $salidaPonche?->toDateTimeString(),
                'primera_transaccion' => $primeraTransaccion?->toDateTimeString(), 'ultima_transaccion' => $ultimaTransaccion?->toDateTimeString(),
                'salida_efectiva' => $salidaEfectiva?->toDateTimeString(),
                'fuente_salida' => $this->fuenteSalida($salidaPonche, $ultimaTransaccion),
                'horas_trabajadas' => round($horas, 2), 'estatus' => $cumple ? 'Cumple' : 'No cumple',
                'monto_pagar' => $cumple ? $configuracion['monto_fijo'] : 0.0,
            ];
        })->sortBy(['empleado', 'terminal'])->values();
    }

    /** @return Collection<int, string> */
    public function empresas(): Collection
    {
        if (! Schema::hasTable('agencias')) {
            return collect();
        }

        return Agencia::query()->whereNotNull('empresa')->where('empresa', '<>', '')
            ->distinct()->orderBy('empresa')->pluck('empresa');
    }

    /**
     * @param  Collection<int, array<string, float|string|null>>  $filas
     * @return Collection<int, array{
     *     coordinador: string,
     *     agencias_asignadas: int,
     *     agencias_cumplieron: int,
     *     agencias_no_cumplieron: int,
     *     empleados_cumplieron: int,
     *     empleados_no_cumplieron: int,
     *     detalle_agencias_cumplieron: array<int, array{terminal: string, empresa: string, empleados: int}>,
     *     detalle_agencias_no_cumplieron: array<int, array{terminal: string, empresa: string, empleados: int}>,
     *     detalle_empleados_cumplieron: array<int, array<string, float|string|null>>,
     *     detalle_empleados_no_cumplieron: array<int, array<string, float|string|null>>
     * }>
     */
    public function resumenCoordinadores(Collection $filas): Collection
    {
        return $filas->groupBy('coordinador')->map(function (Collection $filasCoordinador, string $coordinador): array {
            $agencias = $filasCoordinador->groupBy('terminal')->map(function (Collection $filasAgencia, string $terminal): array {
                return [
                    'terminal' => $terminal,
                    'empresa' => (string) ($filasAgencia->first()['empresa'] ?? 'Sin empresa'),
                    'empleados' => $filasAgencia->count(),
                    'cumple' => $filasAgencia->contains('estatus', 'Cumple'),
                ];
            })->values();
            $cumplieron = $agencias->where('cumple', true)->values();
            $noCumplieron = $agencias->where('cumple', false)->values();
            $empleadosCumplieron = $filasCoordinador->where('estatus', 'Cumple')->values();
            $empleadosNoCumplieron = $filasCoordinador->where('estatus', 'No cumple')->values();

            return [
                'coordinador' => $coordinador,
                'agencias_asignadas' => $agencias->count(),
                'agencias_cumplieron' => $cumplieron->count(),
                'agencias_no_cumplieron' => $noCumplieron->count(),
                'empleados_cumplieron' => $empleadosCumplieron->count(),
                'empleados_no_cumplieron' => $empleadosNoCumplieron->count(),
                'detalle_agencias_cumplieron' => $cumplieron->map(fn (array $agencia): array => collect($agencia)->except('cumple')->all())->all(),
                'detalle_agencias_no_cumplieron' => $noCumplieron->map(fn (array $agencia): array => collect($agencia)->except('cumple')->all())->all(),
                'detalle_empleados_cumplieron' => $empleadosCumplieron->all(),
                'detalle_empleados_no_cumplieron' => $empleadosNoCumplieron->all(),
            ];
        })->sortBy('coordinador')->values();
    }

    /** @return Collection<string, array{primera_transaccion: string, ultima_transaccion: string}> */
    private function ventas(Carbon $fecha): Collection
    {
        if (! Schema::hasTable('gestion_agencias_ventas')) {
            return collect();
        }

        return DB::table('gestion_agencias_ventas')->whereDate('fecha_transaccion', $fecha->toDateString())
            ->whereNotNull('usuario_venta')->where('usuario_venta', '<>', '')->get(['terminal', 'usuario_venta', 'fecha_transaccion'])
            ->groupBy(fn (object $fila): string => $this->clave($fila->terminal, $fila->usuario_venta))
            ->map(fn (Collection $filas): array => [
                'primera_transaccion' => (string) $filas->min('fecha_transaccion'),
                'ultima_transaccion' => (string) $filas->max('fecha_transaccion'),
            ]);
    }

    /**
     * @return array{
     *     tradicional: array{archivo: float, api: float, diferencia: float},
     *     no_tradicional: array{archivo: float, api: float, diferencia: float}
     * }
     */
    public function conciliacionVentas(Carbon $fecha): array
    {
        $archivo = collect();
        if (Schema::hasTable('gestion_agencias_ventas')) {
            $archivo = DB::table('gestion_agencias_ventas')
                ->whereDate('fecha_transaccion', $fecha->toDateString())
                ->selectRaw('LOWER(TRIM(tipo)) as tipo_normalizado, SUM(COALESCE(total_apostado, 0)) as monto')
                ->groupByRaw('LOWER(TRIM(tipo))')
                ->pluck('monto', 'tipo_normalizado');
        }

        $api = collect();
        if (Schema::hasTable('vt_usuarios_bet')) {
            $api = DB::table('vt_usuarios_bet')
                ->where('fecha', $fecha->toDateString())
                ->selectRaw('LOWER(TRIM(tipo)) as tipo_normalizado, SUM(COALESCE(monto, 0)) as monto')
                ->groupByRaw('LOWER(TRIM(tipo))')
                ->pluck('monto', 'tipo_normalizado');
        }

        $totales = function (Collection $montos): array {
            return [
                'tradicional' => (float) $montos->get('tradicional', 0),
                'no_tradicional' => (float) ($montos->get('no tradicional', 0) + $montos->get('no_tradicional', 0)),
            ];
        };
        $totalesArchivo = $totales($archivo);
        $totalesApi = $totales($api);

        return collect(['tradicional', 'no_tradicional'])->mapWithKeys(fn (string $tipo): array => [
            $tipo => [
                'archivo' => $totalesArchivo[$tipo],
                'api' => $totalesApi[$tipo],
                'diferencia' => round($totalesArchivo[$tipo] - $totalesApi[$tipo], 2),
            ],
        ])->all();
    }

    /** @return Collection<string, array{entrada: ?string, salida: ?string, empleado: string}> */
    private function ponches(Carbon $fecha): Collection
    {
        $filas = collect();
        if (Schema::hasTable('asistencias_bet')) {
            $filas = $filas->merge(DB::table('asistencias_bet')->whereDate('fecha', $fecha->toDateString())
                ->get(['agencia_id as terminal', 'cedula', 'usuario as empleado', 'primer_login as entrada', 'ultimo_login as salida']));
        }
        if (Schema::hasTable('asistencias_net')) {
            $filas = $filas->merge(DB::table('asistencias_net')->whereDate('entrada', $fecha->toDateString())
                ->get(['terminal', 'identificacion as cedula', 'username as empleado', 'entrada', 'salida']));
        }

        return $filas->filter(fn (object $fila): bool => $this->normalizar($fila->cedula) !== '')
            ->groupBy(fn (object $fila): string => $this->clave($fila->terminal, $fila->cedula))
            ->map(fn (Collection $grupo): array => ['entrada' => $grupo->pluck('entrada')->filter()->min(), 'salida' => $grupo->pluck('salida')->filter()->max(), 'empleado' => (string) ($grupo->pluck('empleado')->filter()->first() ?? 'No identificado')]);
    }

    /** @param array<int, string> $cedulas */
    private function empleados(array $cedulas): Collection
    {
        if (! Schema::hasTable('empleados') || $cedulas === []) {
            return collect();
        }

        return DB::table('empleados')->whereNull('fechasalida')->get(['cedula', 'nombres', 'apellidos'])
            ->filter(fn (object $empleado): bool => in_array($this->normalizar($empleado->cedula), $cedulas, true))
            ->mapWithKeys(fn (object $empleado): array => [$this->normalizar($empleado->cedula) => trim("{$empleado->nombres} {$empleado->apellidos}")]);
    }

    /** @return Collection<string, Agencia> */
    private function agencias(): Collection
    {
        if (! Schema::hasTable('agencias')) {
            return collect();
        }

        return Agencia::query()->whereNotNull('terminal')->get(['terminal', 'empresa', 'coordinador'])
            ->keyBy(fn (Agencia $agencia): string => $this->normalizar($agencia->terminal));
    }

    private function clave(mixed $terminal, mixed $cedula): string
    {
        return $this->normalizar($terminal).'|'.$this->normalizar($cedula);
    }

    private function normalizar(mixed $valor): string
    {
        $digitos = (string) preg_replace('/\D+/', '', trim((string) $valor));
        if ($digitos === '') {
            return '';
        }

        $normalizado = ltrim($digitos, '0');

        return $normalizado === '' ? '0' : $normalizado;
    }

    private function cedulaParaMostrar(string $cedula): string
    {
        return strlen($cedula) <= 11
            ? str_pad($cedula, 11, '0', STR_PAD_LEFT)
            : $cedula;
    }

    private function instante(mixed $valor): ?Carbon
    {
        if ($valor === null || trim((string) $valor) === '') {
            return null;
        }

        return Carbon::parse($valor);
    }

    private function fuenteSalida(?Carbon $salidaPonche, ?Carbon $ultimaTransaccion): string
    {
        if ($salidaPonche === null && $ultimaTransaccion === null) {
            return 'Sin salida';
        }

        if ($salidaPonche === null) {
            return 'Última transacción + 5 minutos';
        }

        if ($ultimaTransaccion !== null && ($salidaPonche === null || $ultimaTransaccion->greaterThan($salidaPonche))) {
            return 'Última transacción';
        }

        return 'Ponche';
    }
}
