<?php

namespace App\Http\Controllers;

use App\Exports\FaltantesExport;
use App\Exports\VentasUsuarioExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReporteController extends Controller
{
    public function indexReportes()
    {
        $reportes = collect(config('reportes', []))
            ->filter(fn ($reporte) => (bool) ($reporte['activo'] ?? true))
            ->map(function ($reporte) {
                $reporte['url'] = url($reporte['url']);
                $reporte['tags'] = $reporte['tags'] ?? [];

                return $reporte;
            })
            ->sortBy('nombre')
            ->values();

        $categorias = $reportes
            ->pluck('categoria')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return view('reportes.index', compact('reportes', 'categorias'));
    }

    function ventasUsuarioBet(Request $request)
    {
        return view('reportes.ventas-usuario-bet');
    }

    public function compensacion()
    {
        return view('reportes.compensacion');
    }

    public function listCompensacion(Request $request)
    {
        $validated = $request->validate([
            'empresa' => 'required|in:todos,grupo_joselito,negosur',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $fechaInicio = $validated['fecha_inicio'];
        $fechaFin = $validated['fecha_fin'];
        $uniones = [];
        $bindings = [];

        $uniones[] = "
                SELECT
                    DATE(fecha) AS fecha,
                    TRIM(CAST(agencia_id AS CHAR)) AS agencia_id,
                    CAST(NULLIF(TRIM(pagado_consorcio_id), '') AS UNSIGNED) AS consorcio_id,
                    CAST(NULLIF(TRIM(producto_id), '') AS UNSIGNED) AS producto_id,
                    COALESCE(monto, 0) AS aotra_bet,
                    0 AS aotra_net,
                    0 AS porotra_bet,
                    0 AS porotra_net
                FROM pagos_aotra_empresa_bet
                WHERE fecha >= ? AND fecha < DATE_ADD(?, INTERVAL 1 DAY)
        ";
        $bindings[] = $fechaInicio;
        $bindings[] = $fechaFin;

        $uniones[] = "
                SELECT
                    DATE(fecha) AS fecha,
                    TRIM(CAST(agencia_id AS CHAR)) AS agencia_id,
                    CAST(NULLIF(TRIM(pagado_consorcio_id), '') AS UNSIGNED) AS consorcio_id,
                    CAST(NULLIF(TRIM(producto_id), '') AS UNSIGNED) AS producto_id,
                    0 AS aotra_bet,
                    0 AS aotra_net,
                    COALESCE(monto, 0) AS porotra_bet,
                    0 AS porotra_net
                FROM pagos_porotra_empresa_bet
                WHERE fecha >= ? AND fecha < DATE_ADD(?, INTERVAL 1 DAY)
        ";
        $bindings[] = $fechaInicio;
        $bindings[] = $fechaFin;

        $uniones[] = "
                SELECT
                    DATE(fecha) AS fecha,
                    TRIM(CAST(agencia_id AS CHAR)) AS agencia_id,
                    CAST(NULLIF(TRIM(pagado_a_consorcio_id), '') AS UNSIGNED) AS consorcio_id,
                    CAST(NULLIF(TRIM(producto_id), '') AS UNSIGNED) AS producto_id,
                    0 AS aotra_bet,
                    COALESCE(monto, 0) AS aotra_net,
                    0 AS porotra_bet,
                    0 AS porotra_net
                FROM pagos_aotra_empresa_net
                WHERE fecha >= ? AND fecha < DATE_ADD(?, INTERVAL 1 DAY)
        ";
        $bindings[] = $fechaInicio;
        $bindings[] = $fechaFin;

        $uniones[] = "
                SELECT
                    DATE(fecha) AS fecha,
                    TRIM(CAST(agencia_id AS CHAR)) AS agencia_id,
                    CAST(NULLIF(TRIM(pagado_consorcio_id), '') AS UNSIGNED) AS consorcio_id,
                    CAST(NULLIF(TRIM(producto_id), '') AS UNSIGNED) AS producto_id,
                    0 AS aotra_bet,
                    0 AS aotra_net,
                    0 AS porotra_bet,
                    COALESCE(monto, 0) AS porotra_net
                FROM pagos_porotra_empresa_net
                WHERE fecha >= ? AND fecha < DATE_ADD(?, INTERVAL 1 DAY)
        ";
        $bindings[] = $fechaInicio;
        $bindings[] = $fechaFin;

        $movimientosSql = implode(' UNION ALL ', $uniones);
        $catalogoTradicionalSql = "
            SELECT DISTINCT
                CAST(NULLIF(TRIM(producto_id), '') AS UNSIGNED) AS producto_id,
                TRIM(UPPER(tipo)) AS tipo
            FROM catalogo_juegos
        ";
        $agenciasEmpresaSql = "
            SELECT
                TRIM(CAST(terminal AS CHAR)) AS terminal,
                MAX(empresa) AS empresa,
                MAX(ruta) AS ruta
            FROM agencias
            WHERE terminal IS NOT NULL
              AND TRIM(CAST(terminal AS CHAR)) <> ''
            GROUP BY TRIM(CAST(terminal AS CHAR))
        ";
        $empresaWhereSql = '';
        $empresaBindings = [];

        if ($validated['empresa'] === 'grupo_joselito') {
            $empresaWhereSql = ' AND LOWER(COALESCE(a.empresa, "")) LIKE ?';
            $empresaBindings[] = '%joselito%';
        }

        if ($validated['empresa'] === 'negosur') {
            $empresaWhereSql = ' AND LOWER(COALESCE(a.empresa, "")) LIKE ?';
            $empresaBindings[] = '%negosur%';
        }

        $sql = "
            SELECT
                x.consorcios,
                x.aotra_bet,
                x.aotra_net,
                x.porotra_bet,
                x.porotra_net,
                x.total_general,
                x.orden
            FROM (
                SELECT
                    co.consorcios,
                    COALESCE(SUM(p.aotra_bet), 0) AS aotra_bet,
                    COALESCE(SUM(p.aotra_net), 0) AS aotra_net,
                    COALESCE(SUM(p.porotra_bet), 0) AS porotra_bet,
                    COALESCE(SUM(p.porotra_net), 0) AS porotra_net,
                    COALESCE(SUM(p.aotra_bet + p.aotra_net + p.porotra_bet + p.porotra_net), 0) AS total_general,
                    1 AS orden
                FROM (
                    {$movimientosSql}
                ) p
                INNER JOIN ({$catalogoTradicionalSql}) cj
                    ON p.producto_id = cj.producto_id
                    AND cj.tipo = 'TRADICIONAL'
                INNER JOIN consorcios co ON p.consorcio_id = co.id
                LEFT JOIN ({$agenciasEmpresaSql}) a
                    ON TRIM(CAST(a.terminal AS CHAR)) = TRIM(CAST(p.agencia_id AS CHAR))
                WHERE p.consorcio_id IS NOT NULL
                  AND p.consorcio_id <> 0
                  {$empresaWhereSql}
                GROUP BY co.consorcios

                UNION ALL

                SELECT
                    'TOTAL' AS consorcios,
                    COALESCE(SUM(p.aotra_bet), 0) AS aotra_bet,
                    COALESCE(SUM(p.aotra_net), 0) AS aotra_net,
                    COALESCE(SUM(p.porotra_bet), 0) AS porotra_bet,
                    COALESCE(SUM(p.porotra_net), 0) AS porotra_net,
                    COALESCE(SUM(p.aotra_bet + p.aotra_net + p.porotra_bet + p.porotra_net), 0) AS total_general,
                    2 AS orden
                FROM (
                    {$movimientosSql}
                ) p
                INNER JOIN ({$catalogoTradicionalSql}) cj
                    ON p.producto_id = cj.producto_id
                    AND cj.tipo = 'TRADICIONAL'
                LEFT JOIN ({$agenciasEmpresaSql}) a
                    ON TRIM(CAST(a.terminal AS CHAR)) = TRIM(CAST(p.agencia_id AS CHAR))
                WHERE 1 = 1
                  {$empresaWhereSql}
            ) x
            ORDER BY x.orden, x.total_general DESC, x.consorcios
        ";

        $data = DB::select($sql, array_merge($bindings, $empresaBindings, $bindings, $empresaBindings));
        $diarioSql = "
            SELECT
                p.fecha,
                COALESCE(SUM(p.aotra_bet + p.aotra_net), 0) AS pao,
                COALESCE(SUM(p.porotra_bet + p.porotra_net), 0) AS ppo
            FROM (
                {$movimientosSql}
            ) p
            INNER JOIN ({$catalogoTradicionalSql}) cj
                ON p.producto_id = cj.producto_id
                AND cj.tipo = 'TRADICIONAL'
            LEFT JOIN ({$agenciasEmpresaSql}) a
                ON TRIM(CAST(a.terminal AS CHAR)) = TRIM(CAST(p.agencia_id AS CHAR))
            WHERE 1 = 1
              {$empresaWhereSql}
            GROUP BY p.fecha
            ORDER BY p.fecha
        ";
        $diario = DB::select($diarioSql, array_merge($bindings, $empresaBindings));
        $diarioConsorcioSql = "
            SELECT
                co.consorcios,
                COALESCE(SUM(p.aotra_bet + p.aotra_net), 0) AS pao,
                COALESCE(SUM(p.porotra_bet + p.porotra_net), 0) AS ppo
            FROM (
                {$movimientosSql}
            ) p
            INNER JOIN ({$catalogoTradicionalSql}) cj
                ON p.producto_id = cj.producto_id
                AND cj.tipo = 'TRADICIONAL'
            INNER JOIN consorcios co ON p.consorcio_id = co.id
            LEFT JOIN ({$agenciasEmpresaSql}) a
                ON TRIM(CAST(a.terminal AS CHAR)) = TRIM(CAST(p.agencia_id AS CHAR))
            WHERE p.consorcio_id IS NOT NULL
              AND p.consorcio_id <> 0
              {$empresaWhereSql}
            GROUP BY co.consorcios
            ORDER BY (COALESCE(SUM(p.aotra_bet + p.aotra_net), 0) + COALESCE(SUM(p.porotra_bet + p.porotra_net), 0)) DESC, co.consorcios
        ";
        $diarioConsorcio = DB::select($diarioConsorcioSql, array_merge($bindings, $empresaBindings));
        $topRutasSql = "
            SELECT
                COALESCE(NULLIF(TRIM(a.ruta), ''), 'Sin ruta') AS ruta,
                COALESCE(SUM(p.aotra_bet + p.aotra_net), 0) AS pao,
                COALESCE(SUM(p.porotra_bet + p.porotra_net), 0) AS ppo
            FROM (
                {$movimientosSql}
            ) p
            INNER JOIN ({$catalogoTradicionalSql}) cj
                ON p.producto_id = cj.producto_id
                AND cj.tipo = 'TRADICIONAL'
            LEFT JOIN ({$agenciasEmpresaSql}) a
                ON TRIM(CAST(a.terminal AS CHAR)) = TRIM(CAST(p.agencia_id AS CHAR))
            WHERE 1 = 1
              {$empresaWhereSql}
            GROUP BY COALESCE(NULLIF(TRIM(a.ruta), ''), 'Sin ruta')
            ORDER BY (COALESCE(SUM(p.aotra_bet + p.aotra_net), 0) + COALESCE(SUM(p.porotra_bet + p.porotra_net), 0)) DESC,
                     COALESCE(NULLIF(TRIM(a.ruta), ''), 'Sin ruta')
            LIMIT 10
        ";
        $topRutas = DB::select($topRutasSql, array_merge($bindings, $empresaBindings));
        $totalRow = collect($data)->firstWhere('consorcios', 'TOTAL');

        $totalAotraBet = (float) ($totalRow->aotra_bet ?? 0);
        $totalAotraNet = (float) ($totalRow->aotra_net ?? 0);
        $totalPorotraBet = (float) ($totalRow->porotra_bet ?? 0);
        $totalPorotraNet = (float) ($totalRow->porotra_net ?? 0);
        $totalGeneral = $totalAotraBet + $totalAotraNet + $totalPorotraBet + $totalPorotraNet;
        return response()->json([
            'resumen' => [
                'empresa' => match ($validated['empresa']) {
                    'grupo_joselito' => 'Grupo Joselito',
                    'negosur' => 'Negosur',
                    default => 'Todas',
                },
                'aotra_bet' => round($totalAotraBet, 2),
                'aotra_net' => round($totalAotraNet, 2),
                'porotra_bet' => round($totalPorotraBet, 2),
                'porotra_net' => round($totalPorotraNet, 2),
                'total_lotobet' => round($totalAotraBet + $totalPorotraBet, 2),
                'total_lotonet' => round($totalAotraNet + $totalPorotraNet, 2),
                'total_general' => round($totalGeneral, 2),
                'registros' => count($data),
            ],
            'visual' => [
                'diario' => collect($diario)->map(fn ($row) => [
                    'fecha' => (string) $row->fecha,
                    'pao' => round((float) $row->pao, 2),
                    'ppo' => round((float) $row->ppo, 2),
                ])->values(),
                'diario_consorcio' => collect($diarioConsorcio)->map(fn ($row) => [
                    'consorcios' => (string) $row->consorcios,
                    'pao' => round((float) $row->pao, 2),
                    'ppo' => round((float) $row->ppo, 2),
                ])->values(),
                'top_rutas' => collect($topRutas)->map(fn ($row) => [
                    'ruta' => (string) $row->ruta,
                    'pao' => round((float) $row->pao, 2),
                    'ppo' => round((float) $row->ppo, 2),
                ])->values(),
            ],
            'data' => $data,
        ]);
    }

    public function pdfCompensacionGrupoJoselito(Request $request)
    {
        $validated = $request->validate([
            'empresa' => 'nullable|in:todos,grupo_joselito,negosur',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $empresa = $validated['empresa'] ?? 'grupo_joselito';

        $consulta = Request::create('/reportes-compensacion/list', 'GET', [
            'empresa' => $empresa,
            'fecha_inicio' => $validated['fecha_inicio'],
            'fecha_fin' => $validated['fecha_fin'],
        ]);

        $payload = $this->listCompensacion($consulta)->getData(true);
        $fileName = 'compensacion_' . $empresa . '_' .
            str_replace('-', '', $validated['fecha_inicio']) . '_' .
            str_replace('-', '', $validated['fecha_fin']) . '.pdf';

        $pdf = Pdf::loadView('reportes.compensacion-grupo-joselito-pdf', [
            'fechaInicio' => $validated['fecha_inicio'],
            'fechaFin' => $validated['fecha_fin'],
            'empresa' => $payload['resumen']['empresa'] ?? 'Grupo Joselito',
            'resumen' => $payload['resumen'] ?? [],
            'tradicional' => $payload['data'] ?? [],
            'diario' => $payload['visual']['diario'] ?? [],
            'consorcios' => $payload['visual']['diario_consorcio'] ?? [],
            'rutas' => $payload['visual']['top_rutas'] ?? [],
        ])->setPaper('letter', 'landscape');

        return $pdf->download($fileName);
    }

    public function listVentasUsuarioBet(Request $request)
    {
        header('Content-Type: application/json');

        $validated = $request->validate([
            'empresa' => 'nullable|in:todos,grupo_joselito,negosur',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
        ]);

        $page = $request->input('page', 1);
        $empresa = $validated['empresa'] ?? 'todos';
        $fechaInicio = $validated['fecha_inicio'] ?? null;
        $fechaFin = $validated['fecha_fin'] ?? null;

        $query = DB::table('vt_usuarios_bet as v')
            ->leftJoin('empleados as e', DB::raw("REPLACE(REPLACE(COALESCE(v.cedula, ''), '-', ''), ' ', '')"), '=', DB::raw("REPLACE(REPLACE(COALESCE(e.cedula, ''), '-', ''), ' ', '')"))
            ->selectRaw("
                REPLACE(REPLACE(COALESCE(v.cedula, ''), '-', ''), ' ', '') AS cedula,
                COALESCE(
                    NULLIF(TRIM(CONCAT(COALESCE(MAX(e.nombres), ''), ' ', COALESCE(MAX(e.apellidos), ''))), ''),
                    'Actualizar en la maestra de empleado'
                ) AS nombre,
                ROUND(SUM(CASE WHEN LOWER(TRIM(v.tipo)) = 'tradicional' THEN COALESCE(v.monto, 0) ELSE 0 END), 2) AS tradicional,
                ROUND(SUM(CASE WHEN LOWER(TRIM(v.tipo)) IN ('no tradicional', 'no_tradicional') THEN COALESCE(v.monto, 0) ELSE 0 END), 2) AS no_tradicional,
                ROUND(SUM(CASE WHEN LOWER(TRIM(v.tipo)) IN ('recarga', 'recargas') THEN COALESCE(v.monto, 0) ELSE 0 END), 2) AS recargas,
                ROUND(SUM(COALESCE(v.monto, 0)), 2) AS total
            ");

        if ($fechaInicio && $fechaFin) {
            $query->whereDate('v.fecha', '>=', $fechaInicio)
                ->whereDate('v.fecha', '<=', $fechaFin);
        }

        if ($empresa !== 'todos') {
            $query->leftJoin('agencias as a', DB::raw("TRIM(CAST(v.agencia_id AS CHAR))"), '=', DB::raw("TRIM(CAST(a.terminal AS CHAR))"));

            if ($empresa === 'grupo_joselito') {
                $query->whereRaw('LOWER(COALESCE(a.empresa, "")) LIKE ?', ['%joselito%']);
            }

            if ($empresa === 'negosur') {
                $query->whereRaw('LOWER(COALESCE(a.empresa, "")) LIKE ?', ['%negosur%']);
            }
        }

        $registros = $query
            ->whereRaw("NULLIF(REPLACE(REPLACE(COALESCE(v.cedula, ''), '-', ''), ' ', ''), '') IS NOT NULL")
            ->groupByRaw("REPLACE(REPLACE(COALESCE(v.cedula, ''), '-', ''), ' ', '')")
            ->orderByRaw("REPLACE(REPLACE(COALESCE(v.cedula, ''), '-', ''), ' ', '') DESC")
            ->paginate(50, ['*'], 'page', $page);

        return $registros->toJson();
    }

    public function excelVentasUsuarioBet(Request $request)
    {
        ini_set('memory_limit', '2G'); // Aumentar el límite de memoria
        ini_set('max_execution_time', 300); // Aumentar el tiempo máximo de entrada a 5 min

        $tipo = $request->input('tipo');
        $fecha = $request->input('fecha');
        $mes = $request->input('mes');
        $empresa = $request->input('empresa', 'todos');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        $fileName = 'ventas_usuarioio_bet_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new VentasUsuarioExport($tipo, $fecha, $mes, $empresa, $fechaInicio, $fechaFin), $fileName);
    }

    function pdfVentasUsuarioBet(Request $request)
    {
        ini_set('memory_limit', '1G'); // Aumentar el límite de memoria a 512MB

        $mes = $request->input('mes');

        $query = DB::table('vt_usuarios_bet')
            ->select('consorcio_id', 'agencia_id', 'cedula', 'tipo')
            ->whereNotIn('cedula', function ($sub) {
                $sub->select('cedula')->from('empleados')->whereNotNull('cedula');
            });

        if ($mes) {
            [$year, $month] = explode('-', $mes);
            $query->whereYear('fecha', $year)->whereMonth('fecha', $month);
        }

        $registros = $query
            ->groupBy('consorcio_id', 'agencia_id', 'cedula', 'tipo')
            ->orderBy('cedula', 'desc')
            ->get();        

        // 🔹 Generar PDF usando una vista
        $pdf = Pdf::loadView('reportes.ventas-usuario-bet-pdf', compact('registros'))
            ->setPaper('A4', 'portrait');

        // 🔹 Descargar el archivo
        return $pdf->download('reporte_ventas_usuario.pdf');
    }

    // ========== INFORME FALTANTES BET ==========
    function faltantesBet(Request $request)
    {
        return view('reportes.faltantes-bet');
    }

    private function getFaltantesConfig(?string $tipo = 'all'): array
    {
        $tipo = strtolower($tipo ?? 'all');

        if ($tipo === 'all') {
            return [
                'tipo' => 'all',
                'tabla' => 'faltantes',
                'nombre' => 'Todos los sistemas',
            ];
        }

        if ($tipo === 'net') {
            return [
                'tipo' => 'net',
                'tabla' => 'faltantes_net',
                'nombre' => 'Lotonet',
            ];
        }

        return [
            'tipo' => 'bet',
            'tabla' => 'faltantes_bet',
            'nombre' => 'Lotobet',
        ];
    }

    private function faltantesBaseQuery(string $tipo)
    {
        if ($tipo !== 'all') {
            return DB::table($this->getFaltantesConfig($tipo)['tabla']);
        }

        $faltantesBet = DB::table('faltantes_bet')
            ->select('agencia_id', 'identificacion', 'faltante_id', 'monto', 'fecha');

        $faltantesNet = DB::table('faltantes_net')
            ->select('agencia_id', 'identificacion', 'faltante_id', 'monto', 'fecha');

        return DB::query()->fromSub($faltantesBet->unionAll($faltantesNet), 'faltantes');
    }

    public function listFaltantesBet(Request $request)
    {
        header('Content-Type: application/json');

        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');
        $empresa = $request->input('empresa', 'todos');
        $config = $this->getFaltantesConfig($request->input('tipo'));
        $tabla = $config['tabla'];

        $query = $this->faltantesBaseQuery($config['tipo'])
            ->leftJoin('empleados', $tabla . '.identificacion', '=', 'empleados.cedula')
            ->leftJoin('agencias', DB::raw("TRIM(CAST({$tabla}.agencia_id AS CHAR))"), '=', DB::raw("TRIM(CAST(agencias.terminal AS CHAR))"))
            ->select(
                $tabla . '.agencia_id',
                $tabla . '.identificacion',
                DB::raw("COALESCE(NULLIF(TRIM(agencias.empresa), ''), 'Sin empresa') as empresa"),
                DB::raw("CONCAT(COALESCE(empleados.nombres, ''), ' ', COALESCE(empleados.apellidos, '')) as nombre_empleado"),
                DB::raw("COUNT($tabla.faltante_id) as cantidad_faltantes"),
                DB::raw("SUM($tabla.monto) as total_monto"),
                DB::raw("GROUP_CONCAT(DISTINCT DATE_FORMAT($tabla.fecha, '%d/%m/%Y') ORDER BY $tabla.fecha SEPARATOR ', ') as fechas_faltantes"),
                DB::raw("GROUP_CONCAT(CONCAT(DATE_FORMAT($tabla.fecha, '%d/%m/%Y'), '|', COALESCE($tabla.monto, 0)) ORDER BY $tabla.fecha SEPARATOR ';;') as detalles_faltantes")
            )
            ->whereNotNull($tabla . '.identificacion')
            ->where($tabla . '.identificacion', '!=', '');

        if ($fechaInicio && $fechaFin) {
            $query->whereBetween($tabla . '.fecha', [$fechaInicio, $fechaFin]);
        }

        if ($empresa === 'grupo_joselito') {
            $query->whereRaw('LOWER(COALESCE(agencias.empresa, "")) LIKE ?', ['%joselito%']);
        } elseif ($empresa === 'negosur') {
            $query->whereRaw('LOWER(COALESCE(agencias.empresa, "")) LIKE ?', ['%negosur%']);
        }

        $registros = $query
            ->groupBy($tabla . '.agencia_id', $tabla . '.identificacion', 'agencias.empresa', 'empleados.nombres', 'empleados.apellidos')
            ->orderBy('total_monto', 'desc')
            ->paginate(10);

        return $registros->toJson();
    }

    public function excelFaltantesBet(Request $request)
    {
        ini_set('memory_limit', '2G');
        ini_set('max_execution_time', 300);

        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');
        $empresa = $request->input('empresa', 'todos');
        $config = $this->getFaltantesConfig($request->input('tipo'));
        $tabla = $config['tabla'];

        $query = $this->faltantesBaseQuery($config['tipo'])
            ->leftJoin('empleados', $tabla . '.identificacion', '=', 'empleados.cedula')
            ->leftJoin('agencias', DB::raw("TRIM(CAST({$tabla}.agencia_id AS CHAR))"), '=', DB::raw("TRIM(CAST(agencias.terminal AS CHAR))"))
            ->select(
                $tabla . '.identificacion',
                DB::raw("COALESCE(NULLIF(TRIM(agencias.empresa), ''), 'Sin empresa') as empresa"),
                DB::raw("CONCAT(COALESCE(empleados.nombres, ''), ' ', COALESCE(empleados.apellidos, '')) as nombre_empleado"),
                DB::raw("COUNT($tabla.faltante_id) as cantidad_faltantes"),
                DB::raw("SUM($tabla.monto) as total_monto"),
                DB::raw("GROUP_CONCAT(DISTINCT DATE_FORMAT($tabla.fecha, '%d/%m/%Y') ORDER BY $tabla.fecha SEPARATOR ', ') as fechas_faltantes")
            )
            ->whereNotNull($tabla . '.identificacion')
            ->where($tabla . '.identificacion', '!=', '');

        if ($fechaInicio && $fechaFin) {
            $query->whereBetween($tabla . '.fecha', [$fechaInicio, $fechaFin]);
        }

        if ($empresa === 'grupo_joselito') {
            $query->whereRaw('LOWER(COALESCE(agencias.empresa, "")) LIKE ?', ['%joselito%']);
        } elseif ($empresa === 'negosur') {
            $query->whereRaw('LOWER(COALESCE(agencias.empresa, "")) LIKE ?', ['%negosur%']);
        }

        $registros = $query
            ->groupBy($tabla . '.identificacion', 'agencias.empresa', 'empleados.nombres', 'empleados.apellidos')
            ->orderBy('total_monto', 'desc')
            ->get();

        $fileName = 'faltantes_' . $config['tipo'] . '_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new \App\Exports\FaltantesBetExport($registros), $fileName);
    }

    public function pdfFaltantesBet(Request $request)
    {
        ini_set('memory_limit', '1G');

        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');
        $empresa = $request->input('empresa', 'todos');
        $config = $this->getFaltantesConfig($request->input('tipo'));
        $tabla = $config['tabla'];

        $query = $this->faltantesBaseQuery($config['tipo'])
            ->leftJoin('empleados', $tabla . '.identificacion', '=', 'empleados.cedula')
            ->leftJoin('agencias', DB::raw("TRIM(CAST({$tabla}.agencia_id AS CHAR))"), '=', DB::raw("TRIM(CAST(agencias.terminal AS CHAR))"))
            ->select(
                $tabla . '.identificacion',
                DB::raw("COALESCE(NULLIF(TRIM(agencias.empresa), ''), 'Sin empresa') as empresa"),
                DB::raw("CONCAT(COALESCE(empleados.nombres, ''), ' ', COALESCE(empleados.apellidos, '')) as nombre_empleado"),
                DB::raw("COUNT($tabla.faltante_id) as cantidad_faltantes"),
                DB::raw("SUM($tabla.monto) as total_monto"),
                DB::raw("GROUP_CONCAT(DISTINCT DATE_FORMAT($tabla.fecha, '%d/%m/%Y') ORDER BY $tabla.fecha SEPARATOR ', ') as fechas_faltantes")
            )
            ->whereNotNull($tabla . '.identificacion')
            ->where($tabla . '.identificacion', '!=', '');

        if ($fechaInicio && $fechaFin) {
            $query->whereBetween($tabla . '.fecha', [$fechaInicio, $fechaFin]);
        }

        if ($empresa === 'grupo_joselito') {
            $query->whereRaw('LOWER(COALESCE(agencias.empresa, "")) LIKE ?', ['%joselito%']);
        } elseif ($empresa === 'negosur') {
            $query->whereRaw('LOWER(COALESCE(agencias.empresa, "")) LIKE ?', ['%negosur%']);
        }

        $registros = $query
            ->groupBy($tabla . '.identificacion', 'agencias.empresa', 'empleados.nombres', 'empleados.apellidos')
            ->orderBy('total_monto', 'desc')
            ->get();

        $sistema = $config['nombre'];

        $pdf = Pdf::loadView('reportes.faltantes-bet-pdf', compact('registros', 'sistema'))
            ->setPaper('A4', 'portrait');

        return $pdf->download('reporte_faltantes_' . $config['tipo'] . '.pdf');
    }

    public function cuadreVentas(Request $request)
    {
        return view('reportes.cuadre-ventas');
    }

    public function listCuadreVentas(Request $request)
    {
        $sistema = $request->input('sistema', 'Lotobet');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        if (!$fechaInicio || !$fechaFin) {
            return response()->json([
                'resultados' => [],
                'agencias_sin_cedula' => [],
            ]);
        }

        // Determinar la tabla según el sistema
        $tabla = $sistema === 'Lotobet' ? 'vt_usuarios_bet' : 'vt_usuarios_net';

        // Consulta principal por día
        $resultados = DB::select("
            SELECT
                r.Fecha,
                FORMAT(r.Tradicional, 2, 'en_US')     AS Tradicional,
                FORMAT(r.No_Tradicional, 2, 'en_US')  AS No_Tradicional,
                FORMAT(r.Recarga, 2, 'en_US')          AS Recarga,
                FORMAT(r.Paquetico, 2, 'en_US')        AS Paquetico,
                FORMAT(
                    r.Tradicional + r.No_Tradicional + r.Recarga + r.Paquetico,
                    2, 'en_US'
                ) AS Total_Dia
            FROM (
                SELECT
                    DATE(v.fecha) AS Fecha,
                    SUM(CASE WHEN cj.tipo = 'tradicional'     THEN v.monto ELSE 0 END) AS Tradicional,
                    SUM(CASE WHEN cj.tipo = 'no tradicional'  THEN v.monto ELSE 0 END) AS No_Tradicional,
                    SUM(CASE WHEN cj.tipo = 'recarga'         THEN v.monto ELSE 0 END) AS Recarga,
                    SUM(CASE WHEN cj.tipo = 'paquetico'       THEN v.monto ELSE 0 END) AS Paquetico
                FROM {$tabla} v
                LEFT JOIN catalogo_juegos cj
                    ON v.producto_id = cj.producto_id
                WHERE v.fecha >= ?
                  AND v.fecha < DATE_ADD(?, INTERVAL 1 DAY)
                GROUP BY DATE(v.fecha)
            ) r

            UNION ALL

            SELECT
                'TOTAL' AS Fecha,
                FORMAT(SUM(CASE WHEN cj.tipo = 'tradicional'     THEN v.monto ELSE 0 END), 2, 'en_US') AS Tradicional,
                FORMAT(SUM(CASE WHEN cj.tipo = 'no tradicional'  THEN v.monto ELSE 0 END), 2, 'en_US') AS No_Tradicional,
                FORMAT(SUM(CASE WHEN cj.tipo = 'recarga'         THEN v.monto ELSE 0 END), 2, 'en_US') AS Recarga,
                FORMAT(SUM(CASE WHEN cj.tipo = 'paquetico'       THEN v.monto ELSE 0 END), 2, 'en_US') AS Paquetico,
                FORMAT(SUM(v.monto), 2, 'en_US') AS Total_Dia
            FROM {$tabla} v
            LEFT JOIN catalogo_juegos cj
                ON v.producto_id = cj.producto_id
            WHERE v.fecha >= ?
              AND v.fecha < DATE_ADD(?, INTERVAL 1 DAY)
        ", [$fechaInicio, $fechaFin, $fechaInicio, $fechaFin]);

        return response()->json($resultados);
    }

    public function ventasPorRuta(Request $request)
    {
        return view('reportes.ventas-por-ruta');
    }

    public function listVentasPorRuta(Request $request)
    {
        $sistema = $request->input('sistema', 'Lotobet');
        $empresa = $request->input('empresa', 'todas');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        if (!$fechaInicio || !$fechaFin || $fechaInicio > $fechaFin) {
            return response()->json([]);
        }

        $tabla = $sistema === 'Lotobet' ? 'vt_usuarios_bet' : 'vt_usuarios_net';
        $empresaSql = '';
        $bindings = [$fechaInicio, $fechaFin];

        if ($empresa === 'grupo_joselito') {
            $empresaSql = ' AND LOWER(COALESCE(a.empresa, "")) LIKE ?';
            $bindings[] = '%joselito%';
        } elseif ($empresa === 'negosur') {
            $empresaSql = ' AND LOWER(COALESCE(a.empresa, "")) LIKE ?';
            $bindings[] = '%negosur%';
        }

        $sql = "
            SELECT
                r.Ruta,
                FORMAT(r.Tradicional, 2, 'en_US') AS Tradicional,
                FORMAT(r.No_Tradicional, 2, 'en_US') AS No_Tradicional,
                FORMAT(r.Recarga, 2, 'en_US') AS Recarga,
                FORMAT(r.Paquetico, 2, 'en_US') AS Paquetico,
                FORMAT(r.Tradicional + r.No_Tradicional + r.Recarga + r.Paquetico, 2, 'en_US') AS Total_Dia
            FROM (
                SELECT
                    COALESCE(NULLIF(TRIM(a.ruta), ''), 'Sin ruta') AS Ruta,
                    SUM(CASE WHEN cj.tipo = 'tradicional' THEN v.monto ELSE 0 END) AS Tradicional,
                    SUM(CASE WHEN cj.tipo = 'no tradicional' THEN v.monto ELSE 0 END) AS No_Tradicional,
                    SUM(CASE WHEN cj.tipo = 'recarga' THEN v.monto ELSE 0 END) AS Recarga,
                    SUM(CASE WHEN cj.tipo = 'paquetico' THEN v.monto ELSE 0 END) AS Paquetico
                FROM {$tabla} v
                JOIN agencias a
                    ON COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(v.agencia_id AS CHAR))), ''), '0')
                     = COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(a.terminal AS CHAR))), ''), '0')
                LEFT JOIN catalogo_juegos cj
                    ON v.producto_id = cj.producto_id
                WHERE v.fecha >= ?
                  AND v.fecha < DATE_ADD(?, INTERVAL 1 DAY)
                  {$empresaSql}
                GROUP BY
                    COALESCE(NULLIF(TRIM(a.ruta), ''), 'Sin ruta')
            ) r

            UNION ALL

            SELECT
                'TODAS' AS Ruta,
                FORMAT(SUM(CASE WHEN cj.tipo = 'tradicional' THEN v.monto ELSE 0 END), 2, 'en_US') AS Tradicional,
                FORMAT(SUM(CASE WHEN cj.tipo = 'no tradicional' THEN v.monto ELSE 0 END), 2, 'en_US') AS No_Tradicional,
                FORMAT(SUM(CASE WHEN cj.tipo = 'recarga' THEN v.monto ELSE 0 END), 2, 'en_US') AS Recarga,
                FORMAT(SUM(CASE WHEN cj.tipo = 'paquetico' THEN v.monto ELSE 0 END), 2, 'en_US') AS Paquetico,
                FORMAT(SUM(v.monto), 2, 'en_US') AS Total_Dia
            FROM {$tabla} v
            JOIN agencias a
                ON COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(v.agencia_id AS CHAR))), ''), '0')
                 = COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(a.terminal AS CHAR))), ''), '0')
            LEFT JOIN catalogo_juegos cj
                ON v.producto_id = cj.producto_id
            WHERE v.fecha >= ?
              AND v.fecha < DATE_ADD(?, INTERVAL 1 DAY)
              {$empresaSql}
        ";

        $resultados = DB::select($sql, array_merge($bindings, $bindings));

        return response()->json($resultados);
    }

    public function cruceUsuarios(Request $request)
    {
        return view('reportes.cruce-usuarios');
    }

    public function diferenciasIncentivosCruceUsuarios(Request $request)
    {
        return view('reportes.diferencias-incentivos-cruce');
    }

    public function ventasAgenciaPeriodo(Request $request)
    {
        return view('reportes.ventas-agencia-periodo');
    }

    public function listVentasAgenciaPeriodo(Request $request)
    {
        $sistema = $request->input('sistema', 'Lotobet');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');
        $periodo = $request->input('periodo', 'dia');

        if (!$fechaInicio || !$fechaFin) {
            return response()->json([]);
        }

        $tabla = $sistema === 'Lotobet' ? 'vt_usuarios_bet' : 'vt_usuarios_net';

        $selectPeriodo = $periodo === 'mes'
            ? "DATE_FORMAT(v.fecha, '%Y-%m')"
            : "DATE_FORMAT(v.fecha, '%Y-%m-%d')";

        $resultados = DB::select("
            SELECT
                v.agencia_id AS agencia_id,
                {$selectPeriodo} AS periodo,
                FORMAT(SUM(CASE WHEN c.tipo = 'tradicional'     THEN v.monto ELSE 0 END), 2, 'en_US') AS tradicional,
                FORMAT(SUM(CASE WHEN c.tipo = 'no tradicional'  THEN v.monto ELSE 0 END), 2, 'en_US') AS no_tradicional,
                FORMAT(SUM(CASE WHEN c.tipo = 'recarga'         THEN v.monto ELSE 0 END), 2, 'en_US') AS recargas,
                FORMAT(SUM(CASE WHEN c.tipo = 'paquetico'       THEN v.monto ELSE 0 END), 2, 'en_US') AS paquetico,
                FORMAT(SUM(v.monto), 2, 'en_US') AS total
            FROM {$tabla} v
            LEFT JOIN catalogo_juegos c
                ON v.producto_id = c.producto_id
            WHERE v.fecha BETWEEN ? AND ?
            GROUP BY
                v.agencia_id,
                {$selectPeriodo}
            ORDER BY
                v.agencia_id,
                periodo
        ", [$fechaInicio, $fechaFin]);

        return response()->json($resultados);
    }

    public function ventasPorAgencia(Request $request)
    {
        return view('reportes.ventas-por-agencia');
    }

    public function buscarAgencia(Request $request)
    {
        $codigo = $request->input('codigo');

        if (!$codigo) {
            return response()->json(null);
        }

        $agencia = DB::table('agencias')
            ->select('agencia', 'nombre_agencia', 'terminal')
            ->where('terminal', $codigo)
            ->first();

        return response()->json($agencia);
    }

    public function listVentasPorAgencia(Request $request)
    {
        $sistema = $request->input('sistema', 'Lotobet');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');
        $periodo = $request->input('periodo', 'dia');
        $terminal = $request->input('terminal');

        if (!$fechaInicio || !$fechaFin || !$terminal) {
            return response()->json([]);
        }

        $tabla = $sistema === 'Lotobet' ? 'vt_usuarios_bet' : 'vt_usuarios_net';

        $selectPeriodo = $periodo === 'mes'
            ? "DATE_FORMAT(v.fecha, '%Y-%m')"
            : "DATE_FORMAT(v.fecha, '%Y-%m-%d')";

        $resultados = DB::select("
            SELECT
                a.terminal AS terminal,
                a.coordinador AS coordinador,
                a.nombre_agencia AS nombre_agencia,
                a.ruta AS ruta,
                {$selectPeriodo} AS periodo,
                FORMAT(SUM(CASE WHEN c.tipo = 'tradicional'     THEN v.monto ELSE 0 END), 2, 'en_US') AS tradicional,
                FORMAT(SUM(CASE WHEN c.tipo = 'no tradicional'  THEN v.monto ELSE 0 END), 2, 'en_US') AS no_tradicional,
                FORMAT(SUM(CASE WHEN c.tipo = 'recarga'         THEN v.monto ELSE 0 END), 2, 'en_US') AS recargas,
                FORMAT(SUM(CASE WHEN c.tipo = 'paquetico'       THEN v.monto ELSE 0 END), 2, 'en_US') AS paquetico,
                FORMAT(SUM(v.monto), 2, 'en_US') AS total
            FROM {$tabla} v
            JOIN agencias a
                ON TRIM(CAST(v.agencia_id AS CHAR)) COLLATE utf8mb4_unicode_ci = TRIM(a.terminal) COLLATE utf8mb4_unicode_ci
            LEFT JOIN catalogo_juegos c
                ON v.producto_id = c.producto_id
            WHERE v.fecha BETWEEN ? AND ?
              AND TRIM(a.terminal) COLLATE utf8mb4_unicode_ci = TRIM(?) COLLATE utf8mb4_unicode_ci
            GROUP BY
                a.terminal,
                a.coordinador,
                a.nombre_agencia,
                a.ruta,
                {$selectPeriodo}
            ORDER BY
                a.terminal,
                a.coordinador,
                periodo
        ", [$fechaInicio, $fechaFin, $terminal]);

        return response()->json($resultados);
    }

    public function ventasPorCedula(Request $request)
    {
        return view('reportes.ventas-por-cedula');
    }

    public function listVentasPorCedula(Request $request)
    {
        $sistema = $request->input('sistema', 'todos');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');
        $cedula = preg_replace('/\D/', '', (string) $request->input('cedula', ''));

        if (!$fechaInicio || !$fechaFin || !$cedula || $fechaInicio > $fechaFin) {
            return response()->json([]);
        }

        $buildConsultaBase = function (string $tabla) use ($fechaInicio, $fechaFin, $cedula) {
            return DB::table($tabla)
                ->selectRaw('CAST(cedula AS CHAR(11)) AS Identificacion, DATE(fecha) AS Dia, agencia_id AS Agencia, monto')
                ->whereBetween('fecha', [$fechaInicio, $fechaFin])
                ->whereRaw("REPLACE(REPLACE(cedula, '-', ''), ' ', '') = ?", [$cedula]);
        };

        if ($sistema === 'lotonet') {
            $ventasUnificadas = $buildConsultaBase('vt_usuarios_net');
        } elseif ($sistema === 'lotobet') {
            $ventasUnificadas = $buildConsultaBase('vt_usuarios_bet');
        } else {
            $ventasUnificadas = $buildConsultaBase('vt_usuarios_net')
                ->unionAll($buildConsultaBase('vt_usuarios_bet'));
        }

        $agencias = DB::table('agencias')
            ->selectRaw("COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(terminal AS CHAR))), ''), '0') AS terminal_normalizada")
            ->selectRaw("MAX(TRIM(COALESCE(nombre_agencia, ''))) AS nombre_agencia")
            ->whereNotNull('terminal')
            ->groupByRaw("COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(terminal AS CHAR))), ''), '0')");

        $resultados = DB::query()
            ->fromSub($ventasUnificadas, 'ventas_unificadas')
            ->leftJoinSub($agencias, 'agencias_catalogo', function ($join) {
                $join->on(
                    DB::raw("COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(ventas_unificadas.Agencia AS CHAR))), ''), '0')"),
                    '=',
                    'agencias_catalogo.terminal_normalizada'
                );
            })
            ->selectRaw("
                Identificacion,
                Dia,
                Agencia,
                COALESCE(NULLIF(MAX(agencias_catalogo.nombre_agencia), ''), 'Sin nombre') AS Nombre_Agencia,
                CAST(SUM(monto) AS DECIMAL(15,2)) AS Total_Dia_Agencia
            ")
            ->groupBy('Identificacion', 'Dia', 'Agencia')
            ->orderBy('Dia', 'asc')
            ->orderByDesc('Total_Dia_Agencia')
            ->get();

        return response()->json($resultados);
    }

    public function listCruceUsuarios(Request $request)
    {
        $sistema = $request->input('sistema', 'todos');
        $estatus = $request->input('estatus');
        $empresa = $request->input('empresa', 'todos');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        if (!$fechaInicio || !$fechaFin) {
            return response()->json([]);
        }

        // Determinar la tabla según el sistema
        $ventasSql = $this->cruceUsuariosVentasSql($sistema);
        $empresaWhereSql = '';
        $empresaBindings = [];

        if ($empresa === 'grupo_joselito') {
            $empresaWhereSql = ' AND LOWER(COALESCE(a.empresa, "")) LIKE ?';
            $empresaBindings[] = '%joselito%';
        } elseif ($empresa === 'negosur') {
            $empresaWhereSql = ' AND LOWER(COALESCE(a.empresa, "")) LIKE ?';
            $empresaBindings[] = '%negosur%';
        }

        // Deshabilitar temporalmente strict mode para esta consulta
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'STRICT_TRANS_TABLES',''))");
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'NO_ZERO_DATE',''))");

        $resultados = DB::select("
            SELECT
                CAST(
                    REPLACE(REPLACE(v.cedula,'-',''),' ','')
                    AS CHAR(11)
                ) AS Identificacion,

                COALESCE(
                    MAX(CASE
                        WHEN e.empleadoid IS NOT NULL
                         AND (
                            e.fechasalida IS NULL
                            OR e.fechasalida = '0000-00-00'
                            OR TRIM(CAST(e.fechasalida AS CHAR)) = ''
                         )
                        THEN e.empleadoid
                    END),
                    MAX(e.empleadoid)
                ) AS Empleado_ID,

                CASE
                    WHEN MAX(e.empleadoid) IS NULL
                        THEN 'ACTUALIZAR EN MAESTRA DE EMPLEADOS'
                    ELSE CONCAT(
                        COALESCE(
                            MAX(CASE
                                WHEN e.empleadoid IS NOT NULL
                                 AND (
                                    e.fechasalida IS NULL
                                    OR e.fechasalida = '0000-00-00'
                                    OR TRIM(CAST(e.fechasalida AS CHAR)) = ''
                                 )
                                THEN e.nombres
                            END),
                            MAX(e.nombres)
                        ),
                        ' ',
                        COALESCE(
                            MAX(CASE
                                WHEN e.empleadoid IS NOT NULL
                                 AND (
                                    e.fechasalida IS NULL
                                    OR e.fechasalida = '0000-00-00'
                                    OR TRIM(CAST(e.fechasalida AS CHAR)) = ''
                                 )
                                THEN e.apellidos
                            END),
                            MAX(e.apellidos)
                        )
                    )
                END AS NombreCompleto,

                CASE
                    WHEN MAX(e.empleadoid) IS NULL
                      OR SUM(CASE
                            WHEN e.empleadoid IS NOT NULL
                             AND (
                                e.fechasalida IS NULL
                                OR e.fechasalida = '0000-00-00'
                                OR TRIM(CAST(e.fechasalida AS CHAR)) = ''
                             )
                            THEN 1
                            ELSE 0
                        END) = 0
                    THEN CONCAT(
                        'Agencia(s): ',
                        GROUP_CONCAT(
                            DISTINCT v.agencia_id
                            ORDER BY v.agencia_id
                            SEPARATOR ', '
                        )
                    )
                    ELSE ''
                END AS Detalle,

                CASE
                    WHEN MAX(e.empleadoid) IS NULL THEN 'No registrado'
                    WHEN SUM(CASE
                            WHEN e.empleadoid IS NOT NULL
                             AND (
                                e.fechasalida IS NULL
                                OR e.fechasalida = '0000-00-00'
                                OR TRIM(CAST(e.fechasalida AS CHAR)) = ''
                             )
                            THEN 1
                            ELSE 0
                        END) > 0
                        THEN 'Activo'
                    ELSE CONCAT('No Activo - ', MAX(NULLIF(e.fechasalida, '0000-00-00')))
                END AS Estatus,

                DATE(MAX(v.fecha)) AS Ultima_Fecha_Venta

            FROM ({$ventasSql}) v
            LEFT JOIN agencias a
                ON COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(a.terminal AS CHAR))), ''), '0')
                 = COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(v.agencia_id AS CHAR))), ''), '0')
            LEFT JOIN empleados e
                ON REPLACE(REPLACE(v.cedula,'-',''),' ','')
                 = REPLACE(REPLACE(e.cedula,'-',''),' ','')

            WHERE v.fecha >= ?
              AND v.fecha < DATE_ADD(?, INTERVAL 1 DAY)
                            AND NULLIF(REPLACE(REPLACE(v.cedula,'-',''),' ',''), '') IS NOT NULL
                            AND REPLACE(REPLACE(v.cedula,'-',''),' ','') <> '00000000000'
              {$empresaWhereSql}

            GROUP BY
                CAST(REPLACE(REPLACE(v.cedula,'-',''),' ','') AS CHAR(11))

            ORDER BY
                Ultima_Fecha_Venta DESC,
                Identificacion
        ", array_merge([$fechaInicio, $fechaFin], $empresaBindings));

        $agenciasSinCedula = DB::select("
            SELECT
                v.agencia_id AS Agencia,
                COUNT(DISTINCT DATE(v.fecha)) AS Dias_Sin_Cedula_Con_Ventas
            FROM ({$ventasSql}) v
            LEFT JOIN agencias a
                ON COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(a.terminal AS CHAR))), ''), '0')
                 = COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(v.agencia_id AS CHAR))), ''), '0')
            WHERE v.fecha >= ?
              AND v.fecha < DATE_ADD(?, INTERVAL 1 DAY)
              AND (
                    NULLIF(REPLACE(REPLACE(COALESCE(v.cedula, ''),'-',''),' ',''), '') IS NULL
                    OR REPLACE(REPLACE(COALESCE(v.cedula, ''),'-',''),' ','') = '00000000000'
                  )
              {$empresaWhereSql}
            GROUP BY v.agencia_id
            ORDER BY Dias_Sin_Cedula_Con_Ventas DESC, v.agencia_id
        ", array_merge([$fechaInicio, $fechaFin], $empresaBindings));
        
        // Restaurar el strict mode
        DB::statement("SET SESSION sql_mode='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

        $filtrosSeguimiento = [
            'En gestion' => 'en_gestion',
            'Finalizado' => 'finalizado',
        ];
        $filtrarPorSeguimiento = array_key_exists((string) $estatus, $filtrosSeguimiento);

        // Filtrar resultados
        $resultados = array_filter($resultados, function($item) use ($estatus, $filtrarPorSeguimiento) {
            if ($filtrarPorSeguimiento) {
                return $item->Estatus !== 'Activo';
            }
            // Si se seleccionó un estatus específico, filtrar por ese
            if ($estatus) {
                if ($estatus === 'No activo') {
                    return strpos($item->Estatus, 'No Activo') === 0;
                }
                return $item->Estatus === $estatus;
            }
            // Si no se seleccionó estatus (Todos), excluir los Activos
            return $item->Estatus !== 'Activo';
        });
        
        $resultados = array_values($resultados); // Reindexar el array
        $this->anexarSeguimientoCruceUsuarios($resultados);

        if (in_array($estatus, ['No activo', 'No registrado'], true)) {
            $resultados = array_values(array_filter($resultados, function ($item) {
                return $item->Seguimiento_Estado !== 'finalizado';
            }));
        }

        if ($filtrarPorSeguimiento) {
            $estadoSeguimiento = $filtrosSeguimiento[$estatus];
            $resultados = array_values(array_filter($resultados, function ($item) use ($estadoSeguimiento) {
                return $item->Seguimiento_Estado === $estadoSeguimiento;
            }));
        }

        return response()->json([
            'resultados' => $resultados,
            'agencias_sin_cedula' => $agenciasSinCedula,
        ]);
    }

    public function listDiferenciasIncentivosCruceUsuarios(Request $request)
    {
        $validated = $request->validate([
            'sistema' => 'nullable|in:Todos,Lotobet,Lotonet',
            'empresa' => 'nullable|in:todos,grupo_joselito,negosur',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'filtro_cumplimiento' => 'nullable|in:todos,cumplidos,no_cumplidos',
            'modo_calculo' => 'nullable|in:general,separado_empresa',
            'diferencia' => 'nullable|in:todas,solo_incentivos,solo_cruce,ambos',
        ]);

        $sistemaIncentivo = $validated['sistema'] ?? 'Todos';
        $sistemaCruce = strtolower($sistemaIncentivo === 'Todos' ? 'todos' : $sistemaIncentivo);
        $empresa = $validated['empresa'] ?? 'todos';
        $fechaInicio = $validated['fecha_inicio'];
        $fechaFin = $validated['fecha_fin'];
        $filtroCumplimiento = $validated['filtro_cumplimiento'] ?? 'todos';
        $modoCalculo = $validated['modo_calculo'] ?? 'general';
        $filtroDiferencia = $validated['diferencia'] ?? 'todas';

        $pendientesIncentivos = collect($this->obtenerPendientesIncentivosV5([
            'sistema' => $sistemaIncentivo,
            'empresa' => $empresa,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'filtro_cumplimiento' => $filtroCumplimiento,
            'modo_calculo' => $modoCalculo,
        ]));

        $cruceBase = $this->obtenerCruceUsuariosBase($sistemaCruce, $empresa, $fechaInicio, $fechaFin);
        $cruceRows = collect($cruceBase['resultados'] ?? []);

        $cruceAllByCedula = $cruceRows
            ->map(function ($row) {
                $cedula = preg_replace('/\D+/', '', (string) ($row->Identificacion ?? ''));
                if ($cedula === '') {
                    return null;
                }

                return [
                    'cedula' => $cedula,
                    'estatus_cruce' => (string) ($row->Estatus ?? ''),
                    'detalle_cruce' => trim((string) ($row->Detalle ?? '')),
                    'ultima_fecha_venta_cruce' => trim((string) ($row->Ultima_Fecha_Venta ?? '')),
                ];
            })
            ->filter()
            ->keyBy('cedula');

        $cruceVisibleByCedula = $cruceAllByCedula
            ->filter(fn ($row) => ($row['estatus_cruce'] ?? '') !== 'Activo')
            ->all();

        $allCedulas = $pendientesIncentivos->keys()
            ->merge(collect(array_keys($cruceVisibleByCedula)))
            ->merge(collect(array_keys($cruceAllByCedula->all())))
            ->unique()
            ->values();

        $empleadosMaestra = $this->obtenerEstadoMaestraPorCedulas($allCedulas->all());

        $rows = $allCedulas->map(function ($cedula) use ($pendientesIncentivos, $cruceVisibleByCedula, $cruceAllByCedula, $empleadosMaestra) {
            $incentivo = $pendientesIncentivos->get($cedula);
            $cruceVisible = $cruceVisibleByCedula[$cedula] ?? null;
            $cruceRegistro = $cruceAllByCedula->get($cedula);
            $maestra = $empleadosMaestra[$cedula] ?? [
                'empleadoid' => '',
                'nombre' => 'Actualizar en maestro de empleados',
                'estado' => 'No registrado',
                'nombre_vacio' => true,
            ];

            $estaEnIncentivos = $incentivo !== null;
            $estaEnCruceVisible = $cruceVisible !== null;
            $estaEnCruceBase = $cruceRegistro !== null;

            if ($estaEnIncentivos && $estaEnCruceVisible) {
                $clasificacion = 'ambos';
                $clasificacionLabel = 'Ambos';
            } elseif ($estaEnIncentivos) {
                $clasificacion = 'solo_incentivos';
                $clasificacionLabel = 'Solo Incentivos';
            } else {
                $clasificacion = 'solo_cruce';
                $clasificacionLabel = 'Solo Cruce';
            }

            return [
                'cedula' => $cedula,
                'clasificacion' => $clasificacion,
                'clasificacion_label' => $clasificacionLabel,
                'en_incentivos' => $estaEnIncentivos,
                'en_cruce' => $estaEnCruceVisible,
                'aparece_cruce_base' => $estaEnCruceBase,
                'estatus_cruce' => (string) ($cruceRegistro['estatus_cruce'] ?? ''),
                'empleadoid_maestra' => (string) ($maestra['empleadoid'] ?? ''),
                'nombre_maestra' => (string) ($maestra['nombre'] ?? 'Actualizar en maestro de empleados'),
                'estado_maestra' => (string) ($maestra['estado'] ?? 'No registrado'),
                'empresa_incentivo' => (string) ($incentivo['empresa'] ?? ''),
                'agencia_incentivo' => (string) ($incentivo['ultima_agencia_nombre'] ?? ''),
                'terminal_incentivo' => (string) ($incentivo['ultima_terminal'] ?? ''),
                'ultima_fecha_venta' => (string) (($incentivo['ultimo_dia_venta'] ?? '') ?: ($cruceRegistro['ultima_fecha_venta_cruce'] ?? '')),
                'detalle_cruce' => (string) ($cruceRegistro['detalle_cruce'] ?? ''),
                'motivo' => $this->resolverMotivoDiferenciaIncentivosCruce(
                    $estaEnIncentivos,
                    $estaEnCruceVisible,
                    $estaEnCruceBase,
                    (string) ($cruceRegistro['estatus_cruce'] ?? ''),
                    $maestra
                ),
            ];
        });

        $rows = $rows
            ->filter(fn ($row) => $row['en_incentivos'] || $row['en_cruce'])
            ->values();

        $summary = [
            'total_incentivos' => $pendientesIncentivos->count(),
            'total_cruce_visible' => count($cruceVisibleByCedula),
            'solo_incentivos' => $rows->where('clasificacion', 'solo_incentivos')->count(),
            'solo_cruce' => $rows->where('clasificacion', 'solo_cruce')->count(),
            'ambos' => $rows->where('clasificacion', 'ambos')->count(),
            'activos_ocultos_en_cruce' => $rows->filter(function ($row) {
                return $row['clasificacion'] === 'solo_incentivos'
                    && $row['aparece_cruce_base']
                    && $row['estatus_cruce'] === 'Activo';
            })->count(),
        ];

        if ($filtroDiferencia !== 'todas') {
            $rows = $rows->where('clasificacion', $filtroDiferencia)->values();
        } else {
            $rows = $rows->values();
        }

        return response()->json([
            'summary' => $summary,
            'rows' => $rows->all(),
        ]);
    }

    private function anexarSeguimientoCruceUsuarios(array &$resultados): void
    {
        foreach ($resultados as $item) {
            $item->Seguimiento_ID = null;
            $item->Seguimiento_Estado = null;
            $item->Seguimiento_Inicio = null;
            $item->Seguimiento_Finalizado = null;
        }

        if (empty($resultados) || !Schema::hasTable('cruce_usuario_seguimientos')) {
            return;
        }

        $cedulas = collect($resultados)
            ->pluck('Identificacion')
            ->map(fn($cedula) => preg_replace('/[^0-9]/', '', (string) $cedula))
            ->filter()
            ->unique()
            ->values();

        if ($cedulas->isEmpty()) {
            return;
        }

        $seguimientos = DB::table('cruce_usuario_seguimientos')
            ->whereIn('cedula', $cedulas)
            ->orderByRaw("FIELD(estado, 'finalizado', 'en_gestion', 'pendiente') ASC")
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->unique('cedula')
            ->keyBy('cedula');

        foreach ($resultados as $item) {
            $cedula = preg_replace('/[^0-9]/', '', (string) $item->Identificacion);
            $seguimiento = $seguimientos->get($cedula);

            if (!$seguimiento) {
                continue;
            }

            $item->Seguimiento_ID = $seguimiento->id;
            $item->Seguimiento_Estado = $seguimiento->estado;
            $item->Seguimiento_Inicio = $seguimiento->gestion_inicio_at;
            $item->Seguimiento_Finalizado = $seguimiento->finalizado_at;
        }
    }

    public function listCruceUsuariosSinCedulaFechas(Request $request)
    {
        $sistema = $request->input('sistema', 'todos');
        $empresa = $request->input('empresa', 'todos');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');
        $agenciaId = $request->input('agencia_id');

        if (!$fechaInicio || !$fechaFin || !$agenciaId) {
            return response()->json([
                'agencia' => $agenciaId,
                'fechas' => [],
            ]);
        }

        $ventasSql = $this->cruceUsuariosVentasSql($sistema);
        $empresaWhereSql = '';
        $bindings = [$fechaInicio, $fechaFin, $agenciaId];

        if ($empresa === 'grupo_joselito') {
            $empresaWhereSql = ' AND LOWER(COALESCE(a.empresa, "")) LIKE ?';
            $bindings[] = '%joselito%';
        } elseif ($empresa === 'negosur') {
            $empresaWhereSql = ' AND LOWER(COALESCE(a.empresa, "")) LIKE ?';
            $bindings[] = '%negosur%';
        }

        $fechas = DB::select(" 
            SELECT
                DATE(v.fecha) AS Fecha,
                COUNT(*) AS Cantidad_Ventas
            FROM ({$ventasSql}) v
            LEFT JOIN agencias a
                ON COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(a.terminal AS CHAR))), ''), '0')
                 = COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(v.agencia_id AS CHAR))), ''), '0')
            WHERE v.fecha >= ?
              AND v.fecha < DATE_ADD(?, INTERVAL 1 DAY)
              AND v.agencia_id = ?
              AND (
                    NULLIF(REPLACE(REPLACE(COALESCE(v.cedula, ''),'-',''),' ',''), '') IS NULL
                    OR REPLACE(REPLACE(COALESCE(v.cedula, ''),'-',''),' ','') = '00000000000'
                  )
              {$empresaWhereSql}
            GROUP BY DATE(v.fecha)
            ORDER BY Fecha DESC
        ", $bindings);

        return response()->json([
            'agencia' => $agenciaId,
            'fechas' => $fechas,
        ]);
    }

    private function obtenerCruceUsuariosBase(string $sistema, string $empresa, string $fechaInicio, string $fechaFin): array
    {
        $ventasSql = $this->cruceUsuariosVentasSql($sistema);
        $empresaWhereSql = '';
        $empresaBindings = [];

        if ($empresa === 'grupo_joselito') {
            $empresaWhereSql = ' AND LOWER(COALESCE(a.empresa, "")) LIKE ?';
            $empresaBindings[] = '%joselito%';
        } elseif ($empresa === 'negosur') {
            $empresaWhereSql = ' AND LOWER(COALESCE(a.empresa, "")) LIKE ?';
            $empresaBindings[] = '%negosur%';
        }

        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'STRICT_TRANS_TABLES',''))");
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'NO_ZERO_DATE',''))");

        $resultados = DB::select("
            SELECT
                CAST(REPLACE(REPLACE(v.cedula,'-',''),' ','') AS CHAR(11)) AS Identificacion,
                COALESCE(
                    MAX(CASE
                        WHEN e.empleadoid IS NOT NULL
                         AND (
                            e.fechasalida IS NULL
                            OR e.fechasalida = '0000-00-00'
                            OR TRIM(CAST(e.fechasalida AS CHAR)) = ''
                         )
                        THEN e.empleadoid
                    END),
                    MAX(e.empleadoid)
                ) AS Empleado_ID,
                CASE
                    WHEN MAX(e.empleadoid) IS NULL THEN 'ACTUALIZAR EN MAESTRA DE EMPLEADOS'
                    ELSE CONCAT(
                        COALESCE(
                            MAX(CASE
                                WHEN e.empleadoid IS NOT NULL
                                 AND (
                                    e.fechasalida IS NULL
                                    OR e.fechasalida = '0000-00-00'
                                    OR TRIM(CAST(e.fechasalida AS CHAR)) = ''
                                 )
                                THEN e.nombres
                            END),
                            MAX(e.nombres)
                        ),
                        ' ',
                        COALESCE(
                            MAX(CASE
                                WHEN e.empleadoid IS NOT NULL
                                 AND (
                                    e.fechasalida IS NULL
                                    OR e.fechasalida = '0000-00-00'
                                    OR TRIM(CAST(e.fechasalida AS CHAR)) = ''
                                 )
                                THEN e.apellidos
                            END),
                            MAX(e.apellidos)
                        )
                    )
                END AS NombreCompleto,
                CASE
                    WHEN MAX(e.empleadoid) IS NULL
                      OR SUM(CASE
                            WHEN e.empleadoid IS NOT NULL
                             AND (
                                e.fechasalida IS NULL
                                OR e.fechasalida = '0000-00-00'
                                OR TRIM(CAST(e.fechasalida AS CHAR)) = ''
                             )
                            THEN 1
                            ELSE 0
                        END) = 0
                    THEN CONCAT(
                        'Agencia(s): ',
                        GROUP_CONCAT(DISTINCT v.agencia_id ORDER BY v.agencia_id SEPARATOR ', ')
                    )
                    ELSE ''
                END AS Detalle,
                CASE
                    WHEN MAX(e.empleadoid) IS NULL THEN 'No registrado'
                    WHEN SUM(CASE
                            WHEN e.empleadoid IS NOT NULL
                             AND (
                                e.fechasalida IS NULL
                                OR e.fechasalida = '0000-00-00'
                                OR TRIM(CAST(e.fechasalida AS CHAR)) = ''
                             )
                            THEN 1
                            ELSE 0
                        END) > 0
                        THEN 'Activo'
                    ELSE CONCAT('No Activo - ', MAX(NULLIF(e.fechasalida, '0000-00-00')))
                END AS Estatus,
                DATE(MAX(v.fecha)) AS Ultima_Fecha_Venta
            FROM ({$ventasSql}) v
            LEFT JOIN agencias a
                ON COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(a.terminal AS CHAR))), ''), '0')
                 = COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(v.agencia_id AS CHAR))), ''), '0')
            LEFT JOIN empleados e
                ON REPLACE(REPLACE(v.cedula,'-',''),' ','')
                 = REPLACE(REPLACE(e.cedula,'-',''),' ','')
            WHERE v.fecha >= ?
              AND v.fecha < DATE_ADD(?, INTERVAL 1 DAY)
              AND NULLIF(REPLACE(REPLACE(v.cedula,'-',''),' ',''), '') IS NOT NULL
              AND REPLACE(REPLACE(v.cedula,'-',''),' ','') <> '00000000000'
              {$empresaWhereSql}
            GROUP BY CAST(REPLACE(REPLACE(v.cedula,'-',''),' ','') AS CHAR(11))
            ORDER BY Ultima_Fecha_Venta DESC, Identificacion
        ", array_merge([$fechaInicio, $fechaFin], $empresaBindings));

        $agenciasSinCedula = DB::select("
            SELECT
                v.agencia_id AS Agencia,
                COUNT(DISTINCT DATE(v.fecha)) AS Dias_Sin_Cedula_Con_Ventas
            FROM ({$ventasSql}) v
            LEFT JOIN agencias a
                ON COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(a.terminal AS CHAR))), ''), '0')
                 = COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(v.agencia_id AS CHAR))), ''), '0')
            WHERE v.fecha >= ?
              AND v.fecha < DATE_ADD(?, INTERVAL 1 DAY)
              AND (
                    NULLIF(REPLACE(REPLACE(COALESCE(v.cedula, ''),'-',''),' ',''), '') IS NULL
                    OR REPLACE(REPLACE(COALESCE(v.cedula, ''),'-',''),' ','') = '00000000000'
                  )
              {$empresaWhereSql}
            GROUP BY v.agencia_id
            ORDER BY Dias_Sin_Cedula_Con_Ventas DESC, v.agencia_id
        ", array_merge([$fechaInicio, $fechaFin], $empresaBindings));

        DB::statement("SET SESSION sql_mode='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

        return [
            'resultados' => $resultados,
            'agencias_sin_cedula' => $agenciasSinCedula,
        ];
    }

    private function obtenerPendientesIncentivosV5(array $params): array
    {
        $request = Request::create('/incentivos/reporte-nuevo-incentivo-v5', 'GET', [
            'sistema' => $params['sistema'] ?? 'Todos',
            'fecha_ini' => $params['fecha_inicio'] ?? null,
            'fecha_fin' => $params['fecha_fin'] ?? null,
            'min_dias_venta' => 1,
            'filtro_cumplimiento' => $params['filtro_cumplimiento'] ?? 'todos',
            'modo_calculo' => $params['modo_calculo'] ?? 'general',
        ]);

        $response = app(IncentivosController::class)->reporteNuevoIncentivoV5($request);
        $payload = $response->getData(true);
        $rows = collect($payload['data'] ?? []);
        $empresa = $params['empresa'] ?? 'todos';

        $rows = $rows->filter(function ($row) use ($empresa) {
            $ventasMesActual = (float) str_replace(',', '', (string) ($row['ventas_mes_actual'] ?? 0));
            if ($ventasMesActual <= 0) {
                return false;
            }

            if ($empresa === 'grupo_joselito') {
                return str_contains(strtolower((string) ($row['empresa'] ?? '')), 'joselito');
            }

            if ($empresa === 'negosur') {
                return str_contains(strtolower((string) ($row['empresa'] ?? '')), 'negosur');
            }

            return true;
        });

        $grouped = [];
        foreach ($rows as $row) {
            if (trim((string) ($row['nombre'] ?? '')) !== 'Actualizar en maestro de empleados') {
                continue;
            }

            $cedulaNormalizada = preg_replace('/\D+/', '', (string) ($row['cedula'] ?? ''));
            $cedulaOriginal = trim((string) ($row['cedula'] ?? ''));
            $key = $cedulaNormalizada !== '' ? $cedulaNormalizada : $cedulaOriginal;

            if ($key === '') {
                continue;
            }

            $empresaLabel = trim((string) ($row['empresa'] ?? ''));
            $fecha = trim((string) ($row['ultimo_dia_venta'] ?? ''));

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'cedula' => $cedulaOriginal !== '' ? $cedulaOriginal : $cedulaNormalizada,
                    'empresa_set' => [],
                    'ultima_terminal' => trim((string) ($row['ultima_terminal'] ?? '')),
                    'ultima_agencia_nombre' => trim((string) ($row['ultima_agencia_nombre'] ?? 'SIN AGENCIA')) ?: 'SIN AGENCIA',
                    'ultimo_dia_venta' => $fecha,
                ];
            }

            if ($empresaLabel !== '') {
                $grouped[$key]['empresa_set'][mb_strtolower($empresaLabel)] = $empresaLabel;
            }

            if ($grouped[$key]['ultimo_dia_venta'] === '' || ($fecha !== '' && $fecha > $grouped[$key]['ultimo_dia_venta'])) {
                $grouped[$key]['ultimo_dia_venta'] = $fecha;
                $grouped[$key]['ultima_terminal'] = trim((string) ($row['ultima_terminal'] ?? ''));
                $grouped[$key]['ultima_agencia_nombre'] = trim((string) ($row['ultima_agencia_nombre'] ?? 'SIN AGENCIA')) ?: 'SIN AGENCIA';
            }
        }

        return collect($grouped)
            ->mapWithKeys(function ($row, $cedula) {
                return [$cedula => [
                    'cedula' => $row['cedula'],
                    'empresa' => implode(' | ', array_values($row['empresa_set'])),
                    'ultima_terminal' => $row['ultima_terminal'],
                    'ultima_agencia_nombre' => $row['ultima_agencia_nombre'],
                    'ultimo_dia_venta' => $row['ultimo_dia_venta'],
                ]];
            })
            ->all();
    }

    private function obtenerEstadoMaestraPorCedulas(array $cedulas): array
    {
        $cedulas = collect($cedulas)
            ->map(fn ($cedula) => preg_replace('/\D+/', '', (string) $cedula))
            ->filter()
            ->unique()
            ->values();

        if ($cedulas->isEmpty()) {
            return [];
        }

        $hasActivo = Schema::hasColumn('empleados', 'activo');
        $hasFechaSalida = Schema::hasColumn('empleados', 'fechasalida');

        return DB::table('empleados')
            ->whereIn(DB::raw('CAST(cedula AS UNSIGNED)'), $cedulas->all())
            ->selectRaw('CAST(cedula AS UNSIGNED) AS cedula')
            ->selectRaw('MAX(empleadoid) AS empleadoid')
            ->selectRaw("MAX(TRIM(CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, '')))) AS nombre")
            ->selectRaw($hasActivo ? 'MIN(COALESCE(activo, 1)) AS activo' : '1 AS activo')
            ->selectRaw($hasFechaSalida ? "MAX(NULLIF(TRIM(CAST(fechasalida AS CHAR)), '')) AS fechasalida" : "'' AS fechasalida")
            ->groupByRaw('CAST(cedula AS UNSIGNED)')
            ->get()
            ->mapWithKeys(function ($row) {
                $fechaSalida = trim((string) ($row->fechasalida ?? ''));
                if ($fechaSalida === '0000-00-00') {
                    $fechaSalida = '';
                }

                $nombre = trim((string) ($row->nombre ?? ''));
                $empleadoid = trim((string) ($row->empleadoid ?? ''));
                $activo = (int) ($row->activo ?? 1) === 1;

                $estado = 'Activo';
                if ($empleadoid === '') {
                    $estado = 'No registrado';
                } elseif ($fechaSalida !== '' || !$activo) {
                    $estado = $fechaSalida !== '' ? 'No activo - ' . $fechaSalida : 'No activo';
                } elseif ($nombre === '') {
                    $estado = 'Activo con nombre vacio';
                }

                return [preg_replace('/\D+/', '', (string) ($row->cedula ?? '')) => [
                    'empleadoid' => $empleadoid,
                    'nombre' => $nombre !== '' ? $nombre : 'Actualizar en maestro de empleados',
                    'estado' => $estado,
                    'nombre_vacio' => $nombre === '',
                ]];
            })
            ->all();
    }

    private function resolverMotivoDiferenciaIncentivosCruce(
        bool $estaEnIncentivos,
        bool $estaEnCruceVisible,
        bool $estaEnCruceBase,
        string $estatusCruce,
        array $maestra
    ): string {
        $empleadoid = trim((string) ($maestra['empleadoid'] ?? ''));
        $estadoMaestra = trim((string) ($maestra['estado'] ?? ''));
        $nombreVacio = (bool) ($maestra['nombre_vacio'] ?? false);

        if ($estaEnIncentivos && $estaEnCruceVisible) {
            return 'La cedula aparece como pendiente en Incentivos y tambien queda visible en Cruce.';
        }

        if ($estaEnIncentivos && !$estaEnCruceVisible) {
            if ($estaEnCruceBase && $estatusCruce === 'Activo') {
                return 'Cruce la detecta, pero como Activo no se muestra por defecto.';
            }

            if ($empleadoid !== '' && $nombreVacio) {
                return 'Tiene empleadoid en maestra, pero nombre vacio; Incentivos la marca pendiente y Cruce no la toma como No registrado.';
            }

            if ($estadoMaestra !== '' && $estadoMaestra !== 'No registrado') {
                return 'Incentivos la considera pendiente por nombre, pero no cae en el conjunto visible actual de Cruce.';
            }

            return 'Aparece en Incentivos, pero no en el conjunto visible de Cruce para estos filtros.';
        }

        if (!$estaEnIncentivos && $estaEnCruceVisible) {
            if (str_starts_with($estatusCruce, 'No Activo')) {
                return 'Cruce la muestra como No activa; Incentivos no la cuenta por actualizar porque si encontro nombre en maestra.';
            }

            if ($estatusCruce === 'No registrado') {
                return 'Cruce la marca como No registrada, pero no quedo en el subconjunto pendiente de Incentivos.';
            }

            return 'Cruce la muestra como diferencia visible y Incentivos no la cuenta como pendiente.';
        }

        return 'Cedula fuera del cruce visible por defecto.';
    }

    private function cruceUsuariosVentasSql(string $sistema): string
    {
        $sistema = strtolower(trim($sistema));

        if ($sistema === 'lotobet') {
            return 'SELECT cedula, agencia_id, fecha FROM vt_usuarios_bet';
        }

        if ($sistema === 'lotonet') {
            return 'SELECT cedula, agencia_id, fecha FROM vt_usuarios_net';
        }

        return '
            SELECT cedula, agencia_id, fecha FROM vt_usuarios_bet
            UNION ALL
            SELECT cedula, agencia_id, fecha FROM vt_usuarios_net
        ';
    }

    // ========== VERIFICADOR DE USUARIOS ==========
    public function verificadorUsuarios(Request $request)
    {
        return view('reportes.verificador-usuarios');
    }

    public function listVerificadorUsuarios(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');
        $sistema = $request->input('sistema', 'todos'); // todos, lotobet, lotonet

        if (!$fechaInicio || !$fechaFin) {
            return response()->json([]);
        }

        // Deshabilitar temporalmente strict mode para esta consulta
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'STRICT_TRANS_TABLES',''))");
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'NO_ZERO_DATE',''))");

        $query = "
            SELECT
                e.empleadoid,
                e.nombres,
                e.apellidos,
                c.cedula,

                -- Horas separadas
                ROUND(COALESCE(an.horas_net, 0), 2) AS horas_net,
                ROUND(COALESCE(ab.horas_bet, 0), 2) AS horas_bet,
                ROUND(COALESCE(an.horas_net, 0) + COALESCE(ab.horas_bet, 0), 2) AS horas_total,

                -- Cantidad de faltantes
                COALESCE(fn.cant_faltantes_net, 0) AS cant_faltantes_net,
                COALESCE(fb.cant_faltantes_bet, 0) AS cant_faltantes_bet,
                COALESCE(fn.cant_faltantes_net, 0) + COALESCE(fb.cant_faltantes_bet, 0) AS cant_faltantes_total,

                -- Monto de faltantes
                ROUND(COALESCE(fn.monto_faltantes_net, 0), 2) AS monto_faltantes_net,
                ROUND(COALESCE(fb.monto_faltantes_bet, 0), 2) AS monto_faltantes_bet,
                ROUND(
                    COALESCE(fn.monto_faltantes_net, 0) +
                    COALESCE(fb.monto_faltantes_bet, 0),
                    2
                ) AS monto_faltantes_total,

                -- Comentario si la cédula no existe en empleados
                CASE
                    WHEN e.empleadoid IS NULL
                         AND (
                             COALESCE(an.horas_net, 0) > 0
                             OR COALESCE(ab.horas_bet, 0) > 0
                             OR COALESCE(fn.cant_faltantes_net, 0) > 0
                             OR COALESCE(fb.cant_faltantes_bet, 0) > 0
                         )
                    THEN 'cedula sin nombre'
                    ELSE ''
                END AS comentario

            FROM (
                -- Cédulas con actividad (normalizadas)
                SELECT DISTINCT REPLACE(identificacion, '-', '') AS cedula
                FROM asistencias_net
                WHERE entrada >= ? AND entrada < DATE_ADD(?, INTERVAL 1 DAY)

                UNION
                SELECT DISTINCT REPLACE(cedula, '-', '')
                FROM asistencias_bet
                WHERE fecha BETWEEN ? AND ?

                UNION
                SELECT DISTINCT REPLACE(identificacion, '-', '')
                FROM faltantes_net
                WHERE fecha BETWEEN ? AND ?

                UNION
                SELECT DISTINCT REPLACE(identificacion, '-', '')
                FROM faltantes_bet
                WHERE fecha BETWEEN ? AND ?
            ) c

            LEFT JOIN empleados e
                ON REPLACE(e.cedula, '-', '') = c.cedula

            LEFT JOIN (
                -- Horas NET
                SELECT
                    REPLACE(identificacion, '-', '') AS cedula,
                    SUM(GREATEST(TIMESTAMPDIFF(SECOND, entrada, salida), 0)) / 3600 AS horas_net
                FROM asistencias_net
                WHERE entrada >= ? AND entrada < DATE_ADD(?, INTERVAL 1 DAY)
                  AND salida IS NOT NULL
                GROUP BY REPLACE(identificacion, '-', '')
            ) an ON an.cedula = c.cedula

            LEFT JOIN (
                -- Horas BET
                SELECT
                    REPLACE(cedula, '-', '') AS cedula,
                    SUM(GREATEST(TIMESTAMPDIFF(SECOND, primer_login, ultimo_login), 0)) / 3600 AS horas_bet
                FROM asistencias_bet
                WHERE fecha BETWEEN ? AND ?
                  AND primer_login IS NOT NULL
                  AND ultimo_login IS NOT NULL
                GROUP BY REPLACE(cedula, '-', '')
            ) ab ON ab.cedula = c.cedula

            LEFT JOIN (
                -- Faltantes NET
                SELECT
                    REPLACE(identificacion, '-', '') AS cedula,
                    COUNT(*) AS cant_faltantes_net,
                    SUM(COALESCE(monto, 0)) AS monto_faltantes_net
                FROM faltantes_net
                WHERE fecha BETWEEN ? AND ?
                GROUP BY REPLACE(identificacion, '-', '')
            ) fn ON fn.cedula = c.cedula

            LEFT JOIN (
                -- Faltantes BET
                SELECT
                    REPLACE(identificacion, '-', '') AS cedula,
                    COUNT(*) AS cant_faltantes_bet,
                    SUM(COALESCE(monto, 0)) AS monto_faltantes_bet
                FROM faltantes_bet
                WHERE fecha BETWEEN ? AND ?
                GROUP BY REPLACE(identificacion, '-', '')
            ) fb ON fb.cedula = c.cedula

            ORDER BY
                (e.empleadoid IS NULL) DESC,
                e.nombres,
                e.apellidos,
                c.cedula
        ";

        $resultados = DB::select($query, [
            $fechaInicio, $fechaFin,  // asistencias_net
            $fechaInicio, $fechaFin,  // asistencias_bet
            $fechaInicio, $fechaFin,  // faltantes_net
            $fechaInicio, $fechaFin,  // faltantes_bet
            $fechaInicio, $fechaFin,  // an (horas net)
            $fechaInicio, $fechaFin,  // ab (horas bet)
            $fechaInicio, $fechaFin,  // fn (faltantes net)
            $fechaInicio, $fechaFin   // fb (faltantes bet)
        ]);

        // Restaurar el strict mode
        DB::statement("SET SESSION sql_mode='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

        // Filtrar por sistema si se especifica
        if ($sistema !== 'todos') {
            $resultados = array_filter($resultados, function($item) use ($sistema) {
                if ($sistema === 'lotobet') {
                    return $item->horas_bet > 0 || $item->cant_faltantes_bet > 0;
                } elseif ($sistema === 'lotonet') {
                    return $item->horas_net > 0 || $item->cant_faltantes_net > 0;
                }
                return true;
            });
            $resultados = array_values($resultados);
        }

        return response()->json($resultados);
    }

    public function excelVerificadorUsuarios(Request $request)
    {
        ini_set('memory_limit', '2G');
        ini_set('max_execution_time', 300);

        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');
        $sistema = $request->input('sistema', 'todos');

        if (!$fechaInicio || !$fechaFin) {
            return response()->json(['error' => 'Fechas requeridas'], 400);
        }

        // Deshabilitar temporalmente strict mode
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'STRICT_TRANS_TABLES',''))");
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'NO_ZERO_DATE',''))");

        $query = "
            SELECT
                e.empleadoid,
                e.nombres,
                e.apellidos,
                c.cedula,
                ROUND(COALESCE(an.horas_net, 0), 2) AS horas_net,
                ROUND(COALESCE(ab.horas_bet, 0), 2) AS horas_bet,
                ROUND(COALESCE(an.horas_net, 0) + COALESCE(ab.horas_bet, 0), 2) AS horas_total,
                COALESCE(fn.cant_faltantes_net, 0) AS cant_faltantes_net,
                COALESCE(fb.cant_faltantes_bet, 0) AS cant_faltantes_bet,
                COALESCE(fn.cant_faltantes_net, 0) + COALESCE(fb.cant_faltantes_bet, 0) AS cant_faltantes_total,
                ROUND(COALESCE(fn.monto_faltantes_net, 0), 2) AS monto_faltantes_net,
                ROUND(COALESCE(fb.monto_faltantes_bet, 0), 2) AS monto_faltantes_bet,
                ROUND(
                    COALESCE(fn.monto_faltantes_net, 0) +
                    COALESCE(fb.monto_faltantes_bet, 0),
                    2
                ) AS monto_faltantes_total,
                CASE
                    WHEN e.empleadoid IS NULL
                         AND (
                             COALESCE(an.horas_net, 0) > 0
                             OR COALESCE(ab.horas_bet, 0) > 0
                             OR COALESCE(fn.cant_faltantes_net, 0) > 0
                             OR COALESCE(fb.cant_faltantes_bet, 0) > 0
                         )
                    THEN 'cedula sin nombre'
                    ELSE ''
                END AS comentario
            FROM (
                SELECT DISTINCT REPLACE(identificacion, '-', '') AS cedula
                FROM asistencias_net
                WHERE entrada >= ? AND entrada < DATE_ADD(?, INTERVAL 1 DAY)
                UNION
                SELECT DISTINCT REPLACE(cedula, '-', '')
                FROM asistencias_bet
                WHERE fecha BETWEEN ? AND ?
                UNION
                SELECT DISTINCT REPLACE(identificacion, '-', '')
                FROM faltantes_net
                WHERE fecha BETWEEN ? AND ?
                UNION
                SELECT DISTINCT REPLACE(identificacion, '-', '')
                FROM faltantes_bet
                WHERE fecha BETWEEN ? AND ?
            ) c
            LEFT JOIN empleados e
                ON REPLACE(e.cedula, '-', '') = c.cedula
            LEFT JOIN (
                SELECT
                    REPLACE(identificacion, '-', '') AS cedula,
                    SUM(GREATEST(TIMESTAMPDIFF(SECOND, entrada, salida), 0)) / 3600 AS horas_net
                FROM asistencias_net
                WHERE entrada >= ? AND entrada < DATE_ADD(?, INTERVAL 1 DAY)
                  AND salida IS NOT NULL
                GROUP BY REPLACE(identificacion, '-', '')
            ) an ON an.cedula = c.cedula
            LEFT JOIN (
                SELECT
                    REPLACE(cedula, '-', '') AS cedula,
                    SUM(GREATEST(TIMESTAMPDIFF(SECOND, primer_login, ultimo_login), 0)) / 3600 AS horas_bet
                FROM asistencias_bet
                WHERE fecha BETWEEN ? AND ?
                  AND primer_login IS NOT NULL
                  AND ultimo_login IS NOT NULL
                GROUP BY REPLACE(cedula, '-', '')
            ) ab ON ab.cedula = c.cedula
            LEFT JOIN (
                SELECT
                    REPLACE(identificacion, '-', '') AS cedula,
                    COUNT(*) AS cant_faltantes_net,
                    SUM(COALESCE(monto, 0)) AS monto_faltantes_net
                FROM faltantes_net
                WHERE fecha BETWEEN ? AND ?
                GROUP BY REPLACE(identificacion, '-', '')
            ) fn ON fn.cedula = c.cedula
            LEFT JOIN (
                SELECT
                    REPLACE(identificacion, '-', '') AS cedula,
                    COUNT(*) AS cant_faltantes_bet,
                    SUM(COALESCE(monto, 0)) AS monto_faltantes_bet
                FROM faltantes_bet
                WHERE fecha BETWEEN ? AND ?
                GROUP BY REPLACE(identificacion, '-', '')
            ) fb ON fb.cedula = c.cedula
            ORDER BY
                (e.empleadoid IS NULL) DESC,
                e.nombres,
                e.apellidos,
                c.cedula
        ";

        $resultados = DB::select($query, [
            $fechaInicio, $fechaFin,
            $fechaInicio, $fechaFin,
            $fechaInicio, $fechaFin,
            $fechaInicio, $fechaFin,
            $fechaInicio, $fechaFin,
            $fechaInicio, $fechaFin,
            $fechaInicio, $fechaFin,
            $fechaInicio, $fechaFin
        ]);

        DB::statement("SET SESSION sql_mode='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

        // Filtrar por sistema
        if ($sistema !== 'todos') {
            $resultados = array_filter($resultados, function($item) use ($sistema) {
                if ($sistema === 'lotobet') {
                    return $item->horas_bet > 0 || $item->cant_faltantes_bet > 0;
                } elseif ($sistema === 'lotonet') {
                    return $item->horas_net > 0 || $item->cant_faltantes_net > 0;
                }
                return true;
            });
            $resultados = array_values($resultados);
        }

        $fileName = 'verificador_usuarios_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new \App\Exports\VerificadorUsuariosExport($resultados), $fileName);
    }
}
