<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinanceDashboardController extends Controller
{
    public function indexLotobet()
    {
        return view('dashboard.lotobet.ventas');
    }

    public function indexLotonet()
    {
        return view('dashboard.lotonet.ventas');
    }

    public function data(Request $request)
    {
        $plataforma = $request->get('plataforma', 'bet');
        $tabla = $plataforma === 'net' ? 'vt_usuarios_net' : 'vt_usuarios_bet';
        $agencia_id = $request->get('agencia_id', null);
        $tipoExpression = "COALESCE(NULLIF(TRIM(c.tipo),''),'Sin tipo')";

        $fecha_inicio = $request->get('fecha_inicio', Carbon::today()->format('Y-m-d'));
        $fecha_fin = $request->get('fecha_fin', Carbon::today()->format('Y-m-d'));

        // Validar formato fecha
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) {
            $fecha_inicio = Carbon::today()->format('Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {
            $fecha_fin = Carbon::today()->format('Y-m-d');
        }

        $inicio = Carbon::createFromFormat('Y-m-d', $fecha_inicio)->startOfDay();
        $fin = Carbon::createFromFormat('Y-m-d', $fecha_fin)->endOfDay();

        // Query para obtener datos de agencias
        $agenciasQuery = DB::table($tabla . ' as v')
            ->selectRaw("v.agencia_id, COUNT(DISTINCT v.agencia_id) as agencia_count, SUM(v.monto) as total")
            ->whereBetween('v.fecha', [$inicio, $fin])
            ->groupBy('v.agencia_id')
            ->orderByRaw("SUM(v.monto) DESC");

        $agencias = $agenciasQuery->get();
        $totalAgencias = $agencias->count();

        // Query para datos agrupados por tipo
        $datosQuery = DB::table($tabla . ' as v')
            ->leftJoin('catalogo_juegos as c', 'v.producto_id', '=', 'c.producto_id')
            ->selectRaw("
                {$tipoExpression} as tipo,
                SUM(v.monto) as total,
                COUNT(*) as transacciones
            ")
            ->whereBetween('v.fecha', [$inicio, $fin])
            ->when($agencia_id, function ($q) use ($agencia_id) {
                $q->where('v.agencia_id', $agencia_id);
            })
            ->groupByRaw($tipoExpression);

        $tipoPrioridad = [
            'tradicional' => 0,
            'no tradicional' => 1,
        ];

        $datos = $datosQuery->get()->sort(function ($a, $b) use ($tipoPrioridad) {
            $ordenA = $tipoPrioridad[$a->tipo] ?? 2;
            $ordenB = $tipoPrioridad[$b->tipo] ?? 2;

            if ($ordenA === $ordenB) {
                return $b->total <=> $a->total;
            }

            return $ordenA <=> $ordenB;
        })->values();

        // Query para ventas por día separadas por tipo
        $ventasDiariasPorTipoQuery = DB::table($tabla . ' as v')
            ->leftJoin('catalogo_juegos as c', 'v.producto_id', '=', 'c.producto_id')
            ->selectRaw("DATE(v.fecha) as fecha, {$tipoExpression} as tipo, SUM(v.monto) as total")
            ->whereBetween('v.fecha', [$inicio, $fin]);

        if ($agencia_id) {
            $ventasDiariasPorTipoQuery->where('v.agencia_id', $agencia_id);
        }

        $ventasDiariasPorTipo = $ventasDiariasPorTipoQuery
            ->groupByRaw("DATE(v.fecha), {$tipoExpression}")
            ->orderBy('fecha')
            ->get();

        // Obtener fechas únicas para el gráfico
        $fechas = $ventasDiariasPorTipo->pluck('fecha')->unique()->sort()->values();

        // Ordenar tipos: tradicional, no tradicional, resto
        $tiposOrdenados = [];
        $tiposResto = [];

        foreach ($ventasDiariasPorTipo->pluck('tipo')->unique() as $tipo) {
            if ($tipo === 'tradicional') {
                array_unshift($tiposOrdenados, $tipo);
            } elseif ($tipo === 'no tradicional') {
                $tiposOrdenados[] = $tipo;
            } else {
                $tiposResto[] = $tipo;
            }
        }
        $tipos = collect(array_merge($tiposOrdenados, $tiposResto));

        // Calcular promedio diario del mes anterior por tipo
        $mesAnteriorInicio = $inicio->copy()->subMonth()->startOfMonth();
        $mesAnteriorFin = $inicio->copy()->subMonth()->endOfMonth();

        $ventasMesAnteriorPorTipoQuery = DB::table($tabla . ' as v')
            ->leftJoin('catalogo_juegos as c', 'v.producto_id', '=', 'c.producto_id')
            ->selectRaw("DATE(v.fecha) as fecha, {$tipoExpression} as tipo, SUM(v.monto) as total")
            ->whereBetween('v.fecha', [$mesAnteriorInicio, $mesAnteriorFin]);

        if ($agencia_id) {
            $ventasMesAnteriorPorTipoQuery->where('v.agencia_id', $agencia_id);
        }

        $ventasMesAnteriorPorTipo = $ventasMesAnteriorPorTipoQuery
            ->groupByRaw("DATE(v.fecha), {$tipoExpression}")
            ->get();

        // Calcular promedios por tipo del mes anterior
        $promediosPorTipoMesAnterior = [];
        foreach ($tipos as $tipo) {
            $ventasTipoMesAnterior = $ventasMesAnteriorPorTipo->where('tipo', $tipo);
            $promedioTipo = $ventasTipoMesAnterior->count() > 0
                ? $ventasTipoMesAnterior->sum('total') / $ventasTipoMesAnterior->count()
                : 0;
            $promediosPorTipoMesAnterior[$tipo] = $promedioTipo;
        }

        // Calcular promedio diario total del mes anterior
        $ventasMesAnteriorTotalQuery = DB::table($tabla)
            ->selectRaw("DATE(fecha) as fecha, SUM(monto) as total")
            ->whereBetween('fecha', [$mesAnteriorInicio, $mesAnteriorFin]);

        if ($agencia_id) {
            $ventasMesAnteriorTotalQuery->where('agencia_id', $agencia_id);
        }

        $ventasMesAnteriorTotal = $ventasMesAnteriorTotalQuery
            ->groupByRaw("DATE(fecha)")
            ->get();
        $promedioDiarioMesAnterior = $ventasMesAnteriorTotal->count() > 0
            ? $ventasMesAnteriorTotal->sum('total') / $ventasMesAnteriorTotal->count()
            : 0;

        // Construir datasets por tipo
        $datasetsPorTipo = [];
        $coloresDisponibes = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#C9CBCF', '#4BC0C0'];

        foreach ($tipos as $index => $tipo) {
            $datosDelTipo = $ventasDiariasPorTipo->where('tipo', $tipo);
            $valores = [];

            foreach ($fechas as $fecha) {
                $venta = $datosDelTipo->where('fecha', $fecha)->first();
                $valores[] = $venta ? $venta->total : 0;
            }

            $datasetsPorTipo[] = [
                'label' => $tipo,
                'data' => $valores,
                'backgroundColor' => $coloresDisponibes[$index % count($coloresDisponibes)],
            ];
        }

        // Calcular total general
        $totalGeneral = $datos->sum('total');
        $transaccionesTotal = $datos->sum('transacciones');
        $ticketPromedio = $transaccionesTotal > 0 ? $totalGeneral / $transaccionesTotal : 0;

        // KPIs
        $kpis = [
            'total' => $totalGeneral,
            'transacciones' => $transaccionesTotal,
            'ticket_promedio' => $ticketPromedio,
            'total_agencias' => $totalAgencias,
        ];

        // Chart data por tipo
        $chart = [
            'labels' => $datos->pluck('tipo')->toArray(),
            'values' => $datos->pluck('total')->toArray(),
        ];

        // Chart data por día
        $chartDiario = [
            'labels' => $fechas->toArray(),
            'datasets' => $datasetsPorTipo,
        ];

        // Chart data para gráfico de línea (totales por día)
        $ventasDiariasQuery = DB::table($tabla)
            ->selectRaw("DATE(fecha) as fecha, SUM(monto) as total")
            ->whereBetween('fecha', [$inicio, $fin]);

        if ($agencia_id) {
            $ventasDiariasQuery->where('agencia_id', $agencia_id);
        }

        $ventasDiarias = $ventasDiariasQuery
            ->groupByRaw("DATE(fecha)")
            ->orderBy('fecha')
            ->get();

        $chartDiarioLinea = [
            'labels' => $ventasDiarias->pluck('fecha')->toArray(),
            'values' => $ventasDiarias->pluck('total')->toArray(),
            'promedio_mes_anterior' => $promedioDiarioMesAnterior,
        ];

        // Agregar líneas de promedio del mes anterior por tipo al gráfico de barras diario
        foreach ($tipos as $index => $tipo) {
            $chartDiario['datasets'][] = [
                'label' => 'Promedio ' . $tipo . ' Mes Anterior: ' . number_format($promediosPorTipoMesAnterior[$tipo], 2),
                'data' => array_fill(0, count($fechas), $promediosPorTipoMesAnterior[$tipo]),
                'type' => 'line',
                'borderColor' => $coloresDisponibes[$index % count($coloresDisponibes)],
                'backgroundColor' => 'rgba(255, 215, 0, 0.1)',
                'borderWidth' => 2,
                'fill' => false,
                'pointRadius' => 0,
                'tension' => 0,
                'borderDash' => [5, 5], // Línea punteada para diferenciar
            ];
        }

        // Tabla data
        $tablaData = $datos->map(function ($item) use ($totalGeneral) {
            $porcentaje = $totalGeneral > 0 ? ($item->total / $totalGeneral) * 100 : 0;
            return [
                'tipo' => $item->tipo,
                'total' => $item->total,
                'transacciones' => $item->transacciones,
                'promedio' => $item->transacciones > 0 ? $item->total / $item->transacciones : 0,
                'porcentaje' => $porcentaje,
            ];
        })->toArray();

        return response()->json([
            'kpis' => $kpis,
            'chart' => $chart,
            'chart_diario' => $chartDiarioLinea,
            'chart_diario_tipos' => $chartDiario,
            'tabla' => $tablaData,
            'agencias' => $agencia_id ? [] : $agencias->map(function ($agencia) {
                return [
                    'agencia_id' => $agencia->agencia_id,
                    'total' => $agencia->total,
                ];
            })->toArray(),
        ]);
    }
}
