<?php

namespace App\Http\Controllers\Gerencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultarRentabilidadAgenciaV2Request;
use App\Models\CatalogoJuego;
use App\Models\ClasificacionCuenta;
use App\Models\EntradaDiario;
use App\Models\RentabilidadAgencia;
use App\Models\RentabilidadAgenciaR20;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RentabilidadAgenciaV2Controller extends Controller
{
    private const PRODUCTOS = ['Tradicional', 'No Tradicional', 'Recarga'];

    public function index(ConsultarRentabilidadAgenciaV2Request $request): View
    {
        $datos = $request->validated();
        $mes = (string) (($datos['mes'] ?? null) ?: now()->format('Y-m'));
        $terminal = trim((string) ($datos['terminal'] ?? ''));
        $empresa = trim((string) ($datos['empresa'] ?? ''));
        $alcance = (string) ($datos['alcance'] ?? ($terminal !== '' ? 'terminal' : 'todas'));
        $productosSeleccionados = collect($datos['productos'] ?? (! empty($datos['productos_filtrados']) ? [] : self::PRODUCTOS))->unique()->values();
        [$filtroVentas, $filtroPremios] = $this->filtrosProductos($productosSeleccionados);
        $consultar = $request->filled('mes');
        $empresas = RentabilidadAgencia::query()->activas()
            ->whereNotNull('empresa')
            ->whereRaw("TRIM(empresa) <> ''")
            ->selectRaw('TRIM(empresa) AS empresa')
            ->distinct()
            ->orderBy('empresa')
            ->pluck('empresa');
        $agencia = null;
        $dias = collect();
        $resumen = null;
        $metasDiarias = collect();
        $agenciasResumen = collect();

        if ($consultar && $alcance === 'todas') {
            [$agenciasResumen, $resumen] = $this->resumenTodas($mes, $empresa, $filtroVentas, $filtroPremios);
        } elseif ($consultar && $terminal !== '') {
            $agencia = RentabilidadAgencia::query()->activas()
                ->where('terminal', $terminal)
                ->when($empresa !== '', fn ($query) => $query->whereRaw('TRIM(empresa) = ?', [$empresa]))
                ->first();

            if ($agencia !== null) {
                $inicio = Carbon::createFromFormat('Y-m', $mes)->startOfMonth();
                $fin = $inicio->copy()->endOfMonth();
                $tablas = match (mb_strtoupper(trim((string) $agencia->sistema))) {
                    'LOTOBET' => [['vt_usuarios_bet', 'premios_bet', 'pagos_misma_empresa_bet', 'pagos_aotra_empresa_bet']],
                    'LOTONET', 'LOTENET' => [['vt_usuarios_net', 'premios_net', 'pagos_misma_empresa_net', 'pagos_aotra_empresa_net']],
                    default => [['vt_usuarios_bet', 'premios_bet', 'pagos_misma_empresa_bet', 'pagos_aotra_empresa_bet'], ['vt_usuarios_net', 'premios_net', 'pagos_misma_empresa_net', 'pagos_aotra_empresa_net']],
                };
                $ventas = collect();
                $premios = collect();
                $premiosSacados = collect();

                foreach ($tablas as [$tablaVentas, $tablaPremiosSacados, $tablaPagosMismaEmpresa, $tablaPagosOtraEmpresa]) {
                    $this->acumularPorDia($ventas, $tablaVentas, $terminal, $inicio, $fin, $filtroVentas, 'tipo');
                    $this->acumularPorDia($premiosSacados, $tablaPremiosSacados, $terminal, $inicio, $fin, $filtroPremios, 'producto_id');
                    $this->acumularPorDia($premios, $tablaPagosMismaEmpresa, $terminal, $inicio, $fin, $filtroPremios, 'producto_id');
                    $this->acumularPorDia($premios, $tablaPagosOtraEmpresa, $terminal, $inicio, $fin, $filtroPremios, 'producto_id');
                }

                $dias = collect();
                for ($fecha = $inicio->copy(); $fecha->lte($fin); $fecha->addDay()) {
                    $dia = $fecha->toDateString();
                    $venta = (float) $ventas->get($dia, 0);
                    $premio = (float) $premios->get($dia, 0);
                    $dias->push([
                        'fecha' => $dia,
                        'venta' => $venta,
                        'premios' => $premio,
                        'premios_sacados' => (float) $premiosSacados->get($dia, 0),
                        'ingreso_real' => $venta - $premio,
                    ]);
                }

                $ventaMes = (float) $dias->sum('venta');
                $premiosMes = (float) $dias->sum('premios');
                $gastos = $this->costosYGastos($terminal, $inicio, $fin);
                $r20 = (float) RentabilidadAgenciaR20::query()
                    ->where('terminal', '*')
                    ->orderByRaw('CASE WHEN mes = ? THEN 0 ELSE 1 END', ['GLOBAL'])
                    ->orderByDesc('updated_at')
                    ->value('monto');
                $resumen = [
                    'venta' => $ventaMes,
                    'premios' => $premiosMes,
                    'premios_sacados' => (float) $dias->sum('premios_sacados'),
                    'ingreso_real' => $ventaMes - $premiosMes,
                    'costos' => $gastos['costos'],
                    'gastos' => $gastos['gastos'] + $r20,
                    'gastos_fijos' => $gastos['gastos_fijos'] + $r20,
                    'r20' => $r20,
                    'balance' => $ventaMes - $premiosMes - $gastos['costos'] - $gastos['gastos'] - $r20,
                ];
                $metasDiarias = collect([60, 70, 80])->map(fn (int $porcentaje): array => [
                    'premios_porcentaje' => $porcentaje,
                    'meta_venta' => $resumen['gastos_fijos'] / (1 - $porcentaje / 100),
                    'meta_diaria' => $resumen['gastos_fijos'] / (1 - $porcentaje / 100) / $fin->day,
                ]);
            }
        }

        if ($request->ajax() && $alcance === 'terminal') {
            abort_if($agencia === null || $resumen === null, 404, 'No se encontró la terminal solicitada.');

            return view('gerencia.rentabilidad-agencia-v2-detalle', compact('agencia', 'terminal', 'mes', 'resumen', 'metasDiarias', 'dias'));
        }

        return view('gerencia.rentabilidad-agencia-v2', compact('mes', 'terminal', 'empresa', 'empresas', 'alcance', 'consultar', 'agencia', 'dias', 'resumen', 'metasDiarias', 'agenciasResumen', 'productosSeleccionados'));
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
            : CatalogoJuego::query()->whereIn('tipo', $tiposConPremios->all())
                ->pluck('producto_id')->map(fn (mixed $id): string => (string) $id)->unique()->values();

        return [$filtroVentas, $filtroPremios];
    }

    /** @return array{0: Collection<int, array<string, mixed>>, 1: array<string, float|int>} */
    private function resumenTodas(string $mes, string $empresa, ?Collection $filtroVentas, ?Collection $filtroPremios): array
    {
        $inicio = Carbon::createFromFormat('Y-m', $mes)->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();
        $agencias = RentabilidadAgencia::query()->activas()
            ->whereNotNull('terminal')->where('terminal', '<>', '')
            ->when($empresa !== '', fn ($query) => $query->whereRaw('TRIM(empresa) = ?', [$empresa]))
            ->orderBy('terminal')
            ->get(['terminal', 'sistema', 'nombre_agencia', 'agencia']);
        $terminales = $agencias->pluck('terminal')->map(fn (mixed $valor): string => trim((string) $valor))->unique()->values();
        $ventasBet = $this->totalesPorTerminal('vt_usuarios_bet', $terminales, $inicio, $fin, $filtroVentas, 'tipo');
        $ventasNet = $this->totalesPorTerminal('vt_usuarios_net', $terminales, $inicio, $fin, $filtroVentas, 'tipo');
        $premiosSacadosBet = $this->totalesPorTerminal('premios_bet', $terminales, $inicio, $fin, $filtroPremios, 'producto_id');
        $premiosSacadosNet = $this->totalesPorTerminal('premios_net', $terminales, $inicio, $fin, $filtroPremios, 'producto_id');
        $premiosBet = $this->totalesPorTerminal('pagos_misma_empresa_bet', $terminales, $inicio, $fin, $filtroPremios, 'producto_id');
        $premiosOtraBet = $this->totalesPorTerminal('pagos_aotra_empresa_bet', $terminales, $inicio, $fin, $filtroPremios, 'producto_id');
        $premiosNet = $this->totalesPorTerminal('pagos_misma_empresa_net', $terminales, $inicio, $fin, $filtroPremios, 'producto_id');
        $premiosOtraNet = $this->totalesPorTerminal('pagos_aotra_empresa_net', $terminales, $inicio, $fin, $filtroPremios, 'producto_id');
        $gastosPorTerminal = $this->costosYGastosPorTerminal($terminales, $inicio, $fin);
        $r20 = (float) RentabilidadAgenciaR20::query()
            ->where('terminal', '*')
            ->orderByRaw('CASE WHEN mes = ? THEN 0 ELSE 1 END', ['GLOBAL'])
            ->orderByDesc('updated_at')
            ->value('monto');

        $filas = $agencias->map(function (RentabilidadAgencia $agencia) use ($ventasBet, $ventasNet, $premiosSacadosBet, $premiosSacadosNet, $premiosBet, $premiosOtraBet, $premiosNet, $premiosOtraNet, $gastosPorTerminal, $r20, $fin): array {
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
                'venta' => $venta,
                'premios' => $premios,
                'premios_sacados' => $premiosSacados,
                'ingreso_real' => $ingresoReal,
                'costos' => $gastos['costos'],
                'gastos' => $gastos['gastos'] + $r20,
                'gastos_fijos' => $gastosFijos,
                'ventas_por_dia' => collect([60, 70, 80])->mapWithKeys(fn (int $porcentaje): array => [$porcentaje => $gastosFijos / (1 - $porcentaje / 100) / $fin->day])->all(),
                'meta_venta' => collect([60, 70, 80])->mapWithKeys(fn (int $porcentaje): array => [$porcentaje => $gastosFijos / (1 - $porcentaje / 100)])->all(),
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
        ]];
    }

    /** @param Collection<int, string> $terminales */
    private function totalesPorTerminal(string $tabla, Collection $terminales, Carbon $inicio, Carbon $fin, ?Collection $filtro, string $columna): Collection
    {
        if ($terminales->isEmpty() || ($filtro !== null && $filtro->isEmpty())) {
            return collect();
        }

        return DB::table($tabla)
            ->whereIn('agencia_id', $terminales->all())
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->when($filtro !== null, fn ($query) => $query->whereIn($columna, $filtro->all()))
            ->selectRaw('agencia_id AS terminal, SUM(COALESCE(monto, 0)) AS total')
            ->groupBy('agencia_id')
            ->pluck('total', 'terminal');
    }

    private function acumularPorDia(Collection $totales, string $tabla, string $terminal, Carbon $inicio, Carbon $fin, ?Collection $filtro, string $columna): void
    {
        if ($filtro !== null && $filtro->isEmpty()) {
            return;
        }

        $filas = DB::table($tabla)
            ->where('agencia_id', $terminal)
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->when($filtro !== null, fn ($query) => $query->whereIn($columna, $filtro->all()))
            ->select('fecha')
            ->selectRaw('SUM(COALESCE(monto, 0)) AS total')
            ->groupBy('fecha')
            ->get();

        foreach ($filas as $fila) {
            $dia = (string) $fila->fecha;
            $totales->put($dia, (float) $totales->get($dia, 0) + (float) $fila->total);
        }
    }

    /** @return array{costos: float, gastos: float, gastos_fijos: float} */
    private function costosYGastos(string $terminal, Carbon $inicio, Carbon $fin): array
    {
        return $this->costosYGastosPorTerminal(collect([$terminal]), $inicio, $fin)
            ->get($terminal, ['costos' => 0.0, 'gastos' => 0.0, 'gastos_fijos' => 0.0]);
    }

    /** @param Collection<int, string> $terminales */
    private function costosYGastosPorTerminal(Collection $terminales, Carbon $inicio, Carbon $fin): Collection
    {
        $clasificaciones = ClasificacionCuenta::clasificacionesActivas();
        if ($clasificaciones->isEmpty() || $terminales->isEmpty()) {
            return collect();
        }

        $movimientos = EntradaDiario::query()
            ->from('entradas_diario as movimientos')
            ->join('cuentas_contables as cuentas', 'cuentas.cuenta', '=', 'movimientos.cuenta')
            ->whereIn('movimientos.id_viejo', $terminales->all())
            ->whereBetween('movimientos.fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->whereIn('movimientos.cuenta', $clasificaciones->keys()->all())
            ->whereIn(DB::raw('LOWER(TRIM(cuentas.tipo))'), ['costo', 'gasto'])
            ->selectRaw('movimientos.id_viejo AS terminal')
            ->selectRaw('movimientos.cuenta AS cuenta')
            ->selectRaw('LOWER(TRIM(cuentas.tipo)) AS tipo')
            ->selectRaw('SUM(COALESCE(movimientos.debito, 0) - COALESCE(movimientos.credito, 0)) AS importe')
            ->groupByRaw('movimientos.id_viejo, movimientos.cuenta, LOWER(TRIM(cuentas.tipo))');
        if (DB::connection()->getDriverName() === 'mysql') {
            $movimientos->forceIndex($terminales->count() === 1 ? 'entradas_diario_id_viejo_index' : 'entradas_diario_cuenta_index');
        }

        return $movimientos
            ->get()
            ->groupBy('terminal')
            ->map(fn (Collection $filas): array => [
                'costos' => (float) $filas->where('tipo', 'costo')->sum('importe'),
                'gastos' => (float) $filas->where('tipo', 'gasto')->sum('importe'),
                'gastos_fijos' => (float) $filas->filter(fn (EntradaDiario $fila): bool => $fila->tipo === 'gasto' && mb_strtoupper(trim((string) $clasificaciones->get((string) $fila->cuenta))) === 'FIJO')->sum('importe'),
            ]);
    }
}
