<?php

namespace App\Http\Controllers\Gerencia;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Facades\Excel;

class VentaGerencialController extends Controller
{
    public function index(Request $request)
    {
        $fecha = trim((string) $request->query('fecha', now()->format('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = now()->format('Y-m-d');
        }

        $sistema = strtolower(trim((string) $request->query('sistema', 'lotobet')));
        if (!in_array($sistema, ['lotobet', 'lotonet'], true)) {
            $sistema = 'lotobet';
        }

        $resumenAgencias = $this->getResumenVentasAgenciaPorFecha($fecha, $sistema);

        return view('gerencia.venta-gerencial', [
            'fechaSeleccionada' => $fecha,
            'sistemaSeleccionado' => $sistema,
            'resumenAgencias' => $resumenAgencias,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $fecha = trim((string) $request->query('fecha', now()->format('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = now()->format('Y-m-d');
        }

        $sistema = strtolower(trim((string) $request->query('sistema', 'lotobet')));
        if (!in_array($sistema, ['lotobet', 'lotonet'], true)) {
            $sistema = 'lotobet';
        }

        $rows = collect($this->getResumenVentasAgenciaPorFecha($fecha, $sistema));
        $fileName = sprintf('venta_gerencial_%s_%s.xlsx', str_replace('-', '', $fecha), $sistema);

        return Excel::download(new class($rows) implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize {
            public function __construct(private $rows)
            {
            }

            public function collection()
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return [
                    'Agencia',
                    'Terminal',
                    'Ventas',
                    'Premios Sacados',
                    'Utilidad Bruta',
                ];
            }

            public function map($row): array
            {
                return [
                    (string) ($row['nombre_agencia'] ?? ''),
                    (string) ($row['terminal'] ?? ''),
                    (float) ($row['total_vendido'] ?? 0),
                    (float) ($row['premios_sacados'] ?? 0),
                    (float) ($row['utilidad_bruta'] ?? 0),
                ];
            }
        }, $fileName);
    }

    public function comparativa(Request $request)
    {
        $debeConsultar = $request->hasAny(['fecha', 'sistema', 'agencia']);

        $fecha = trim((string) $request->query('fecha', now()->format('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = now()->format('Y-m-d');
        }

        $sistema = strtolower(trim((string) $request->query('sistema', 'lotobet')));
        if (!in_array($sistema, ['lotobet', 'lotonet'], true)) {
            $sistema = 'lotobet';
        }

        $agencia = trim((string) $request->query('agencia', ''));
        if ($agencia === '') {
            $agencia = null;
        }

        $resumenComparativo = [];
        $agenciasDisponibles = [];
        $tendenciaSemanal = [
            'labels' => [],
            'series' => [],
        ];

        if ($debeConsultar) {
            $resumenComparativo = $this->getResumenVentasComparativaPorFecha($fecha, $sistema, $agencia);
            $agenciasDisponibles = $this->getAgenciasDisponiblesComparativa($fecha, $sistema);
            $tendenciaSemanal = $this->getTendenciaSemanalComparativa($fecha, $sistema, $agencia);
        }

        return view('gerencia.venta-comparativa', [
            'fechaSeleccionada' => $fecha,
            'sistemaSeleccionado' => $sistema,
            'agenciaSeleccionada' => $agencia,
            'agenciasDisponibles' => $agenciasDisponibles,
            'resumenComparativo' => $resumenComparativo,
            'tendenciaSemanal' => $tendenciaSemanal,
        ]);
    }

    public function exportExcelComparativa(Request $request)
    {
        $fecha = trim((string) $request->query('fecha', now()->format('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = now()->format('Y-m-d');
        }

        $sistema = strtolower(trim((string) $request->query('sistema', 'lotobet')));
        if (!in_array($sistema, ['lotobet', 'lotonet'], true)) {
            $sistema = 'lotobet';
        }

        $agencia = trim((string) $request->query('agencia', ''));
        if ($agencia === '') {
            $agencia = null;
        }

        $rows = collect($this->getResumenVentasComparativaPorFecha($fecha, $sistema, $agencia));
        $fileName = sprintf('venta_comparativa_%s_%s.xlsx', str_replace('-', '', $fecha), $sistema);

        return Excel::download(new class($rows) implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize {
            public function __construct(private $rows)
            {
            }

            public function collection()
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return [
                    'Agencia',
                    'Terminal',
                    'Ventas Hoy',
                    'Ventas Ayer',
                    'Ventas Ultimos 2 Dias',
                    'Ventas Ultimos 3 Dias',
                ];
            }

            public function map($row): array
            {
                return [
                    (string) ($row['nombre_agencia'] ?? ''),
                    (string) ($row['terminal'] ?? ''),
                    (float) ($row['ventas_hoy'] ?? 0),
                    (float) ($row['ventas_ayer'] ?? 0),
                    (float) ($row['ventas_ultimos_2_dias'] ?? 0),
                    (float) ($row['ventas_ultimos_3_dias'] ?? 0),
                ];
            }
        }, $fileName);
    }

    private function getResumenVentasAgenciaPorFecha(string $fecha, string $sistema): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = now()->format('Y-m-d');
        }

        $mapaTablas = $this->getMapaTablasPorSistema($sistema);

        $ventasRows = DB::table($mapaTablas['ventas'] . ' as v')
            ->leftJoin('agencias as a', DB::raw("TRIM(CAST(a.terminal AS CHAR))"), '=', DB::raw("TRIM(CAST(v.agencia_id AS CHAR))"))
            ->selectRaw("TRIM(CAST(v.agencia_id AS CHAR)) AS agencia")
            ->selectRaw('SUM(COALESCE(monto, 0)) AS total_vendido')
            ->whereNotNull('v.agencia_id')
            ->whereDate('fecha', $fecha)
            ->groupBy(DB::raw("TRIM(CAST(v.agencia_id AS CHAR))"))
            ->orderByDesc('total_vendido')
            ->get();

        $premiosRows = DB::table($mapaTablas['premios'])
            ->selectRaw("TRIM(CAST(agencia_id AS CHAR)) AS agencia")
            ->selectRaw('SUM(COALESCE(monto, 0)) AS premios_pagados')
            ->whereDate('fecha', $fecha)
            ->groupBy(DB::raw("TRIM(CAST(agencia_id AS CHAR))"))
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
            $premiosSacados = (float) ($premiosByAgencia[$agencia] ?? 0);
            $nombreAgencia = trim((string) ($nombresAgenciaByTerminal[$agencia] ?? ''));
            $utilidadBruta = $totalVendido - $premiosSacados;

            return [
                'agencia' => $agencia,
                'terminal' => $agencia,
                'nombre_agencia' => $nombreAgencia !== '' ? $nombreAgencia : $agencia,
                'total_vendido' => $totalVendido,
                'premios_sacados' => $premiosSacados,
                'utilidad_bruta' => $utilidadBruta,
            ];
        })->toArray();
    }

    private function getMapaTablasPorSistema(string $sistema): array
    {
        if ($sistema === 'lotonet') {
            return [
                'ventas' => 'vt_usuarios_net',
                'premios' => 'premios_net',
            ];
        }

        return [
            'ventas' => 'vt_usuarios_bet',
            'premios' => 'premios_bet',
        ];
    }

    private function getResumenVentasComparativaPorFecha(string $fecha, string $sistema, ?string $agenciaFiltro = null): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = now()->format('Y-m-d');
        }

        $fechaBase = Carbon::createFromFormat('Y-m-d', $fecha);
        $fechaHoy = $fechaBase->toDateString();
        $fechaAyer = $fechaBase->copy()->subDay()->toDateString();
        $fechaDosDias = $fechaBase->copy()->subDays(2)->toDateString();
        $fechaTresDias = $fechaBase->copy()->subDays(3)->toDateString();

        $mapaTablas = $this->getMapaTablasPorSistema($sistema);

        $rows = DB::table($mapaTablas['ventas'] . ' as v')
            ->leftJoin('agencias as a', DB::raw("TRIM(CAST(a.terminal AS CHAR))"), '=', DB::raw("TRIM(CAST(v.agencia_id AS CHAR))"))
            ->selectRaw("TRIM(CAST(v.agencia_id AS CHAR)) AS agencia")
            ->selectRaw("MAX(TRIM(COALESCE(a.nombre_agencia, ''))) AS nombre_agencia")
            ->selectRaw('SUM(CASE WHEN DATE(v.fecha) = ? THEN COALESCE(v.monto, 0) ELSE 0 END) AS ventas_hoy', [$fechaHoy])
            ->selectRaw('SUM(CASE WHEN DATE(v.fecha) = ? THEN COALESCE(v.monto, 0) ELSE 0 END) AS ventas_ayer', [$fechaAyer])
            ->selectRaw('SUM(CASE WHEN DATE(v.fecha) = ? THEN COALESCE(v.monto, 0) ELSE 0 END) AS ventas_hace_2_dias', [$fechaDosDias])
            ->selectRaw('SUM(CASE WHEN DATE(v.fecha) = ? THEN COALESCE(v.monto, 0) ELSE 0 END) AS ventas_hace_3_dias', [$fechaTresDias])
            ->selectRaw('SUM(CASE WHEN DATE(v.fecha) BETWEEN ? AND ? THEN COALESCE(v.monto, 0) ELSE 0 END) AS ventas_ultimos_2_dias', [$fechaAyer, $fechaHoy])
            ->selectRaw('SUM(CASE WHEN DATE(v.fecha) BETWEEN ? AND ? THEN COALESCE(v.monto, 0) ELSE 0 END) AS ventas_ultimos_3_dias', [$fechaDosDias, $fechaHoy])
            ->whereNotNull('v.agencia_id')
            ->when($agenciaFiltro !== null, function ($query) use ($agenciaFiltro) {
                $query->whereRaw("TRIM(CAST(v.agencia_id AS CHAR)) = ?", [$agenciaFiltro]);
            })
            ->whereBetween(DB::raw('DATE(v.fecha)'), [$fechaTresDias, $fechaHoy])
            ->groupBy(DB::raw("TRIM(CAST(v.agencia_id AS CHAR))"))
            ->orderByDesc('ventas_hoy')
            ->get();

        return $rows->map(function ($row) {
            $agencia = (string) ($row->agencia ?? 'SIN AGENCIA');
            $nombreAgencia = trim((string) ($row->nombre_agencia ?? ''));

            return [
                'agencia' => $agencia,
                'terminal' => $agencia,
                'nombre_agencia' => $nombreAgencia !== '' ? $nombreAgencia : $agencia,
                'ventas_hoy' => (float) ($row->ventas_hoy ?? 0),
                'ventas_ayer' => (float) ($row->ventas_ayer ?? 0),
                'ventas_hace_2_dias' => (float) ($row->ventas_hace_2_dias ?? 0),
                'ventas_hace_3_dias' => (float) ($row->ventas_hace_3_dias ?? 0),
                'ventas_ultimos_2_dias' => (float) ($row->ventas_ultimos_2_dias ?? 0),
                'ventas_ultimos_3_dias' => (float) ($row->ventas_ultimos_3_dias ?? 0),
            ];
        })->toArray();
    }

    private function getAgenciasDisponiblesComparativa(string $fecha, string $sistema): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = now()->format('Y-m-d');
        }

        $fechaInicio = Carbon::createFromFormat('Y-m-d', $fecha)->subDays(6)->toDateString();
        $mapaTablas = $this->getMapaTablasPorSistema($sistema);

        return DB::table($mapaTablas['ventas'] . ' as v')
            ->leftJoin('agencias as a', DB::raw("TRIM(CAST(a.terminal AS CHAR))"), '=', DB::raw("TRIM(CAST(v.agencia_id AS CHAR))"))
            ->selectRaw("TRIM(CAST(v.agencia_id AS CHAR)) AS agencia")
            ->selectRaw("MAX(TRIM(COALESCE(a.nombre_agencia, ''))) AS nombre_agencia")
            ->whereNotNull('v.agencia_id')
            ->whereBetween(DB::raw('DATE(v.fecha)'), [$fechaInicio, $fecha])
            ->groupBy(DB::raw("TRIM(CAST(v.agencia_id AS CHAR))"))
            ->orderBy('agencia')
            ->get()
            ->map(function ($row) {
                $agencia = (string) ($row->agencia ?? '');
                $nombre = trim((string) ($row->nombre_agencia ?? ''));

                return [
                    'agencia' => $agencia,
                    'nombre' => $nombre !== '' ? $nombre : $agencia,
                ];
            })
            ->values()
            ->toArray();
    }

    private function getTendenciaSemanalComparativa(string $fecha, string $sistema, ?string $agenciaFiltro = null): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = now()->format('Y-m-d');
        }

        $fechaInicio = Carbon::createFromFormat('Y-m-d', $fecha)->subDays(6)->toDateString();
        $mapaTablas = $this->getMapaTablasPorSistema($sistema);

        $rows = DB::table($mapaTablas['ventas'] . ' as v')
            ->selectRaw('DATE(v.fecha) AS fecha')
            ->selectRaw('SUM(COALESCE(v.monto, 0)) AS total_ventas')
            ->whereNotNull('v.agencia_id')
            ->when($agenciaFiltro !== null, function ($query) use ($agenciaFiltro) {
                $query->whereRaw("TRIM(CAST(v.agencia_id AS CHAR)) = ?", [$agenciaFiltro]);
            })
            ->whereBetween(DB::raw('DATE(v.fecha)'), [$fechaInicio, $fecha])
            ->groupBy(DB::raw('DATE(v.fecha)'))
            ->orderBy(DB::raw('DATE(v.fecha)'))
            ->get()
            ->keyBy('fecha');

        $labels = [];
        $series = [];

        $cursor = Carbon::createFromFormat('Y-m-d', $fechaInicio);
        $fin = Carbon::createFromFormat('Y-m-d', $fecha);

        while ($cursor->lessThanOrEqualTo($fin)) {
            $key = $cursor->toDateString();
            $labels[] = $cursor->format('d/m');
            $series[] = (float) (($rows[$key]->total_ventas ?? 0));
            $cursor->addDay();
        }

        return [
            'labels' => $labels,
            'series' => $series,
        ];
    }
}
