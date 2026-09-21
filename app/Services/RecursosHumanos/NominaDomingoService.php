<?php

namespace App\Services\RecursosHumanos;

use App\Models\Agencia;
use App\Models\CoordinadorOperador;
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
        $clavesPorUsuario = [];
        foreach ($ponches as $clave => $ponche) {
            [$terminal] = explode('|', $clave, 2);
            foreach ($ponche['usuarios'] as $usuario) {
                $claveUsuario = $terminal.'|'.$usuario;
                if (array_key_exists($claveUsuario, $clavesPorUsuario) && $clavesPorUsuario[$claveUsuario] !== $clave) {
                    $clavesPorUsuario[$claveUsuario] = null;
                } else {
                    $clavesPorUsuario[$claveUsuario] = $clave;
                }
            }
        }

        $ventasAsociadas = collect();
        foreach ($ventas as $claveVenta => $venta) {
            $clavePonche = array_key_exists($claveVenta, $clavesPorUsuario)
                ? $clavesPorUsuario[$claveVenta]
                : ($ponches->has($claveVenta) ? $claveVenta : null);
            $claveResultado = $clavePonche ?? 'venta:'.$claveVenta;
            $anterior = $ventasAsociadas->get($claveResultado);
            $ventasAsociadas->put($claveResultado, $anterior === null ? $venta : [
                'primera_transaccion' => min($anterior['primera_transaccion'], $venta['primera_transaccion']),
                'ultima_transaccion' => max($anterior['ultima_transaccion'], $venta['ultima_transaccion']),
                'usuario_venta' => $anterior['usuario_venta'].', '.$venta['usuario_venta'],
                'tradicional_cantidad' => $anterior['tradicional_cantidad'] + $venta['tradicional_cantidad'],
                'tradicional_monto' => $anterior['tradicional_monto'] + $venta['tradicional_monto'],
                'no_tradicional_cantidad' => $anterior['no_tradicional_cantidad'] + $venta['no_tradicional_cantidad'],
                'no_tradicional_monto' => $anterior['no_tradicional_monto'] + $venta['no_tradicional_monto'],
            ]);
        }

        $claves = $ventasAsociadas->keys()->merge($ponches->keys())->unique();
        $cedulas = $claves->map(fn (string $clave): string => explode('|', $clave, 2)[1])->all();
        $empleados = $this->empleados($cedulas);
        $agencias = $this->agencias();

        return $claves->map(function (string $clave) use ($ventasAsociadas, $ponches, $empleados, $agencias, $configuracion): array {
            $ventaSinPonche = str_starts_with($clave, 'venta:');
            [$terminal, $cedula] = explode('|', $ventaSinPonche ? substr($clave, 6) : $clave, 2);
            $venta = $ventasAsociadas->get($clave);
            $ponche = $ventaSinPonche ? null : $ponches->get($clave);
            $entrada = $this->instante($ponche['entrada'] ?? null);
            $salidaPonche = $this->instante($ponche['salida'] ?? null);
            $ultimaTransaccion = $this->instante($venta['ultima_transaccion'] ?? null);
            $primeraTransaccion = $this->instante($venta['primera_transaccion'] ?? null);
            $salidaAjustada = $salidaPonche !== null && $ultimaTransaccion !== null && $ultimaTransaccion->greaterThan($salidaPonche);
            $salidaEfectiva = $salidaAjustada ? $ultimaTransaccion->copy()->addMinutes(5) : $salidaPonche;
            $incidencia = match (true) {
                $entrada === null => 'Sin primer login',
                $salidaPonche === null => 'Sin último login',
                $salidaPonche->lessThan($entrada) => 'Último login anterior al primero',
                $primeraTransaccion !== null && $primeraTransaccion->lessThan($entrada) => 'Venta antes del primer login',
                default => null,
            };
            $horas = $entrada && $salidaEfectiva
                ? max(0, $entrada->diffInSeconds($salidaEfectiva, false) / 3600)
                : 0;
            $cumple = $incidencia === null && $horas >= $configuracion['horas_requeridas'];
            $agencia = $agencias->get($terminal);
            $nombreMaestra = $empleados->get($cedula);

            return [
                'terminal' => $terminal, 'cedula' => $this->cedulaParaMostrar($cedula),
                'empleado' => $nombreMaestra ?: ($ponche['empleado'] ?? 'No identificado'),
                'coincide_maestra' => $nombreMaestra !== null && $nombreMaestra !== '',
                'origen_identidad' => $ponche !== null ? 'Ponche' : ($nombreMaestra ? 'Maestra de empleados' : 'Usuario de venta sin verificar'),
                'empresa' => trim((string) ($agencia?->empresa ?? '')) ?: 'Sin empresa',
                'coordinador' => trim((string) ($agencia?->coordinador_nombre ?? '')) ?: 'Sin coordinador',
                'entrada' => $entrada?->toDateTimeString(), 'salida_ponche' => $salidaPonche?->toDateTimeString(),
                'primera_transaccion' => $primeraTransaccion?->toDateTimeString(), 'ultima_transaccion' => $ultimaTransaccion?->toDateTimeString(),
                'salida_efectiva' => $salidaEfectiva?->toDateTimeString(),
                'fuente_salida' => $salidaPonche === null ? 'Sin último login' : ($salidaAjustada ? 'Última venta + 5 minutos' : 'Último login'),
                'usuario_venta' => $venta['usuario_venta'] ?? null,
                'tradicional_cantidad' => $venta['tradicional_cantidad'] ?? 0,
                'tradicional_monto' => $venta['tradicional_monto'] ?? 0.0,
                'no_tradicional_cantidad' => $venta['no_tradicional_cantidad'] ?? 0,
                'no_tradicional_monto' => $venta['no_tradicional_monto'] ?? 0.0,
                'incidencia' => $incidencia,
                'horas_trabajadas' => round($horas, 2), 'estatus' => $incidencia !== null ? 'Revisar' : ($cumple ? 'Cumple' : 'No cumple'),
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
            $empleadosNoCumplieron = $filasCoordinador->where('estatus', '!=', 'Cumple')->values();

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

    /** @return Collection<string, array<string, float|int|string>> */
    private function ventas(Carbon $fecha): Collection
    {
        if (! Schema::hasTable('nomina_domingo_ventas')) {
            return collect();
        }

        return DB::table('nomina_domingo_ventas')->whereDate('fecha_transaccion', $fecha->toDateString())
            ->whereNotNull('usuario_venta')->where('usuario_venta', '<>', '')
            ->get(['terminal', 'usuario_venta', 'fecha_transaccion', 'tipo', 'total_apostado'])
            ->groupBy(fn (object $fila): string => $this->clave($fila->terminal, $fila->usuario_venta))
            ->map(fn (Collection $filas): array => [
                'primera_transaccion' => (string) $filas->min('fecha_transaccion'),
                'ultima_transaccion' => (string) $filas->max('fecha_transaccion'),
                'usuario_venta' => (string) $filas->first()->usuario_venta,
                'tradicional_cantidad' => $filas->where('tipo', 'Tradicional')->count(),
                'tradicional_monto' => (float) $filas->where('tipo', 'Tradicional')->sum('total_apostado'),
                'no_tradicional_cantidad' => $filas->where('tipo', 'No Tradicional')->count(),
                'no_tradicional_monto' => (float) $filas->where('tipo', 'No Tradicional')->sum('total_apostado'),
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
        if (Schema::hasTable('nomina_domingo_ventas')) {
            $archivo = DB::table('nomina_domingo_ventas')
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

    /** @return Collection<string, array{entrada: ?string, salida: ?string, empleado: string, usuarios: array<int, string>}> */
    private function ponches(Carbon $fecha): Collection
    {
        $filas = collect();
        if (Schema::hasTable('asistencias_bet')) {
            $filas = $filas->merge(DB::table('asistencias_bet')->whereDate('fecha', $fecha->toDateString())
                ->get(['agencia_id as terminal', 'cedula', 'usuario', 'usuario as empleado', 'primer_login as entrada', 'ultimo_login as salida']));
        }
        if (Schema::hasTable('asistencias_net')) {
            $filas = $filas->merge(DB::table('asistencias_net')->whereDate('entrada', $fecha->toDateString())
                ->get(['terminal', 'identificacion as cedula', 'usuario', 'username as empleado', 'entrada', 'salida']));
        }

        return $filas->filter(fn (object $fila): bool => $this->normalizar($fila->cedula) !== '' || $this->normalizar($fila->usuario) !== '')
            ->groupBy(fn (object $fila): string => $this->clave($fila->terminal, $this->normalizar($fila->cedula) !== '' ? $fila->cedula : $fila->usuario))
            ->map(fn (Collection $grupo): array => [
                'entrada' => $grupo->pluck('entrada')->filter()->min(),
                'salida' => $grupo->pluck('salida')->filter()->max(),
                'empleado' => (string) ($grupo->pluck('empleado')->filter()->first() ?? 'No identificado'),
                'usuarios' => $grupo->pluck('usuario')->map(fn (mixed $usuario): string => $this->normalizar($usuario))->filter()->unique()->values()->all(),
            ]);
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

        $agencias = Agencia::query()->whereNotNull('terminal')->get(['id', 'terminal', 'empresa']);

        if (Schema::hasTable('coordinador_operador') && Schema::hasTable('coordinador_operador_agencia')) {
            $coordinadores = CoordinadorOperador::query()
                ->where('puesto', 'coordinador')
                ->with('agencias:id')
                ->get(['id', 'nombre', 'apellido'])
                ->flatMap(fn (CoordinadorOperador $coordinador): Collection => $coordinador->agencias->map(
                    fn (Agencia $agencia): array => [
                        'agencia_id' => $agencia->id,
                        'nombre' => trim($coordinador->nombre.' '.$coordinador->apellido),
                    ]
                ))->keyBy('agencia_id');

            $agencias->each(function (Agencia $agencia) use ($coordinadores): void {
                $agencia->setAttribute('coordinador_nombre', $coordinadores->get($agencia->id)['nombre'] ?? null);
            });
        }

        return $agencias
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
}
