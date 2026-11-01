<?php

namespace App\Services;

use App\Models\CatalogoJuego;
use App\Models\ClasificacionCuenta;
use App\Models\EntradaDiario;
use App\Models\RentabilidadAgenciaR20;
use App\Models\RentabilidadAgenciaV5;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RentabilidadAgenciaV6Service
{
    public const PRODUCTOS = ['Tradicional', 'No Tradicional', 'Recarga'];

    public const IMPORTES = ['ventas', 'premios_sacados', 'premios_propios', 'premios_no_propios', 'utilidad_propia', 'utilidad_general', 'gastos', 'balance_propio', 'balance_neto'];

    private const MEDIDAS = ['ventas', 'premios_sacados', 'premios_propios', 'premios_no_propios', 'costos', 'gastos_contables', 'gastos_fijos'];

    public function __construct(private readonly ClasificadorRentabilidadMensual $clasificador) {}

    /** @param array<string, mixed> $filtros @return array{0: Carbon, 1: Carbon} */
    public function rango(array $filtros): array
    {
        $anio = (int) ($filtros['anio'] ?? now()->year);

        return [Carbon::create($anio, (int) ($filtros['mes_inicio'] ?? now()->month), 1)->startOfDay(), Carbon::create($anio, (int) ($filtros['mes_fin'] ?? now()->month), 1)->endOfMonth()];
    }

    /** @param array<string, mixed> $filtros @return list<string> */
    public function productos(array $filtros): array
    {
        return array_values(array_unique($filtros['productos'] ?? (! empty($filtros['productos_filtrados']) ? [] : self::PRODUCTOS)));
    }

    /** @param array<string, mixed> $filtros @return Collection<int, RentabilidadAgenciaV5> */
    public function agencias(array $filtros): Collection
    {
        $empresa = trim((string) ($filtros['empresa'] ?? ''));

        return RentabilidadAgenciaV5::query()->activas()->whereNotNull('terminal')->where('terminal', '<>', '')
            ->when($empresa !== '', fn ($query) => $query->whereRaw('TRIM(empresa) = ?', [$empresa]))
            ->when(($filtros['alcance'] ?? '') === 'terminal', fn ($query) => $query->where('terminal', trim((string) ($filtros['terminal'] ?? ''))))
            ->orderBy('terminal')->get(['terminal', 'nombre_agencia', 'agencia', 'sistema', 'empresa']);
    }

    /** @param array<string, mixed> $filtros @return array{filas: Collection, totales: array<string, float>, desde: Carbon, hasta: Carbon} */
    public function reporte(array $filtros, bool $diario = false): array
    {
        [$desde, $hasta] = $this->rango($filtros);
        $agencias = $this->agencias($filtros);
        $mediciones = $this->mediciones($agencias, $desde, $hasta, $this->productos($filtros), $diario);
        $r20 = (float) RentabilidadAgenciaR20::query()->where('terminal', '*')
            ->orderByRaw('CASE WHEN mes = ? THEN 0 ELSE 1 END', ['GLOBAL'])->orderByDesc('updated_at')->value('monto');
        $filas = $agencias->map(function (RentabilidadAgenciaV5 $agencia) use ($desde, $hasta, $mediciones, $r20, $diario): array {
            $terminal = trim((string) $agencia->terminal);
            $periodos = [];
            for ($fecha = $desde->copy(); $fecha->lte($hasta); $diario ? $fecha->addDay() : $fecha->addMonth()) {
                $periodo = $fecha->format($diario ? 'Y-m-d' : 'Y-m');
                $datos = (array) $mediciones->get($terminal.'|'.$periodo, []);
                $ventas = (float) ($datos['ventas'] ?? 0);
                $propios = (float) ($datos['premios_propios'] ?? 0);
                $ajenos = (float) ($datos['premios_no_propios'] ?? 0);
                $centavosR20 = (int) round($r20 * 100);
                $r20Periodo = $diario
                    ? (intdiv($centavosR20, $fecha->daysInMonth) + ($fecha->day <= abs($centavosR20 % $fecha->daysInMonth) ? ($centavosR20 <=> 0) : 0)) / 100
                    : $r20;
                $gastos = round((float) ($datos['costos'] ?? 0) + (float) ($datos['gastos_contables'] ?? 0) + $r20Periodo, 2);
                $periodos[] = [
                    'mes' => $periodo, 'ventas' => $ventas, 'premios_sacados' => (float) ($datos['premios_sacados'] ?? 0),
                    'premios_propios' => $propios, 'premios_no_propios' => $ajenos,
                    'utilidad_propia' => round($ventas - $propios, 2), 'utilidad_general' => round($ventas - $propios - $ajenos, 2),
                    'costos' => (float) ($datos['costos'] ?? 0), 'gastos_contables' => (float) ($datos['gastos_contables'] ?? 0),
                    'r20' => $r20Periodo, 'gastos' => $gastos, 'balance_propio' => round($ventas - $propios - $gastos, 2),
                    'balance_neto' => round($ventas - $propios - $ajenos - $gastos, 2),
                ];
            }
            $totales = collect(self::IMPORTES)->mapWithKeys(fn (string $campo): array => [$campo => round(array_sum(array_column($periodos, $campo)), 2)])->all();

            return [
                'terminal' => $terminal, 'nombre' => trim((string) ($agencia->nombre_agencia ?: $agencia->agencia ?: $terminal)),
                'empresa' => trim((string) $agencia->empresa), 'periodos' => $periodos, ...$totales,
                'clasificacion_propia' => $this->clasificador->clasificar($periodos, 'balance_propio'),
                'clasificacion_neta' => $this->clasificador->clasificar($periodos, 'balance_neto'),
            ];
        });

        return ['filas' => $filas, 'totales' => collect(self::IMPORTES)->mapWithKeys(fn (string $campo): array => [$campo => round($filas->sum($campo), 2)])->all(), 'desde' => $desde, 'hasta' => $hasta, 'resumen_clasificaciones' => $this->resumenClasificaciones($filas)];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return array{total: int, con_ventas: int, sin_ventas: int, categorias: list<array{nombre: string, color: string, propia: int, neta: int}>}
     */
    private function resumenClasificaciones(Collection $filas): array
    {
        $terminales = $filas->unique('terminal');
        $propias = $terminales->countBy('clasificacion_propia.nombre');
        $netas = $terminales->countBy('clasificacion_neta.nombre');
        $categorias = collect(ClasificadorRentabilidadMensual::UMBRALES)
            ->push(['nombre' => 'Sin ventas', 'color' => 'secondary'])
            ->map(fn (array $categoria): array => [
                'nombre' => $categoria['nombre'],
                'color' => $categoria['color'],
                'propia' => (int) $propias->get($categoria['nombre'], 0),
                'neta' => (int) $netas->get($categoria['nombre'], 0),
            ])->all();
        $sinVentas = (int) $propias->get('Sin ventas', 0);

        return ['total' => $terminales->count(), 'con_ventas' => $terminales->count() - $sinVentas, 'sin_ventas' => $sinVentas, 'categorias' => $categorias];
    }

    private function periodoSql(string $columna, bool $diario): string
    {
        if ($diario) {
            return 'DATE('.$columna.')';
        }

        return DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', ".$columna.')' : 'DATE_FORMAT('.$columna.", '%Y-%m')";
    }

    private function terminalSql(string $columna): string
    {
        return DB::connection()->getDriverName() === 'mysql'
            ? 'CONVERT('.$columna.' USING utf8mb4) COLLATE utf8mb4_unicode_ci'
            : $columna;
    }

    /** @param Collection<int, RentabilidadAgenciaV5> $agencias @param list<string> $productos @return Collection<string, object> */
    private function mediciones(Collection $agencias, Carbon $desde, Carbon $hasta, array $productos, bool $diario): Collection
    {
        if ($agencias->isEmpty()) {
            return collect();
        }
        $ramas = [];
        $productosPremios = array_values(array_intersect($productos, ['Tradicional', 'No Tradicional']));
        $premiosFiltro = count($productosPremios) === 2 ? null : CatalogoJuego::query()->whereIn('tipo', $productosPremios)->pluck('producto_id')->map(fn ($id): string => (string) $id)->all();
        foreach (['bet', 'net'] as $sistema) {
            $terminales = $agencias->filter(function (RentabilidadAgenciaV5 $agencia) use ($sistema): bool {
                $nombre = mb_strtoupper(trim((string) $agencia->sistema));

                return $sistema === 'bet' ? ! in_array($nombre, ['LOTONET', 'LOTENET'], true) : $nombre !== 'LOTOBET';
            })->pluck('terminal')->unique()->all();
            if ($terminales === []) {
                continue;
            }
            $fuentes = [
                ['vt_usuarios_'.$sistema, ['ventas' => 'COALESCE(monto, 0)'], count($productos) === count(self::PRODUCTOS) ? null : $productos, 'tipo'],
                ['premios_'.$sistema, ['premios_sacados' => 'COALESCE(monto, 0)'], $premiosFiltro, 'producto_id'],
                ['pagos_misma_empresa_'.$sistema, [
                    'premios_propios' => 'CASE WHEN agencia_id = pagado_agencia_id THEN COALESCE(monto, 0) ELSE 0 END',
                    'premios_no_propios' => 'CASE WHEN agencia_id <> pagado_agencia_id THEN COALESCE(monto, 0) ELSE 0 END',
                ], $premiosFiltro, 'producto_id'],
                ['pagos_aotra_empresa_'.$sistema, ['premios_no_propios' => 'COALESCE(monto, 0)'], $premiosFiltro, 'producto_id'],
            ];
            foreach ($fuentes as [$tabla, $medidas, $filtro, $columna]) {
                $periodo = $this->periodoSql('fecha', $diario);
                $consulta = DB::table($tabla)->whereIn('agencia_id', $terminales)
                    ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
                    ->when($filtro !== null, fn (Builder $query) => $query->whereIn($columna, $filtro))
                    ->selectRaw($this->terminalSql('agencia_id').' AS terminal, '.$periodo.' AS periodo');
                foreach (self::MEDIDAS as $medida) {
                    $consulta->selectRaw(isset($medidas[$medida]) ? 'SUM('.$medidas[$medida].') AS '.$medida : '0 AS '.$medida);
                }
                $ramas[] = $consulta->groupByRaw('agencia_id, '.$periodo);
            }
        }
        $clasificaciones = ClasificacionCuenta::clasificacionesActivas();
        if ($clasificaciones->isNotEmpty()) {
            $periodo = $this->periodoSql('movimientos.fecha', $diario);
            $importe = '(COALESCE(movimientos.debito, 0) - COALESCE(movimientos.credito, 0))';
            $costos = EntradaDiario::query()->from('entradas_diario as movimientos')
                ->join('cuentas_contables as cuentas', 'cuentas.cuenta', '=', 'movimientos.cuenta')
                ->whereIn('movimientos.id_viejo', $agencias->pluck('terminal')->unique()->all())
                ->whereBetween('movimientos.fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->whereIn('movimientos.cuenta', $clasificaciones->keys()->all())
                ->whereIn(DB::raw('LOWER(TRIM(cuentas.tipo))'), ['costo', 'gasto'])
                ->selectRaw($this->terminalSql('movimientos.id_viejo').' AS terminal, '.$periodo.' AS periodo');
            foreach (self::MEDIDAS as $medida) {
                $expresion = match ($medida) {
                    'costos' => "SUM(CASE WHEN LOWER(TRIM(cuentas.tipo)) = 'costo' THEN ".$importe.' ELSE 0 END)',
                    'gastos_contables' => "SUM(CASE WHEN LOWER(TRIM(cuentas.tipo)) = 'gasto' THEN ".$importe.' ELSE 0 END)',
                    default => '0',
                };
                $costos->selectRaw($expresion.' AS '.$medida);
            }
            if (DB::connection()->getDriverName() === 'mysql') {
                $costos->forceIndex($agencias->pluck('terminal')->unique()->count() === 1 ? 'entradas_diario_id_viejo_index' : 'entradas_diario_cuenta_index');
            }
            $ramas[] = $costos->groupByRaw('movimientos.id_viejo, '.$periodo)->toBase();
        }
        $union = array_shift($ramas);
        foreach ($ramas as $rama) {
            $union->unionAll($rama);
        }

        return DB::query()->fromSub($union, 'flujos')->select('terminal', 'periodo')
            ->selectRaw(implode(', ', array_map(fn (string $campo): string => 'SUM('.$campo.') AS '.$campo, self::MEDIDAS)))
            ->groupBy('terminal', 'periodo')->get()->keyBy(fn (object $fila): string => $fila->terminal.'|'.$fila->periodo);
    }

    /** @param array<string, mixed> $filtros @return array<string, mixed> */
    public function cuentas(array $filtros): array
    {
        [$desde, $hasta] = $this->rango($filtros);
        $agencia = $this->agencias([...$filtros, 'alcance' => 'terminal'])->first();
        abort_if($agencia === null, 404, 'No se encontro la terminal solicitada.');
        $clasificaciones = ClasificacionCuenta::clasificacionesActivas();
        $movimientos = collect();
        if ($clasificaciones->isNotEmpty()) {
            $movimientos = EntradaDiario::query()->from('entradas_diario as movimientos')
                ->join('cuentas_contables as cuentas', 'cuentas.cuenta', '=', 'movimientos.cuenta')
                ->where('movimientos.id_viejo', $agencia->terminal)
                ->whereBetween('movimientos.fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->whereIn('movimientos.cuenta', $clasificaciones->keys()->all())
                ->whereIn(DB::raw('LOWER(TRIM(cuentas.tipo))'), ['costo', 'gasto'])
                ->selectRaw("movimientos.cuenta AS cuenta, COALESCE(NULLIF(TRIM(cuentas.descripcion), ''), 'Sin descripcion') AS descripcion, LOWER(TRIM(cuentas.tipo)) AS tipo, SUM(COALESCE(movimientos.debito, 0) - COALESCE(movimientos.credito, 0)) AS importe")
                ->groupByRaw('movimientos.cuenta, cuentas.descripcion, LOWER(TRIM(cuentas.tipo))')->orderBy('movimientos.cuenta')
                ->get()->map(fn ($fila): array => ['cuenta' => (string) $fila->cuenta, 'descripcion' => $fila->descripcion, 'tipo' => $fila->tipo, 'clasificacion' => $clasificaciones->get($fila->cuenta), 'importe' => (float) $fila->importe]);
        }
        $meses = ($hasta->year - $desde->year) * 12 + $hasta->month - $desde->month + 1;
        $r20 = (float) RentabilidadAgenciaR20::query()->where('terminal', '*')->orderByRaw('CASE WHEN mes = ? THEN 0 ELSE 1 END', ['GLOBAL'])->orderByDesc('updated_at')->value('monto');
        $movimientos->push(['cuenta' => 'R20', 'descripcion' => 'Gasto R20 manual ('.$meses.' meses)', 'tipo' => 'gasto', 'clasificacion' => 'FIJO', 'importe' => $r20 * $meses]);

        return ['terminal' => $agencia->terminal, 'agencia' => $agencia->nombre_agencia ?: $agencia->agencia ?: $agencia->terminal, 'movimientos' => $movimientos->values()->all(), 'total' => (float) $movimientos->sum('importe')];
    }
}
