<?php

namespace App\Http\Controllers;

use App\Exports\FaltantesExport;
use App\Exports\VentasUsuarioExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    function ventasUsuarioBet(Request $request)
    {
        return view('reportes.ventas-usuario-bet');
    }

    public function listVentasUsuarioBet(Request $request)
    {
        header('Content-Type: application/json');

        $mes = $request->input('mes');
        $page = $request->input('page', 1);

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

        $fileName = 'ventas_usuarioio_bet_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new VentasUsuarioExport($tipo, $fecha, $mes), $fileName);
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

    public function listFaltantesBet(Request $request)
    {
        header('Content-Type: application/json');

        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        $query = DB::table('faltantes_bet')
            ->leftJoin('empleados', 'faltantes_bet.identificacion', '=', 'empleados.cedula')
            ->select(
                'faltantes_bet.agencia_id',
                'faltantes_bet.identificacion',
                DB::raw("CONCAT(COALESCE(empleados.nombres, ''), ' ', COALESCE(empleados.apellidos, '')) as nombre_empleado"),
                DB::raw('COUNT(faltantes_bet.faltante_id) as cantidad_faltantes'),
                DB::raw('SUM(faltantes_bet.monto) as total_monto')
            )
            ->whereNotNull('faltantes_bet.identificacion')
            ->where('faltantes_bet.identificacion', '!=', '');

        if ($fechaInicio && $fechaFin) {
            $query->whereBetween('faltantes_bet.fecha', [$fechaInicio, $fechaFin]);
        }

        $registros = $query
            ->groupBy('faltantes_bet.agencia_id', 'faltantes_bet.identificacion', 'empleados.nombres', 'empleados.apellidos')
            ->orderBy('total_monto', 'desc')
            ->paginate(50);

        return $registros->toJson();
    }

    public function excelFaltantesBet(Request $request)
    {
        ini_set('memory_limit', '2G');
        ini_set('max_execution_time', 300);

        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        $query = DB::table('faltantes_bet')
            ->leftJoin('empleados', 'faltantes_bet.identificacion', '=', 'empleados.cedula')
            ->select(
                'faltantes_bet.identificacion',
                DB::raw("CONCAT(COALESCE(empleados.nombres, ''), ' ', COALESCE(empleados.apellidos, '')) as nombre_empleado"),
                DB::raw('COUNT(faltantes_bet.faltante_id) as cantidad_faltantes'),
                DB::raw('SUM(faltantes_bet.monto) as total_monto')
            )
            ->whereNotNull('faltantes_bet.identificacion')
            ->where('faltantes_bet.identificacion', '!=', '');

        if ($fechaInicio && $fechaFin) {
            $query->whereBetween('faltantes_bet.fecha', [$fechaInicio, $fechaFin]);
        }

        $registros = $query
            ->groupBy('faltantes_bet.identificacion', 'empleados.nombres', 'empleados.apellidos')
            ->orderBy('total_monto', 'desc')
            ->get();

        $fileName = 'faltantes_bet_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new \App\Exports\FaltantesBetExport($registros), $fileName);
    }

    public function pdfFaltantesBet(Request $request)
    {
        ini_set('memory_limit', '1G');

        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        $query = DB::table('faltantes_bet')
            ->leftJoin('empleados', 'faltantes_bet.identificacion', '=', 'empleados.cedula')
            ->select(
                'faltantes_bet.identificacion',
                DB::raw("CONCAT(COALESCE(empleados.nombres, ''), ' ', COALESCE(empleados.apellidos, '')) as nombre_empleado"),
                DB::raw('COUNT(faltantes_bet.faltante_id) as cantidad_faltantes'),
                DB::raw('SUM(faltantes_bet.monto) as total_monto')
            )
            ->whereNotNull('faltantes_bet.identificacion')
            ->where('faltantes_bet.identificacion', '!=', '');

        if ($fechaInicio && $fechaFin) {
            $query->whereBetween('faltantes_bet.fecha', [$fechaInicio, $fechaFin]);
        }

        $registros = $query
            ->groupBy('faltantes_bet.identificacion', 'empleados.nombres', 'empleados.apellidos')
            ->orderBy('total_monto', 'desc')
            ->get();

        $pdf = Pdf::loadView('reportes.faltantes-bet-pdf', compact('registros'))
            ->setPaper('A4', 'portrait');

        return $pdf->download('reporte_faltantes_bet.pdf');
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
            return response()->json([]);
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

    public function cruceUsuarios(Request $request)
    {
        return view('reportes.cruce-usuarios');
    }

    public function listCruceUsuarios(Request $request)
    {
        $sistema = $request->input('sistema', 'Lotobet');
        $estatus = $request->input('estatus');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        if (!$fechaInicio || !$fechaFin) {
            return response()->json([]);
        }

        // Determinar la tabla según el sistema
        $tabla = $sistema === 'Lotobet' ? 'vt_usuarios_bet' : 'vt_usuarios_net';

        // Deshabilitar temporalmente strict mode para esta consulta
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'STRICT_TRANS_TABLES',''))");
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'NO_ZERO_DATE',''))");

        $resultados = DB::select("
            SELECT
                CAST(
                    REPLACE(REPLACE(v.cedula,'-',''),' ','')
                    AS CHAR(11)
                ) AS Identificacion,

                MAX(e.empleadoid) AS Empleado_ID,

                CASE
                    WHEN MAX(e.empleadoid) IS NULL
                        THEN 'ACTUALIZAR EN MAESTRA DE EMPLEADOS'
                    ELSE CONCAT(MAX(e.nombres), ' ', MAX(e.apellidos))
                END AS NombreCompleto,

                CASE
                    WHEN MAX(e.empleadoid) IS NULL
                      OR (MAX(e.fechasalida) IS NOT NULL AND MAX(e.fechasalida) <> '0000-00-00')
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
                    WHEN MAX(e.fechasalida) IS NULL OR MAX(e.fechasalida) = '0000-00-00'
                        THEN 'Activo'
                    ELSE CONCAT('No Activo - ', MAX(e.fechasalida))
                END AS Estatus,

                DATE(MAX(v.fecha)) AS Ultima_Fecha_Venta

            FROM {$tabla} v
            LEFT JOIN empleados e
                ON REPLACE(REPLACE(v.cedula,'-',''),' ','')
                 = REPLACE(REPLACE(e.cedula,'-',''),' ','')

            WHERE v.fecha >= ?
              AND v.fecha < DATE_ADD(?, INTERVAL 1 DAY)

            GROUP BY
                CAST(REPLACE(REPLACE(v.cedula,'-',''),' ','') AS CHAR(11))

            ORDER BY
                Ultima_Fecha_Venta DESC,
                Identificacion
        ", [$fechaInicio, $fechaFin]);
        
        // Restaurar el strict mode
        DB::statement("SET SESSION sql_mode='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

        // Filtrar resultados
        $resultados = array_filter($resultados, function($item) use ($estatus) {
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

        return response()->json($resultados);
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
