<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComercialController extends Controller
{
    public function index()
    {
        return view('comercial.index', [
            'kpis' => $this->getAcumuladosBet(),
        ]);
    }

    public function kpiVentas(Request $request)
    {
        $mes = $request->query('mes');

        if (!is_string($mes) || !preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $mes = now()->format('Y-m');
        }

        $metasDiarias = [
            'tradicional' => max(0, (float) $request->query('meta_tradicional', 0)),
            'no_tradicional' => max(0, (float) $request->query('meta_no_tradicional', 0)),
            'recargas' => max(0, (float) $request->query('meta_recargas', 0)),
        ];

        $kpis = $this->getAcumuladosBet($mes);
        $cumplimiento = $this->buildCumplimiento($kpis, $metasDiarias);
        $resumenAgencias = $this->getResumenVentasAgenciaMensual($mes);
        $agenciasPorTipo = $this->getCantidadAgenciasConVentaPorTipo($mes);

        return view('comercial.kpi-ventas', [
            'kpis' => $kpis,
            'metasDiarias' => $metasDiarias,
            'cumplimiento' => $cumplimiento,
            'resumenAgencias' => $resumenAgencias,
            'agenciasPorTipo' => $agenciasPorTipo,
            'mesSeleccionado' => $mes,
        ]);
    }

    private function getCantidadAgenciasConVentaPorTipo(string $mes): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $mes = now()->format('Y-m');
        }

        [$year, $month] = explode('-', $mes);

        $rows = DB::table('vt_usuarios_bet')
            ->selectRaw('LOWER(TRIM(tipo)) AS tipo_normalizado')
            ->selectRaw('COUNT(DISTINCT TRIM(CAST(agencia_id AS CHAR))) AS total_agencias')
            ->whereNotNull('agencia_id')
            ->whereYear('fecha', (int) $year)
            ->whereMonth('fecha', (int) $month)
            ->groupBy(DB::raw('LOWER(TRIM(tipo))'))
            ->get();

        $resultado = [
            'tradicional' => 0,
            'no_tradicional' => 0,
            'recargas' => 0,
        ];

        foreach ($rows as $row) {
            $tipo = (string) ($row->tipo_normalizado ?? '');
            $cantidad = (int) ($row->total_agencias ?? 0);

            if ($tipo === 'tradicional') {
                $resultado['tradicional'] += $cantidad;
                continue;
            }

            if ($tipo === 'no tradicional' || $tipo === 'no_tradicional') {
                $resultado['no_tradicional'] += $cantidad;
                continue;
            }

            if ($tipo === 'recarga' || $tipo === 'recargas') {
                $resultado['recargas'] += $cantidad;
            }
        }

        return $resultado;
    }

    public function kpiVentasV(Request $request)
    {
        $fechaInicio = $request->query('fecha_inicio');
        $fechaFin = $request->query('fecha_fin');

        if (!is_string($fechaInicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio)) {
            $fechaInicio = now()->startOfMonth()->format('Y-m-d');
        }

        if (!is_string($fechaFin) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)) {
            $fechaFin = now()->endOfMonth()->format('Y-m-d');
        }

        if ($fechaInicio > $fechaFin) {
            [$fechaInicio, $fechaFin] = [$fechaFin, $fechaInicio];
        }

        return view('comercial.kpi-ventas-v', [
            'kpis' => $this->getAcumuladosBetPorRango($fechaInicio, $fechaFin),
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
        ]);
    }

    private function getAcumuladosBetPorRango(string $fechaInicio, string $fechaFin): array
    {
        $acumulados = DB::table('vt_usuarios_bet')
            ->selectRaw("SUM(CASE WHEN LOWER(TRIM(tipo)) = 'tradicional' THEN monto ELSE 0 END) AS tradicional")
            ->selectRaw("SUM(CASE WHEN LOWER(TRIM(tipo)) IN ('no tradicional','no_tradicional') THEN monto ELSE 0 END) AS no_tradicional")
            ->selectRaw("SUM(CASE WHEN LOWER(TRIM(tipo)) IN ('recarga','recargas') THEN monto ELSE 0 END) AS recargas")
            ->whereDate('fecha', '>=', $fechaInicio)
            ->whereDate('fecha', '<=', $fechaFin)
            ->first();

        return [
            'tradicional' => (float) ($acumulados->tradicional ?? 0),
            'no_tradicional' => (float) ($acumulados->no_tradicional ?? 0),
            'recargas' => (float) ($acumulados->recargas ?? 0),
        ];
    }

    private function getAcumuladosBet(?string $mes = null, ?string $dia = null): array
    {
        $query = DB::table('vt_usuarios_bet')
            ->selectRaw("SUM(CASE WHEN LOWER(TRIM(tipo)) = 'tradicional' THEN monto ELSE 0 END) AS tradicional")
            ->selectRaw("SUM(CASE WHEN LOWER(TRIM(tipo)) IN ('no tradicional','no_tradicional') THEN monto ELSE 0 END) AS no_tradicional")
            ->selectRaw("SUM(CASE WHEN LOWER(TRIM(tipo)) IN ('recarga','recargas') THEN monto ELSE 0 END) AS recargas");

        if (is_string($dia) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia)) {
            $query->whereDate('fecha', $dia);
        } elseif (is_string($mes) && preg_match('/^\d{4}-\d{2}$/', $mes)) {
            [$year, $month] = explode('-', $mes);
            $query->whereYear('fecha', (int) $year)
                ->whereMonth('fecha', (int) $month);
        }

        $acumulados = $query->first();

        return [
            'tradicional' => (float) ($acumulados->tradicional ?? 0),
            'no_tradicional' => (float) ($acumulados->no_tradicional ?? 0),
            'recargas' => (float) ($acumulados->recargas ?? 0),
        ];
    }

    private function buildCumplimiento(array $kpis, array $metasDiarias): array
    {
        $productos = ['tradicional', 'no_tradicional', 'recargas'];
        $resultado = [];

        foreach ($productos as $producto) {
            $metaMensual = max(0, (float) ($metasDiarias[$producto] ?? 0)) * 30;
            $acumulado = max(0, (float) ($kpis[$producto] ?? 0));
            $faltante = max(0, $metaMensual - $acumulado);
            $pctFaltante = $metaMensual > 0 ? ($faltante / $metaMensual) * 100 : 0;

            $resultado[$producto] = [
                'meta_mensual' => $metaMensual,
                'faltante' => $faltante,
                'pct_faltante' => $pctFaltante,
            ];
        }

        return $resultado;
    }

    private function getResumenVentasAgenciaMensual(string $mes): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $mes = now()->format('Y-m');
        }

        [$year, $month] = explode('-', $mes);
        $fechaInicio = sprintf('%s-%s-01', $year, $month);
        $fechaFin = date('Y-m-t', strtotime($fechaInicio));

        $ventasRows = DB::table('vt_usuarios_bet')
            ->selectRaw("TRIM(CAST(agencia_id AS CHAR)) AS agencia")
            ->selectRaw('SUM(COALESCE(monto, 0)) AS total_vendido')
            ->whereNotNull('agencia_id')
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->groupBy(DB::raw("TRIM(CAST(agencia_id AS CHAR))"))
            ->orderByDesc('total_vendido')
            ->get();

        $premiosAotra = DB::table('pagos_aotra_empresa_bet')
            ->selectRaw("TRIM(CAST(agencia_id AS CHAR)) AS agencia")
            ->selectRaw('COALESCE(monto, 0) AS monto')
            ->whereBetween('fecha', [$fechaInicio, $fechaFin]);

        $premiosMisma = DB::table('pagos_misma_empresa_bet')
            ->selectRaw("TRIM(CAST(agencia_id AS CHAR)) AS agencia")
            ->selectRaw('COALESCE(monto, 0) AS monto')
            ->whereBetween('fecha', [$fechaInicio, $fechaFin]);

        $premiosRows = DB::query()
            ->fromSub($premiosAotra->unionAll($premiosMisma), 't')
            ->selectRaw('agencia, SUM(monto) AS premios_pagados')
            ->groupBy('agencia')
            ->get();

        $premiosByAgencia = $premiosRows
            ->mapWithKeys(function ($row) {
                $agencia = (string) ($row->agencia ?? '');
                return [$agencia => (float) ($row->premios_pagados ?? 0)];
            })
            ->toArray();

        $nombresAgenciaByTerminal = DB::table('agencias')
            ->selectRaw("TRIM(CAST(terminal AS CHAR)) AS terminal")
            ->selectRaw('TRIM(COALESCE(nombre_agencia, "")) AS nombre_agencia')
            ->whereNotNull('terminal')
            ->get()
            ->mapWithKeys(function ($row) {
                $terminal = (string) ($row->terminal ?? '');
                $nombre = (string) ($row->nombre_agencia ?? '');

                return [$terminal => $nombre];
            })
            ->toArray();

        return $ventasRows->map(function ($row) use ($premiosByAgencia, $nombresAgenciaByTerminal) {
            $agencia = (string) ($row->agencia ?? 'SIN AGENCIA');
            $totalVendido = (float) ($row->total_vendido ?? 0);
            $premiosPagados = (float) ($premiosByAgencia[$agencia] ?? 0);
            $nombreAgencia = trim((string) ($nombresAgenciaByTerminal[$agencia] ?? ''));

            return [
                'agencia' => $agencia,
                'terminal' => $agencia,
                'nombre_agencia' => $nombreAgencia !== '' ? $nombreAgencia : $agencia,
                'total_vendido' => $totalVendido,
                'premios_pagados' => $premiosPagados,
            ];
        })->toArray();
    }
}
