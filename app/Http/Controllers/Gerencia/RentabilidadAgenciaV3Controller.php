<?php

namespace App\Http\Controllers\Gerencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultarRentabilidadAgenciaV3Request;
use App\Models\ClasificacionCuenta;
use App\Models\CuentaContable;
use App\Models\EntradaDiario;
use App\Models\RentabilidadAgencia;
use App\Models\RentabilidadAgenciaR20;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RentabilidadAgenciaV3Controller extends Controller
{
    private const CUENTAS_INGRESO = ['400110001', '400120001', '400130001', '400130002', '400130003'];

    public function index(ConsultarRentabilidadAgenciaV3Request $request): View
    {
        $datos = $request->validated();
        $mesCierre = (string) (($datos['mes_cierre'] ?? null) ?: now()->subMonth()->format('Y-m'));
        $empresa = trim((string) ($datos['empresa'] ?? ''));
        $consultar = $request->filled('mes_cierre');
        $empresas = RentabilidadAgencia::query()->activas()
            ->whereNotNull('empresa')->whereRaw("TRIM(empresa) <> ''")
            ->selectRaw('TRIM(empresa) AS empresa')->distinct()->orderBy('empresa')->pluck('empresa');
        $filas = collect();
        $resumen = null;
        $mesesAnio = collect();
        $mesesTrimestre = collect();
        $graficoMensual = collect();

        if ($consultar) {
            $cierre = Carbon::createFromFormat('Y-m', $mesCierre)->startOfMonth();
            $inicioAnio = $cierre->copy()->startOfYear();
            $inicioTrimestre = $cierre->copy()->subMonths(2);
            $inicioConsulta = $inicioTrimestre->lt($inicioAnio) ? $inicioTrimestre : $inicioAnio;
            $mesesAnio = $this->mesesEntre($inicioAnio, $cierre);
            $mesesTrimestre = $this->mesesEntre($inicioTrimestre, $cierre);
            $mesesDetalle = $mesesAnio->merge($mesesTrimestre)->unique()->sort()->values();
            $agencias = RentabilidadAgencia::query()->activas()
                ->whereNotNull('terminal')->where('terminal', '<>', '')
                ->when($empresa !== '', fn ($query) => $query->whereRaw('TRIM(empresa) = ?', [$empresa]))
                ->orderBy('terminal')->get(['terminal', 'nombre_agencia', 'agencia', 'empresa']);
            $terminales = $agencias->pluck('terminal')->map(fn (mixed $valor): string => trim((string) $valor))->unique()->values();
            $cuentasGasto = CuentaContable::query()
                ->whereIn('cuenta', ClasificacionCuenta::cuentasActivas()->all())
                ->whereRaw('LOWER(TRIM(tipo)) = ?', ['gasto'])
                ->pluck('cuenta');
            $ingresos = $this->movimientosMensuales($terminales, self::CUENTAS_INGRESO, $inicioConsulta, $cierre, 'ingreso');
            $gastos = $this->movimientosMensuales($terminales, $cuentasGasto->all(), $inicioConsulta, $cierre, 'gasto');
            $r20 = (float) RentabilidadAgenciaR20::query()->where('terminal', '*')
                ->orderByRaw('CASE WHEN mes = ? THEN 0 ELSE 1 END', ['GLOBAL'])
                ->orderByDesc('updated_at')->value('monto');

            $filas = $agencias->unique('terminal')->map(function (RentabilidadAgencia $agencia) use ($mesesDetalle, $mesesAnio, $mesesTrimestre, $ingresos, $gastos, $r20): array {
                $terminal = trim((string) $agencia->terminal);
                $mensual = $mesesDetalle->mapWithKeys(function (string $mes) use ($terminal, $ingresos, $gastos, $r20): array {
                    $ingreso = (float) ($ingresos[$terminal][$mes] ?? 0);
                    $gastoContable = (float) ($gastos[$terminal][$mes] ?? 0);
                    $conMovimientos = ($ingresos[$terminal] ?? collect())->has($mes)
                        || ($gastos[$terminal] ?? collect())->has($mes);
                    $gasto = $conMovimientos ? $gastoContable + $r20 : 0.0;

                    return [$mes => [
                        'mes' => $mes,
                        'ingresos' => $ingreso,
                        'gastos' => $gasto,
                        'utilidad' => $ingreso - $gasto,
                        'con_movimientos' => $conMovimientos,
                    ]];
                });
                $anio = $this->resumirPeriodo($mensual, $mesesAnio);
                $trimestre = $this->resumirPeriodo($mensual, $mesesTrimestre);

                return [
                    'terminal' => $terminal,
                    'nombre' => trim((string) ($agencia->nombre_agencia ?: $agencia->agencia ?: $terminal)),
                    'empresa' => trim((string) $agencia->empresa),
                    'anio' => $anio,
                    'trimestre' => $trimestre,
                    'clasificacion' => $this->clasificar($anio, $trimestre),
                    'diagnostico' => $this->diagnosticar($anio, $trimestre),
                    'mensual' => $mensual->values()->all(),
                ];
            })->values();

            $resumen = [
                'terminales' => $filas->count(),
                'rentables_anio' => $filas->filter(fn (array $fila): bool => $fila['anio']['completo'] && $fila['anio']['utilidad'] > 0)->count(),
                'rentables_trimestre' => $filas->filter(fn (array $fila): bool => $fila['trimestre']['completo'] && $fila['trimestre']['utilidad'] > 0)->count(),
                'incompletas' => $filas->filter(fn (array $fila): bool => $fila['clasificacion'] === 'Datos incompletos')->count(),
                'perdida_sostenida' => $filas->filter(fn (array $fila): bool => $fila['clasificacion'] === 'Pérdida sostenida')->count(),
                'en_observacion' => $filas->filter(fn (array $fila): bool => $fila['clasificacion'] === 'En observación')->count(),
                'utilidad_anio' => (float) $filas->sum(fn (array $fila): float => $fila['anio']['utilidad']),
                'utilidad_trimestre' => (float) $filas->sum(fn (array $fila): float => $fila['trimestre']['utilidad']),
            ];
            $movimientosPorMes = $filas->flatMap(fn (array $fila): array => $fila['mensual'])->groupBy('mes');
            $graficoMensual = $mesesDetalle->mapWithKeys(fn (string $mes): array => [$mes => [
                'ingresos' => (float) $movimientosPorMes->get($mes, collect())->sum('ingresos'),
                'gastos' => (float) $movimientosPorMes->get($mes, collect())->sum('gastos'),
                'terminales_con_movimientos' => $movimientosPorMes->get($mes, collect())->where('con_movimientos', true)->count(),
            ]]);
        }

        return view('gerencia.rentabilidad-agencia-v3', compact('mesCierre', 'empresa', 'empresas', 'consultar', 'filas', 'resumen', 'mesesAnio', 'mesesTrimestre', 'graficoMensual'));
    }

    /** @return Collection<int, string> */
    private function mesesEntre(Carbon $inicio, Carbon $fin): Collection
    {
        $meses = collect();
        for ($fecha = $inicio->copy(); $fecha->lte($fin); $fecha->addMonth()) {
            $meses->push($fecha->format('Y-m'));
        }

        return $meses;
    }

    /** @param Collection<int, string> $terminales
     * @param  array<int, string>  $cuentas
     * @return Collection<string, Collection<string, float>>
     */
    private function movimientosMensuales(Collection $terminales, array $cuentas, Carbon $inicio, Carbon $cierre, string $tipo): Collection
    {
        if ($terminales->isEmpty() || $cuentas === []) {
            return collect();
        }

        $expresionMes = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', fecha)"
            : "DATE_FORMAT(fecha, '%Y-%m')";
        $importe = $tipo === 'ingreso'
            ? 'COALESCE(credito, 0) - COALESCE(debito, 0)'
            : 'COALESCE(debito, 0) - COALESCE(credito, 0)';

        return EntradaDiario::query()
            ->whereIn('id_viejo', $terminales->all())
            ->whereIn('cuenta', $cuentas)
            ->whereBetween('fecha', [$inicio->toDateString(), $cierre->copy()->endOfMonth()->toDateString()])
            ->selectRaw("TRIM(CAST(id_viejo AS CHAR)) AS terminal, {$expresionMes} AS mes, SUM({$importe}) AS total")
            ->groupByRaw("TRIM(CAST(id_viejo AS CHAR)), {$expresionMes}")
            ->get()
            ->groupBy('terminal')
            ->map(fn (Collection $filas): Collection => $filas->mapWithKeys(fn (EntradaDiario $fila): array => [(string) $fila->mes => (float) $fila->total]));
    }

    /** @param Collection<string, array<string, mixed>> $mensual
     * @param  Collection<int, string>  $meses
     * @return array{ingresos: float, gastos: float, utilidad: float, margen: float|null, meses_rentables: int, meses_perdida: int, meses_con_movimientos: int, meses: int, completo: bool}
     */
    private function resumirPeriodo(Collection $mensual, Collection $meses): array
    {
        $filas = $mensual->only($meses->all());
        $ingresos = (float) $filas->sum('ingresos');
        $gastos = (float) $filas->sum('gastos');

        $mesesConMovimientos = $filas->filter(fn (array $fila): bool => $fila['con_movimientos'])->count();

        return [
            'ingresos' => $ingresos,
            'gastos' => $gastos,
            'utilidad' => $ingresos - $gastos,
            'margen' => $ingresos > 0 ? ($ingresos - $gastos) / $ingresos * 100 : null,
            'meses_rentables' => $filas->filter(fn (array $fila): bool => $fila['utilidad'] > 0)->count(),
            'meses_perdida' => $filas->filter(fn (array $fila): bool => $fila['con_movimientos'] && $fila['utilidad'] < 0)->count(),
            'meses_con_movimientos' => $mesesConMovimientos,
            'meses' => $meses->count(),
            'completo' => $mesesConMovimientos === $meses->count(),
        ];
    }

    /** @param array<string, mixed> $anio
     * @param  array<string, mixed>  $trimestre
     */
    private function clasificar(array $anio, array $trimestre): string
    {
        if ($anio['meses_con_movimientos'] === 0 && $trimestre['meses_con_movimientos'] === 0) {
            return 'Sin movimientos';
        }
        if (! $anio['completo'] || ! $trimestre['completo']) {
            return 'Datos incompletos';
        }
        if ($anio['utilidad'] > 0 && $trimestre['utilidad'] > 0 && $trimestre['meses_rentables'] === 3) {
            return 'Rentable sostenida';
        }
        if ($anio['utilidad'] > 0 && $trimestre['utilidad'] > 0) {
            return 'Rentable irregular';
        }
        if ($anio['utilidad'] <= 0 && $trimestre['utilidad'] > 0) {
            return 'En recuperación';
        }
        if ($anio['utilidad'] > 0) {
            return 'En deterioro';
        }

        if ($anio['utilidad'] < 0 && $trimestre['utilidad'] < 0 && $trimestre['meses_perdida'] >= 2) {
            return 'Pérdida sostenida';
        }

        return 'En observación';
    }

    /** @param array<string, mixed> $anio
     * @param  array<string, mixed>  $trimestre
     */
    private function diagnosticar(array $anio, array $trimestre): string
    {
        if (! $anio['completo'] || ! $trimestre['completo']) {
            return 'Faltan meses con asientos; el resultado acumulado es parcial.';
        }
        if ($anio['utilidad'] < 0 && $trimestre['utilidad'] < 0 && $trimestre['meses_perdida'] >= 2) {
            return 'Pérdida acumulada y al menos dos meses recientes con pérdida. Revisar la continuidad de la terminal.';
        }
        if ($anio['utilidad'] > 0 && $trimestre['utilidad'] <= 0) {
            return 'Año positivo, trimestre sin utilidad. Revisar la tendencia antes de decidir.';
        }
        if ($anio['utilidad'] <= 0 && $trimestre['utilidad'] > 0) {
            return 'Acumulado sin utilidad, trimestre positivo. Seguir la recuperación.';
        }
        if ($anio['utilidad'] > 0 && $trimestre['utilidad'] > 0) {
            return 'Acumulado y trimestre positivos; confirmar la estabilidad mensual.';
        }

        return 'La pérdida no es sostenida según los tres meses recientes; continuar en observación.';
    }
}
