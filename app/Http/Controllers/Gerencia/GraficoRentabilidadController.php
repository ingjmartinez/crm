<?php

namespace App\Http\Controllers\Gerencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultarGraficoRentabilidadRequest;
use App\Models\CatalogoJuego;
use App\Models\ClasificacionCuenta;
use App\Models\EntradaDiario;
use App\Models\GraficoRentabilidad;
use App\Models\RentabilidadAgenciaR20;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class GraficoRentabilidadController extends Controller
{
    private const PRODUCTOS = ['Tradicional', 'No Tradicional', 'Recarga'];

    /** @var array<string, list<string>> Groups 0 (unknown), 1 (traditional), 2 (non-traditional), 3 (both). */
    private const GRUPOS_PREMIOS = ['Tradicional' => ['1', '3'], 'No Tradicional' => ['2', '3']];

    public function index(ConsultarGraficoRentabilidadRequest $request): View
    {
        return $this->cargar($request, $request->input('periodo', 'mes') === 'mes');
    }

    public function datos(ConsultarGraficoRentabilidadRequest $request): JsonResponse
    {
        abort_unless($request->input('periodo', 'mes') === 'mes' && $request->filled('mes'), 422);

        return response()->json($this->cargar($request)->getData()['datosCargados']);
    }

    public function cargar(ConsultarGraficoRentabilidadRequest $request, bool $cargarDatos = true): View
    {
        $datos = $request->validated();
        $mostrarResumen = $request->boolean('mostrar_resumen');
        $mes = (string) (($datos['mes'] ?? null) ?: now()->format('Y-m'));
        $periodo = (string) ($datos['periodo'] ?? 'mes');
        $mesDesde = (string) ($datos['mes_desde'] ?? $mes);
        $mesHasta = (string) ($datos['mes_hasta'] ?? $mes);
        $anio = (int) ($datos['anio'] ?? now()->year);
        [$inicio, $fin, $etiquetaPeriodo] = match ($periodo) {
            'rango' => [Carbon::createFromFormat('!Y-m', $mesDesde)->startOfMonth(), Carbon::createFromFormat('!Y-m', $mesHasta)->endOfMonth(), $mesDesde.' a '.$mesHasta],
            'anio' => [Carbon::create($anio, 1, 1)->startOfDay(), Carbon::create($anio, 12, 31)->endOfDay(), (string) $anio],
            default => [Carbon::createFromFormat('!Y-m', $mes)->startOfMonth(), Carbon::createFromFormat('!Y-m', $mes)->endOfMonth(), $mes],
        };
        $empresa = trim((string) ($datos['empresa'] ?? ''));
        $ruta = trim((string) ($datos['ruta'] ?? ''));
        $terminal = trim((string) ($datos['terminal'] ?? ''));
        $alcance = (string) ($datos['alcance'] ?? ($terminal !== '' ? 'terminal' : 'todas'));
        $consultar = match ($periodo) {
            'rango' => $request->filled(['mes_desde', 'mes_hasta']),
            'anio' => $request->filled('anio'),
            default => $request->filled('mes'),
        };
        $productosSeleccionados = collect($datos['productos'] ?? (! empty($datos['productos_filtrados']) ? [] : self::PRODUCTOS))->unique()->values();
        $empresas = GraficoRentabilidad::query()->activas()
            ->whereNotNull('empresa')->whereRaw("TRIM(empresa) <> ''")
            ->selectRaw('TRIM(empresa) AS empresa')->distinct()->orderBy('empresa')->pluck('empresa');
        $rutasDisponibles = GraficoRentabilidad::query()->activas()
            ->whereNotNull('ruta')->whereRaw("TRIM(ruta) <> ''")
            ->selectRaw("COALESCE(TRIM(empresa), '') AS empresa, TRIM(ruta) AS ruta")
            ->distinct()->orderBy('ruta')->get();
        $rutas = $rutasDisponibles
            ->when($empresa !== '', fn (Collection $filas): Collection => $filas->where('empresa', $empresa))
            ->pluck('ruta')->unique()->values();
        $agenciasResumen = collect();
        $resumen = null;
        $ventasPorProducto = array_fill_keys(self::PRODUCTOS, 0.0);
        $ventasMensuales = ['meses' => [], 'etiquetas' => [], 'series' => []];
        $datosCargados = null;
        $mesesPorCargar = [];

        if ($consultar && $cargarDatos) {
            [$filtroVentas, $filtroPremios] = $this->filtrosProductos($productosSeleccionados);
            [$agenciasResumen, $resumen, $ventasPorProducto, $ventasMensuales, $datosCargados] = $this->resumenTodas($inicio, $fin, $empresa, $ruta, $alcance === 'terminal' ? $terminal : '', $filtroVentas, $filtroPremios);
        } elseif ($consultar) {
            for ($fecha = $inicio->copy()->startOfMonth(); $fecha->lte($fin); $fecha->addMonth()) {
                $mesesPorCargar[] = $fecha->format('Y-m');
                $ventasMensuales['meses'][] = $fecha->format('Y-m');
                $ventasMensuales['etiquetas'][] = $fecha->copy()->locale('es')->isoFormat('MMM YYYY');
            }
            $ventasMensuales['series'] = collect(self::PRODUCTOS)->map(fn (string $producto): array => ['name' => $producto, 'data' => array_fill(0, count($mesesPorCargar), 0)])->all();
            $resumen = array_fill_keys(['terminales', 'terminales_balance_positivo', 'terminales_balance_negativo', 'venta', 'premios', 'premios_sacados', 'ingreso_real', 'gastos', 'balance'], 0);
        }

        return view('gerencia.grafico-rentabilidad', compact('mes', 'periodo', 'mesDesde', 'mesHasta', 'anio', 'etiquetaPeriodo', 'empresa', 'ruta', 'terminal', 'alcance', 'consultar', 'mostrarResumen', 'productosSeleccionados', 'empresas', 'rutas', 'rutasDisponibles', 'agenciasResumen', 'resumen', 'ventasPorProducto', 'ventasMensuales', 'datosCargados', 'mesesPorCargar'));
    }

    /** @return array{0: Collection<int, string>|null, 1: Collection<int, string>|null} */
    private function filtrosProductos(Collection $productos): array
    {
        $filtroVentas = $productos->count() === count(self::PRODUCTOS) ? null : $productos;
        $tiposConPremios = $productos->filter(fn (string $producto): bool => $producto !== 'Recarga')->values();
        if ($tiposConPremios->count() === 2) {
            return [$filtroVentas, null];
        }

        $filtroPremios = $tiposConPremios->isEmpty()
            ? collect()
            : collect(self::GRUPOS_PREMIOS[$tiposConPremios->first()]);

        return [$filtroVentas, $filtroPremios];
    }

    /** @return array{0: Collection<int, array<string, mixed>>, 1: array<string, float|int>, 2: array<string, float>, 3: array{meses: list<string>, etiquetas: list<string>, series: list<array{name: string, data: list<float>}>}, 4: array<string, mixed>} */
    private function resumenTodas(Carbon $inicio, Carbon $fin, string $empresa, string $ruta, string $terminal, ?Collection $filtroVentas, ?Collection $filtroPremios): array
    {
        $cantidadMeses = ($fin->year - $inicio->year) * 12 + $fin->month - $inicio->month + 1;
        $diasPeriodo = (int) $inicio->diffInDays($fin->copy()->startOfDay()) + 1;
        $agencias = GraficoRentabilidad::query()->activas()
            ->whereNotNull('terminal')->where('terminal', '<>', '')
            ->orderBy('terminal')
            ->get(['terminal', 'sistema', 'nombre_agencia', 'agencia', 'ruta', 'empresa']);
        $terminales = $agencias->pluck('terminal')->map(fn (mixed $valor): string => trim((string) $valor))->unique()->values();
        $terminalesBet = $agencias->reject(fn (GraficoRentabilidad $agencia): bool => in_array(mb_strtoupper(trim((string) $agencia->sistema)), ['LOTONET', 'LOTENET'], true))->pluck('terminal')->unique()->values();
        $terminalesNet = $agencias->reject(fn (GraficoRentabilidad $agencia): bool => mb_strtoupper(trim((string) $agencia->sistema)) === 'LOTOBET')->pluck('terminal')->unique()->values();
        $ventasProductosBet = $this->ventasAgrupadasPorProducto('vt_usuarios_bet', $terminalesBet, $inicio, $fin, null);
        $ventasProductosNet = $this->ventasAgrupadasPorProducto('vt_usuarios_net', $terminalesNet, $inicio, $fin, null);
        $meses = collect();
        for ($fecha = $inicio->copy()->startOfMonth(); $fecha->lte($fin); $fecha->addMonth()) {
            $meses->push($fecha->copy());
        }
        $catalogoPremios = CatalogoJuego::query()->whereIn('tipo', ['Tradicional', 'No Tradicional'])->get(['producto_id', 'tipo']);
        $premiosSacadosBetDatos = $this->premiosAgrupadosPorProducto('premios_bet', $terminales, $inicio, $fin, 'agencia_id', $catalogoPremios);
        $premiosSacadosNetDatos = $this->premiosAgrupadosPorProducto('premios_net', $terminales, $inicio, $fin, 'agencia_id', $catalogoPremios);
        $premiosBetDatos = $this->premiosAgrupadosPorProducto('pagos_misma_empresa_bet', $terminales, $inicio, $fin, 'pagado_agencia_id', $catalogoPremios);
        $premiosOtraBetDatos = $this->premiosAgrupadosPorProducto('pagos_porotra_empresa_bet', $terminales, $inicio, $fin, 'agencia_id', $catalogoPremios);
        $premiosNetDatos = $this->premiosAgrupadosPorProducto('pagos_misma_empresa_net', $terminales, $inicio, $fin, 'pagado_agencia_id', $catalogoPremios);
        $premiosOtraNetDatos = $this->premiosAgrupadosPorProducto('pagos_porotra_empresa_net', $terminales, $inicio, $fin, 'agencia_id', $catalogoPremios);
        $gastosPorTerminal = $this->costosYGastosPorTerminal($terminales, $inicio, $fin);
        $r20 = (float) RentabilidadAgenciaR20::query()
            ->where('terminal', '*')
            ->orderByRaw('CASE WHEN mes = ? THEN 0 ELSE 1 END', ['GLOBAL'])
            ->orderByDesc('updated_at')
            ->value('monto') * $cantidadMeses;

        $datosCargados = $this->prepararDatosCargados($agencias, $ventasProductosBet, $ventasProductosNet, [
            'bet' => $premiosBetDatos->concat($premiosOtraBetDatos),
            'net' => $premiosNetDatos->concat($premiosOtraNetDatos),
        ], ['bet' => $premiosSacadosBetDatos, 'net' => $premiosSacadosNetDatos], $gastosPorTerminal, $r20, $meses);
        $agencias = $agencias->filter(fn (GraficoRentabilidad $agencia): bool => ($empresa === '' || trim((string) $agencia->empresa) === $empresa)
            && ($ruta === '' || trim((string) $agencia->ruta) === $ruta)
            && ($terminal === '' || (string) $agencia->terminal === $terminal));
        $terminalesSeleccionadas = $agencias->pluck('terminal')->all();
        $ventasProductosBet = $ventasProductosBet->whereIn('terminal', $terminalesSeleccionadas);
        $ventasProductosNet = $ventasProductosNet->whereIn('terminal', $terminalesSeleccionadas);
        if ($filtroVentas !== null) {
            $ventasProductosBet = $ventasProductosBet->whereIn('tipo', $filtroVentas->all());
            $ventasProductosNet = $ventasProductosNet->whereIn('tipo', $filtroVentas->all());
        }
        $ventasBet = $ventasProductosBet->groupBy('terminal')->map(fn (Collection $filas): float => (float) $filas->sum('total'));
        $ventasNet = $ventasProductosNet->groupBy('terminal')->map(fn (Collection $filas): float => (float) $filas->sum('total'));
        $ventasPorProducto = collect(self::PRODUCTOS)->mapWithKeys(fn (string $producto): array => [
            $producto => (float) $ventasProductosBet->where('tipo', $producto)->sum('total') + (float) $ventasProductosNet->where('tipo', $producto)->sum('total'),
        ])->all();
        $totalesMensuales = $ventasProductosBet->concat($ventasProductosNet)->groupBy('mes')
            ->map(fn (Collection $filas): Collection => $filas->groupBy('tipo')
                ->map(fn (Collection $ventas): float => (float) $ventas->sum('total')));
        $ventasMensuales = [
            'meses' => $meses->map(fn (Carbon $fecha): string => $fecha->format('Y-m'))->all(),
            'etiquetas' => $meses->map(fn (Carbon $fecha): string => $fecha->copy()->locale('es')->isoFormat('MMM YYYY'))->all(),
            'series' => collect(self::PRODUCTOS)->map(fn (string $producto): array => [
                'name' => $producto,
                'data' => $meses->map(fn (Carbon $fecha): float => (float) ($totalesMensuales->get($fecha->format('Y-m'))?->get($producto) ?? 0))->all(),
            ])->all(),
        ];
        $premiosSacadosBet = $this->totalizarPremios($premiosSacadosBetDatos, $filtroPremios);
        $premiosSacadosNet = $this->totalizarPremios($premiosSacadosNetDatos, $filtroPremios);
        $premiosBet = $this->totalizarPremios($premiosBetDatos, $filtroPremios);
        $premiosOtraBet = $this->totalizarPremios($premiosOtraBetDatos, $filtroPremios);
        $premiosNet = $this->totalizarPremios($premiosNetDatos, $filtroPremios);
        $premiosOtraNet = $this->totalizarPremios($premiosOtraNetDatos, $filtroPremios);

        $filas = $agencias->map(function (GraficoRentabilidad $agencia) use ($ventasBet, $ventasNet, $premiosSacadosBet, $premiosSacadosNet, $premiosBet, $premiosOtraBet, $premiosNet, $premiosOtraNet, $gastosPorTerminal, $r20, $diasPeriodo): array {
            $terminal = trim((string) $agencia->terminal);
            $sistema = mb_strtoupper(trim((string) $agencia->sistema));
            $venta = match ($sistema) {
                'LOTOBET' => (float) $ventasBet->get($terminal, 0),
                'LOTONET', 'LOTENET' => (float) $ventasNet->get($terminal, 0),
                default => (float) $ventasBet->get($terminal, 0) + (float) $ventasNet->get($terminal, 0),
            };
            $premios = match ($sistema) {
                'LOTOBET' => (float) $premiosBet->get($terminal, 0) + (float) $premiosOtraBet->get($terminal, 0),
                'LOTONET', 'LOTENET' => (float) $premiosNet->get($terminal, 0) + (float) $premiosOtraNet->get($terminal, 0),
                default => (float) $premiosBet->get($terminal, 0) + (float) $premiosOtraBet->get($terminal, 0) + (float) $premiosNet->get($terminal, 0) + (float) $premiosOtraNet->get($terminal, 0),
            };
            $premiosSacados = match ($sistema) {
                'LOTOBET' => (float) $premiosSacadosBet->get($terminal, 0),
                'LOTONET', 'LOTENET' => (float) $premiosSacadosNet->get($terminal, 0),
                default => (float) $premiosSacadosBet->get($terminal, 0) + (float) $premiosSacadosNet->get($terminal, 0),
            };
            $gastos = $gastosPorTerminal->get($terminal, ['costos' => 0.0, 'gastos' => 0.0, 'gastos_fijos' => 0.0]);
            $ingresoReal = $venta - $premios;
            $gastosFijos = $gastos['gastos_fijos'] + $r20;

            return [
                'terminal' => $terminal,
                'nombre' => trim((string) ($agencia->nombre_agencia ?: $agencia->agencia ?: $terminal)),
                'sistema' => $sistema,
                'ruta' => trim((string) $agencia->ruta),
                'venta' => $venta,
                'premios' => $premios,
                'premios_sacados' => $premiosSacados,
                'ingreso_real' => $ingresoReal,
                'costos' => $gastos['costos'],
                'gastos' => $gastos['gastos'] + $r20,
                'gastos_fijos' => $gastosFijos,
                'ventas_por_dia' => collect([60, 70, 80])->mapWithKeys(fn (int $porcentaje): array => [$porcentaje => ($gastos['gastos'] + $r20) / (1 - $porcentaje / 100) / $diasPeriodo])->all(),
                'meta_venta' => collect([60, 70, 80])->mapWithKeys(fn (int $porcentaje): array => [$porcentaje => ($gastos['gastos'] + $r20) / (1 - $porcentaje / 100)])->all(),
                'balance' => $ingresoReal - $gastos['costos'] - $gastos['gastos'] - $r20,
            ];
        })->values();

        return [$filas, [
            'terminales' => $filas->count(),
            'terminales_balance_positivo' => $filas->filter(fn (array $fila): bool => $fila['balance'] > 0)->count(),
            'terminales_balance_negativo' => $filas->filter(fn (array $fila): bool => $fila['balance'] < 0)->count(),
            'venta' => (float) $filas->sum('venta'),
            'premios' => (float) $filas->sum('premios'),
            'premios_sacados' => (float) $filas->sum('premios_sacados'),
            'ingreso_real' => (float) $filas->sum('ingreso_real'),
            'costos' => (float) $filas->sum('costos'),
            'gastos' => (float) $filas->sum('gastos'),
            'balance' => (float) $filas->sum('balance'),
        ], $ventasPorProducto, $ventasMensuales, $datosCargados];
    }

    /**
     * @param  Collection<int, string>  $terminales
     * @return Collection<int, object>
     */
    private function ventasAgrupadasPorProducto(string $tabla, Collection $terminales, Carbon $inicio, Carbon $fin, ?Collection $productos): Collection
    {
        if ($terminales->isEmpty() || ($productos !== null && $productos->isEmpty())) {
            return collect();
        }

        $mismoMes = $inicio->format('Y-m') === $fin->format('Y-m');
        $consulta = DB::table($tabla)
            ->whereIn('agencia_id', $terminales->all())
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->when($productos !== null, fn ($query) => $query->whereIn('tipo', $productos->all()))
            ->selectRaw('agencia_id AS terminal, tipo, SUM(COALESCE(monto, 0)) AS total')
            ->groupBy('agencia_id', 'tipo');

        if ($mismoMes) {
            $consulta->selectRaw('? AS mes', [$inicio->format('Y-m')]);
        } else {
            $consulta->selectRaw('SUBSTR(fecha, 1, 7) AS mes')->groupByRaw('SUBSTR(fecha, 1, 7)');
        }

        return $consulta->get();
    }

    /**
     * Aggregate by family membership instead of hundreds of individual game IDs.
     * A product belonging to both families is counted once when both are selected.
     *
     * @param  Collection<int, string>  $terminales
     * @return Collection<int, object>
     */
    private function premiosAgrupadosPorProducto(string $tabla, Collection $terminales, Carbon $inicio, Carbon $fin, string $columnaTerminal, Collection $catalogo): Collection
    {
        if ($terminales->isEmpty()) {
            return collect();
        }

        $expresiones = [];
        $parametros = [];
        foreach (['Tradicional' => 1, 'No Tradicional' => 2] as $tipo => $grupo) {
            $productos = $catalogo->where('tipo', $tipo)->pluck('producto_id')->unique()->values()->all();
            $expresiones[] = $productos === [] ? '0' : 'CASE WHEN producto_id IN ('.implode(',', array_fill(0, count($productos), '?')).') THEN '.$grupo.' ELSE 0 END';
            $parametros = array_merge($parametros, $productos);
        }

        $consulta = DB::table($tabla)
            ->whereIn($columnaTerminal, $terminales->all())
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->selectRaw($columnaTerminal.' AS terminal, SUM(COALESCE(monto, 0)) AS total')
            ->selectRaw('('.implode(' + ', $expresiones).') AS grupo_producto', $parametros)
            ->groupBy($columnaTerminal, 'grupo_producto');

        if ($inicio->format('Y-m') === $fin->format('Y-m')) {
            $consulta->selectRaw('? AS mes', [$inicio->format('Y-m')]);
        } else {
            $consulta->selectRaw('SUBSTR(fecha, 1, 7) AS mes')->groupByRaw('SUBSTR(fecha, 1, 7)');
        }

        return $consulta->get()->map(function (object $fila): object {
            $fila->producto_id = (string) $fila->grupo_producto;

            return $fila;
        });
    }

    /** @return Collection<string, float> */
    private function totalizarPremios(Collection $filas, ?Collection $productos): Collection
    {
        return $filas->when($productos !== null, fn (Collection $datos): Collection => $datos->whereIn('producto_id', $productos->all()))
            ->groupBy('terminal')->map(fn (Collection $datos): float => (float) $datos->sum('total'));
    }

    /**
     * @param  array{bet: Collection, net: Collection}  $premios
     * @param  array{bet: Collection, net: Collection}  $sacados
     * @return array<string, mixed>
     */
    private function prepararDatosCargados(Collection $agencias, Collection $ventasBet, Collection $ventasNet, array $premios, array $sacados, Collection $gastos, float $r20, Collection $meses): array
    {
        $ventas = ['bet' => $ventasBet->groupBy('terminal'), 'net' => $ventasNet->groupBy('terminal')];
        $premios = collect($premios)->map(fn (Collection $filas): Collection => $filas->groupBy('terminal'));
        $sacados = collect($sacados)->map(fn (Collection $filas): Collection => $filas->groupBy('terminal'));

        return [
            'meses' => $meses->map(fn (Carbon $fecha): string => $fecha->format('Y-m'))->all(),
            'etiquetas' => $meses->map(fn (Carbon $fecha): string => $fecha->copy()->locale('es')->isoFormat('MMM YYYY'))->all(),
            'productosPremios' => self::GRUPOS_PREMIOS,
            'agencias' => $agencias->map(function (GraficoRentabilidad $agencia) use ($ventas, $premios, $sacados, $gastos, $r20, $meses): array {
                $terminal = trim((string) $agencia->terminal);
                $origenes = match (mb_strtoupper(trim((string) $agencia->sistema))) {
                    'LOTOBET' => ['bet'],
                    'LOTONET', 'LOTENET' => ['net'],
                    default => ['bet', 'net'],
                };
                $ventasTerminal = collect($origenes)->flatMap(fn (string $origen): Collection => $ventas[$origen]->get($terminal, collect()));
                $premiosTerminal = collect($origenes)->flatMap(fn (string $origen): Collection => $premios[$origen]->get($terminal, collect()));
                $sacadosTerminal = collect($origenes)->flatMap(fn (string $origen): Collection => $sacados[$origen]->get($terminal, collect()));
                $gastosTerminal = $gastos->get($terminal, ['costos' => 0.0, 'gastos' => 0.0]);
                $serializarPremios = fn (Collection $filas): array => $filas->groupBy(fn (object $fila): string => $fila->mes.'|'.($fila->producto_id ?? ''))->map(fn (Collection $grupo): array => [
                    'producto_id' => (string) ($grupo->first()->producto_id ?? ''),
                    'mes' => $grupo->first()->mes,
                    'total' => (float) $grupo->sum('total'),
                ])->values()->all();

                return [
                    'terminal' => $terminal,
                    'empresa' => trim((string) $agencia->empresa),
                    'ruta' => trim((string) $agencia->ruta),
                    'costos' => (float) $gastosTerminal['costos'],
                    'gastos' => (float) $gastosTerminal['gastos'] + $r20,
                    'gastosMensuales' => $meses->map(fn (Carbon $fecha): array => [
                        'mes' => $fecha->format('Y-m'),
                        'costos' => (float) ($gastosTerminal['mensuales'][$fecha->format('Y-m')]['costos'] ?? 0),
                        'gastos' => (float) ($gastosTerminal['mensuales'][$fecha->format('Y-m')]['gastos'] ?? 0) + $r20 / $meses->count(),
                    ])->all(),
                    'ventas' => $ventasTerminal->map(fn (object $fila): array => ['mes' => $fila->mes, 'tipo' => $fila->tipo, 'total' => (float) $fila->total])->values()->all(),
                    'premios' => $serializarPremios($premiosTerminal),
                    'sacados' => $serializarPremios($sacadosTerminal),
                ];
            })->values()->all(),
        ];
    }

    /** @param Collection<int, string> $terminales */
    private function costosYGastosPorTerminal(Collection $terminales, Carbon $inicio, Carbon $fin): Collection
    {
        $clasificaciones = ClasificacionCuenta::clasificacionesActivas();
        if ($clasificaciones->isEmpty() || $terminales->isEmpty()) {
            return collect();
        }

        $cuentasFijas = $clasificaciones->filter(fn (mixed $clasificacion): bool => mb_strtoupper(trim((string) $clasificacion)) === 'FIJO')->keys()->all();
        $gastoFijo = $cuentasFijas === [] ? '0' : "CASE WHEN LOWER(TRIM(cuentas.tipo)) = 'gasto' AND movimientos.cuenta IN (".implode(',', array_fill(0, count($cuentasFijas), '?')).') THEN 1 ELSE 0 END';
        $movimientos = EntradaDiario::query()
            ->from('entradas_diario as movimientos')
            ->join('cuentas_contables as cuentas', 'cuentas.cuenta', '=', 'movimientos.cuenta')
            ->whereIn('movimientos.id_viejo', $terminales->all())
            ->whereBetween('movimientos.fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->whereIn('movimientos.cuenta', $clasificaciones->keys()->all())
            ->whereIn(DB::raw('LOWER(TRIM(cuentas.tipo))'), ['costo', 'gasto'])
            ->selectRaw('movimientos.id_viejo AS terminal')
            ->selectRaw($gastoFijo.' AS gasto_fijo', $cuentasFijas)
            ->selectRaw('LOWER(TRIM(cuentas.tipo)) AS tipo')
            ->selectRaw('SUM(COALESCE(movimientos.debito, 0) - COALESCE(movimientos.credito, 0)) AS importe')
            ->groupBy('gasto_fijo')
            ->groupByRaw('movimientos.id_viejo, LOWER(TRIM(cuentas.tipo))');

        if ($inicio->format('Y-m') === $fin->format('Y-m')) {
            $movimientos->selectRaw('? AS mes', [$inicio->format('Y-m')]);
        } else {
            $movimientos->selectRaw('SUBSTR(movimientos.fecha, 1, 7) AS mes')->groupByRaw('SUBSTR(movimientos.fecha, 1, 7)');
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            $indice = Schema::hasIndex('entradas_diario', 'entradas_diario_rent_grafico_fecha_index')
                ? 'entradas_diario_rent_grafico_fecha_index'
                : 'entradas_diario_fecha_index';
            $movimientos->forceIndex($indice);
        }

        return $movimientos
            ->get()
            ->groupBy('terminal')
            ->map(fn (Collection $filas): array => [
                'costos' => (float) $filas->where('tipo', 'costo')->sum('importe'),
                'gastos' => (float) $filas->where('tipo', 'gasto')->sum('importe'),
                'mensuales' => $filas->groupBy('mes')->map(fn (Collection $mes): array => [
                    'costos' => (float) $mes->where('tipo', 'costo')->sum('importe'),
                    'gastos' => (float) $mes->where('tipo', 'gasto')->sum('importe'),
                ])->all(),
                'gastos_fijos' => (float) $filas->where('gasto_fijo', 1)->sum('importe'),
            ]);
    }
}
