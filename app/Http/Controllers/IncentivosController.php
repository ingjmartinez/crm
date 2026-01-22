<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IncentivosController extends Controller
{
    public function index()
    {
        $productosExcluidos = DB::table('catalogo_juegos')
            ->whereIn('producto_id', [538, 539])
            ->get();

        return view('incentivos.index', [
            'productosExcluidos' => $productosExcluidos
        ]);
    }

    public function generar(Request $request)
    {
        $mes = (int)$request->input('mes');
        $anio = (int)$request->input('year');
        $excluidos = $request->input('excluidos', null);
        $excluidos = $excluidos ? trim($excluidos, ',') : null;

        $jobId = DB::table('incentivo_jobs')->insertGetId([
            'mes' => $mes,
            'anio' => $anio,
            'excluidos' => $excluidos,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        return response()->json(['job_id' => $jobId, 'status' => 'pending']);
    }

    public function status($id)
    {
        $job = DB::table('incentivo_jobs')->where('id', $id)->first();
        return response()->json($job);
    }

    function list(Request $request)
    {
        $mes = (int)$request->input('mes');
        $anio = (int)$request->input('year');
        $excluidos = $request->input('excluidos', null);

        $q = DB::table('incentivo_resultados')
            ->where('mes', $mes)
            ->where('anio', $anio);

        if ($excluidos === null || $excluidos === '') {
            $q->whereNull('excluidos');
        } else {
            $q->where('excluidos', trim($excluidos, ','));
        }

        return $q->orderByDesc('meta_incremental')->get();
    }

    function save(Request $request)
    {
        ini_set('max_execution_time', 300); // 5 minutes
        ini_set('memory_limit', '512M');

        $mes = $request->input('mes');
        $anio = $request->input('year', date('Y'));
        $datos = $request->input('datos');
        // Insertar o traer id de incentivo_temporal_c
        $incentivoId = DB::table('incentivo_temporal_c')
            ->where('anio', $anio)->where('mes', $mes)->value('incentivo_id');
        if (!$incentivoId) {
            $incentivoId = DB::table('incentivo_temporal_c')->insertGetId([
                'anio' => $anio,
                'mes' => $mes
            ]);
        }

        $data = [];
        // Limpiar tabla 
        DB::table('incentivo_temporal')->where('incentivo_id', $incentivoId)->delete();

        foreach ($datos as $dato) {
            $data[] = [
                'incentivo_id' => $incentivoId,
                'agencia_id' => $dato['agencia_id'],
                'tipo_producto' => $dato['tipo_producto'],
                'sistema' => $dato['sistema'],
                'total_trimestre' => floatval(str_replace(',', '', $dato['total_trimestre'])),
                'promedio_mensual' => floatval(str_replace(',', '', $dato['promedio_mensual'])),
                'venta_base' => floatval(str_replace(',', '', $dato['venta_base'])),
                'venta_mes' => floatval(str_replace(',', '', $dato['total_mes'])),
                'nivel' => $dato['nivel'],
                'cumplimiento' => floatval(str_replace(',', '', $dato['cumplimiento'])),
                'meta_incremental' => floatval(str_replace(',', '', $dato['meta_incremental'])),
                'meta_plan' => floatval(str_replace(',', '', $dato['meta_plan'])),
            ];
        }

        foreach (array_chunk($data, 5000) as $chunk) {
            DB::table('incentivo_temporal')->insert($chunk);
        }

        return response()->json(['message' => 'Incentivos guardados exitosamente.']);
    }

    function listPlanAgencia(Request $request)
    {
        ini_set('max_execution_time', 300); // 5 minutes
        ini_set('memory_limit', '512M');
        $mes = $request->input('mes');
        $anio = $request->input('year', date('Y'));
        $incentivoId = DB::table('incentivo_temporal_c')
            ->where('anio', $anio)->where('mes', $mes)->value('incentivo_id');

        // Validar si existen datos en incentivo_temporal_c
        if ($incentivoId === null) {
            return response()->json(['message' => 'No hay datos registrados en el mes.'], 404);
        }

        $planAgencia = DB::select(
            "SELECT 
                it.agencia_id,
                it.tipo_producto,
                it.sistema,
                FORMAT(it.venta_mes, 2) AS venta_mes,
                FORMAT(it.meta_incremental, 2) AS venta_base,
                CASE
                    WHEN it.nivel IN (1,2,3) AND it.venta_mes >= it.meta_incremental THEN
                        FORMAT((it.venta_mes - it.meta_incremental), 2)
                    WHEN it.venta_mes >= it.meta_incremental THEN 
                        FORMAT((it.venta_mes - it.meta_incremental), 2)
                    ELSE
                        CONCAT(
                            'FALTA ',
                            FORMAT(((it.meta_incremental - it.venta_mes) / it.meta_incremental) * 100 , 2),
                            '%'
                        )
                END AS excedente,
                pa_agente.porcentaje AS porcentaje_agente,
                pa_coord.porcentaje AS porcentaje_coordinador,
                pa_admin.porcentaje AS porcentaje_administrativo,
                CASE
                    WHEN it.nivel IN (1,2,3) AND it.venta_mes >= it.meta_incremental THEN
                        FORMAT((it.venta_mes - it.meta_incremental) * pa_agente.porcentaje, 2)
                    WHEN it.venta_mes >= it.meta_incremental THEN 
                        FORMAT((it.venta_mes - it.venta_base) * pa_agente.porcentaje, 2)
                    ELSE ''
                END AS monto_agente,
                CASE
                    WHEN it.nivel IN (1,2,3) AND it.venta_mes >= it.meta_incremental THEN
                        FORMAT((it.venta_mes - it.meta_incremental) * pa_agente.porcentaje, 2)
                    WHEN it.venta_mes >= it.meta_incremental THEN 
                        FORMAT((it.venta_mes - it.venta_base) * pa_coord.porcentaje, 2)
                    ELSE ''
                END AS monto_coordinador,
                CASE
                    WHEN it.nivel IN (1,2,3) AND it.venta_mes >= it.meta_incremental THEN
                        FORMAT((it.venta_mes - it.meta_incremental) * pa_agente.porcentaje, 2)
                    WHEN it.venta_mes >= it.meta_incremental THEN 
                        FORMAT((it.venta_mes - it.venta_base) * pa_admin.porcentaje, 2)
                    ELSE ''
                END AS monto_administrativo,
                CASE
                    WHEN it.nivel IN (1,2,3) AND it.venta_mes >= it.meta_incremental THEN
                        FORMAT(
                            ((it.venta_mes - it.meta_incremental) * pa_agente.porcentaje) +
                            ((it.venta_mes - it.meta_incremental) * pa_coord.porcentaje) +
                            ((it.venta_mes - it.meta_incremental) * pa_admin.porcentaje)
                        , 2)
                    WHEN it.venta_mes >= it.meta_incremental THEN 
                        FORMAT(
                            ((it.venta_mes - it.venta_base) * pa_agente.porcentaje) +
                            ((it.venta_mes - it.venta_base) * pa_coord.porcentaje) +
                            ((it.venta_mes - it.venta_base) * pa_admin.porcentaje)
                        , 2)
                    ELSE ''
                END AS total_distribucion
            FROM incentivo_temporal it
            LEFT JOIN distribucion_porcentajes pa_agente
                ON pa_agente.departamento = 'Agente'
                AND pa_agente.tipo = it.tipo_producto
            LEFT JOIN distribucion_porcentajes pa_coord
                ON pa_coord.departamento = 'Coordinador'
                AND pa_coord.tipo = it.tipo_producto
            LEFT JOIN distribucion_porcentajes pa_admin
                ON pa_admin.departamento = 'Administrativo'
                AND pa_admin.tipo = it.tipo_producto
            INNER JOIN plan_agencia pa ON CAST(TRIM(it.agencia_id) AS UNSIGNED) = pa.agencia_id
            WHERE it.incentivo_id = $incentivoId AND it.venta_mes > 0;"
        );

        // FORMAT(it.venta_base, 2) AS venta_base,
        // CASE WHEN it.venta_mes >= it.meta_plan 
        //             THEN 'SI CUMPLE'
        //             ELSE 'NO CUMPLE'
        //         END AS condicion
        return response()->json($planAgencia);
    }

    function savePlanAgencia(Request $request)
    {
        ini_set('max_execution_time', 300); // 5 minutes
        ini_set('memory_limit', '512M');

        $mes = $request->input('mes');
        $anio = $request->input('year', date('Y'));
        $datos = $request->input('datos');
        // Insertar o traer id de incentivo_temporal_c
        $incentivoId = DB::table('incentivo_temporal_c')
            ->where('anio', $anio)->where('mes', $mes)->value('incentivo_id');
        if (!$incentivoId) {
            $incentivoId = DB::table('incentivo_temporal_c')->insertGetId([
                'anio' => $anio,
                'mes' => $mes
            ]);
        }

        $data = [];
        // Limpiar tabla 
        DB::table('plan_agencias_distribucion')->where('incentivo_id', $incentivoId)->delete();

        foreach ($datos as $dato) {
            $data[] = [
                'incentivo_id' => $incentivoId,
                'agencia_id' => $dato['agencia_id'],
                'tipo_producto' => $dato['tipo_producto'],
                'sistema' => $dato['sistema'],
                'venta_mes' => floatval(str_replace(',', '', $dato['venta_mes'])),
                'venta_base' => floatval(str_replace(',', '', $dato['venta_base'])),
                'excedente' => floatval(str_replace(',', '', $dato['excedente'])),
                'porcentaje_agente' => floatval(str_replace(',', '', $dato['porcentaje_agente'])),
                'porcentaje_coordinador' => floatval(str_replace(',', '', $dato['porcentaje_coordinador'])),
                'porcentaje_administrativo' => floatval(str_replace(',', '', $dato['porcentaje_administrativo'])),
                'monto_agente' => floatval(str_replace(',', '', $dato['monto_agente'])),
                'monto_coordinador' => floatval(str_replace(',', '', $dato['monto_coordinador'])),
                'monto_administrativo' => floatval(str_replace(',', '', $dato['monto_administrativo'])),
                'total_distribucion' => floatval(str_replace(',', '', $dato['total_distribucion'])),
            ];
        }

        foreach (array_chunk($data, 3000) as $chunk) {
            DB::table('plan_agencias_distribucion')->insert($chunk);
        }

        return response()->json(['message' => 'Plan Agencia guardado exitosamente.']);
    }

    function listEfectividad(Request $request)
    {
        ini_set('max_execution_time', 600); // 5 minutes
        ini_set('memory_limit', '1G');
        $mes = $request->input('mes');
        $anio = $request->input('year', date('Y'));
        $incentivoId = DB::table('incentivo_temporal_c')
            ->where('anio', $anio)->where('mes', $mes)->value('incentivo_id');

        // Validar si existen datos en incentivo_temporal_c
        if ($incentivoId === null) {
            return response()->json(['message' => 'No hay datos registrados en el mes.'], 404);
        }
        $excluidos = trim($request->input('excluidos', '')); // ejemplo: "7,8,9"

        $excluirBet = '';
        $excluirNet = '';

        if ($excluidos !== '') {
            $excluirBet = "AND FIND_IN_SET(producto_id, '$excluidos') = 0";
            $excluirNet = "AND FIND_IN_SET(n.producto_id, '$excluidos') = 0";
        }

        $data = DB::select(
            "SELECT
                it.agencia_id,
                it.sistema,
                it.tipo_producto,
                FORMAT(it.venta_mes, 2) AS venta_mes,
                -- BET
                IFNULL(bet.empleadoid, '') AS empleadoid_bet,
                IFNULL(bet.cedula, '') AS cedula_bet,
                IFNULL(FORMAT(bet.monto_cedula, 2), '') AS monto_bet_cedula,
                IFNULL(ROUND((bet.monto_cedula / it.venta_mes) * 100, 2), '') AS porc_bet,
                -- NET
                IFNULL(net.empleadoid, '') AS empleadoid_net,
                IFNULL(net.cedula, '') AS cedula_net,
                IFNULL(FORMAT(net.monto_cedula, 2), '') AS monto_net_cedula,
                IFNULL(ROUND((net.monto_cedula / it.venta_mes) * 100, 2), '') AS porc_net
            FROM incentivo_temporal it
            LEFT JOIN (
                SELECT agencia_id, vb.cedula, e.empleadoid, SUM(monto) AS monto_cedula, tipo, 'Lotobet' AS sistema
                FROM vt_usuarios_bet vb
                INNER JOIN empleados e ON vb.cedula = e.cedula
                WHERE MONTH(vb.fecha) = $mes AND YEAR(vb.fecha) = $anio AND vb.monto > 0 $excluirBet AND e.aplica_incentivo = 'SI'
                GROUP BY vb.agencia_id, vb.cedula, e.empleadoid, vb.tipo
            ) bet ON bet.agencia_id = it.agencia_id AND bet.tipo = it.tipo_producto AND it.sistema = bet.sistema
            LEFT JOIN (
                SELECT agencia_id, n.cedula, e.empleadoid, SUM(monto) AS monto_cedula, c.tipo, 'Lotonet' AS sistema
                FROM vt_usuarios_net n
                INNER JOIN empleados e ON n.cedula = e.cedula
                LEFT JOIN catalogo_juegos c ON CAST(n.producto_id AS SIGNED) = c.producto_id
                WHERE MONTH(n.fecha) = $mes AND YEAR(n.fecha) = $anio AND n.monto > 0 $excluirNet AND e.aplica_incentivo = 'SI'
                GROUP BY n.agencia_id, n.cedula, e.empleadoid, c.tipo
            ) net ON net.agencia_id = it.agencia_id AND net.tipo = it.tipo_producto AND it.sistema = net.sistema
            WHERE it.incentivo_id = $incentivoId AND it.tipo_producto IS NOT NULL
            ORDER BY it.agencia_id;"
        );
        return response()->json($data);
    }

    function saveEfectividad(Request $request)
    {
        ini_set('max_execution_time', 300); // 5 minutes
        ini_set('memory_limit', '512M');

        $mes = $request->input('mes');
        $anio = $request->input('year', date('Y'));
        $datos = $request->input('datos');
        // Insertar o traer id de incentivo_temporal_c
        $incentivoId = DB::table('incentivo_temporal_c')
            ->where('anio', $anio)->where('mes', $mes)->value('incentivo_id');
        if (!$incentivoId) {
            $incentivoId = DB::table('incentivo_temporal_c')->insertGetId([
                'anio' => $anio,
                'mes' => $mes
            ]);
        }

        $data = [];
        // Limpiar tabla 
        DB::table('efectividad_usuarios')->where('incentivo_id', $incentivoId)->delete();

        foreach ($datos as $dato) {
            $data[] = [
                'incentivo_id' => $incentivoId,
                'agencia_id' => $dato['agencia_id'],
                'tipo_producto' => $dato['tipo_producto'],
                'sistema' => $dato['sistema'],
                'venta_mes' => floatval(str_replace(',', '', $dato['venta_mes'])),
                'empleadoid_bet' => $dato['empleadoid_bet'],
                'cedula_bet' => $dato['cedula_bet'],
                'monto_cedula_bet' => floatval(str_replace(',', '', $dato['monto_bet_cedula'])),
                'porcentaje_cedula_bet' => floatval(str_replace(',', '', $dato['porc_bet'])),
                'empleadoid_net' => $dato['empleadoid_net'],
                'cedula_net' => $dato['cedula_net'],
                'monto_cedula_net' => floatval(str_replace(',', '', $dato['monto_net_cedula'])),
                'porcentaje_cedula_net' => floatval(str_replace(',', '', $dato['porc_net'])),
            ];
        }

        foreach (array_chunk($data, 5000) as $chunk) {
            DB::table('efectividad_usuarios')->insert($chunk);
        }

        return response()->json(['message' => 'Efectividad guardada exitosamente.']);
    }

    function listPagoAgente(Request $request)
    {
        ini_set('max_execution_time', 600); // 5 minutes
        ini_set('memory_limit', '1G');
        $sistema = $request->input('sistema');
        $mes = $request->input('mes');
        $anio = $request->input('year', date('Y'));
        $incentivoId = DB::table('incentivo_temporal_c')
            ->where('anio', $anio)->where('mes', $mes)->value('incentivo_id');

        // Validar si existen datos en incentivo_temporal_c
        if ($incentivoId === null) {
            return response()->json(['message' => 'No hay datos registrados en el mes.'], 404);
        }

        $data = DB::select(
            "SELECT
                eu.agencia_id,
                eu.tipo_producto,
                CASE WHEN '$sistema' = 'Lotobet'
                    THEN eu.cedula_bet
                    ELSE eu.cedula_net
                END AS cedula,
                CASE WHEN '$sistema' = 'Lotobet'
                    THEN FORMAT(eu.porcentaje_cedula_bet, 2)
                    ELSE FORMAT(eu.porcentaje_cedula_net, 2)
                END AS porcentaje_cedula,
                FORMAT(pad.monto_agente, 2) AS monto_agente,
                CASE WHEN '$sistema' = 'Lotobet'
                    THEN ROUND((eu.porcentaje_cedula_bet / 100) * pad.monto_agente, 2)
                    ELSE ROUND((eu.porcentaje_cedula_net / 100) * pad.monto_agente, 2)
                END AS monto_incentivo,
                CASE WHEN '$sistema' = 'Lotobet'
                    THEN eu.empleadoid_bet
                    ELSE eu.empleadoid_net
                END AS empleadoid
            FROM efectividad_usuarios eu
            INNER JOIN plan_agencias_distribucion pad ON eu.incentivo_id = pad.incentivo_id
                AND eu.agencia_id = pad.agencia_id
                AND eu.tipo_producto = pad.tipo_producto
            INNER JOIN incentivo_temporal it on eu.incentivo_id = it.incentivo_id
                AND eu.agencia_id = it.agencia_id
                AND eu.tipo_producto = it.tipo_producto
            WHERE eu.incentivo_id = $incentivoId AND eu.sistema = '$sistema'
                AND it.venta_mes >= it.venta_base
                AND it.venta_mes > 0;"
        );
        return response()->json($data);
    }

    function savePagoAgente(Request $request)
    {
        ini_set('max_execution_time', 300); // 5 minutes
        ini_set('memory_limit', '512M');
        $sistema = $request->input('sistema');
        $mes = $request->input('mes');
        $anio = $request->input('year', date('Y'));
        $datos = $request->input('datos');
        // Insertar o traer id de incentivo_temporal_c
        $incentivoId = DB::table('incentivo_temporal_c')
            ->where('anio', $anio)->where('mes', $mes)->value('incentivo_id');
        if (!$incentivoId) {
            $incentivoId = DB::table('incentivo_temporal_c')->insertGetId([
                'anio' => $anio,
                'mes' => $mes
            ]);
        }

        $data = [];
        // Limpiar tabla 
        DB::table('pago_incentivos')->where('incentivo_id', $incentivoId)->delete();

        foreach ($datos as $dato) {
            $data[] = [
                'incentivo_id' => $incentivoId,
                'agencia_id' => $dato['agencia_id'],
                'tipo_producto' => $dato['tipo_producto'],
                'sistema' => $sistema,
                'empleadoid' => $dato['empleadoid'],
                'cedula' => $dato['cedula'],
                'porcentaje_cedula' => floatval(str_replace(',', '', $dato['porcentaje_cedula'])),
                'monto_agente' => floatval(str_replace(',', '', $dato['monto_agente'])),
                'monto_incentivo' => floatval(str_replace(',', '', $dato['monto_incentivo'])),
            ];
        }

        foreach (array_chunk($data, 5000) as $chunk) {
            DB::table('pago_incentivos')->insert($chunk);
        }

        return response()->json(['message' => 'Pago Incentivos guardado exitosamente.']);
    }

    function listPagoCoordinador(Request $request)
    {
        ini_set('max_execution_time', 600); // 5 minutes
        ini_set('memory_limit', '1G');
        $sistema = $request->input('sistema');
        $mes = $request->input('mes');
        $anio = $request->input('year', date('Y'));
        $incentivoId = DB::table('incentivo_temporal_c')
            ->where('anio', $anio)->where('mes', $mes)->value('incentivo_id');

        // Validar si existen datos en incentivo_temporal_c
        if ($incentivoId === null) {
            return response()->json(['message' => 'No hay datos registrados en el mes.'], 404);
        }

        $data = DB::select(
            "SELECT 
                companyid,
                company,
                empleadoid,
                cedula,
                nombres,
                apellidos,
                FORMAT(SUM(total), 2) AS total_empleado
            FROM (
                SELECT 
                    e.companyid,
                    CASE WHEN e.companyid = 168 THEN 'Joselito' ELSE 'Negosur' END AS company,
                    c.empleado_id AS empleadoid,
                    e.cedula,
                    e.nombres,
                    e.apellidos,
                    pad_tot.total_agencia AS total,
                    pad_tot.porcentaje_coordinador AS porcentaje
                FROM coordinador c
                INNER JOIN (
                    SELECT agencia_id, SUM(monto_coordinador) AS total_agencia, porcentaje_coordinador
                    FROM plan_agencias_distribucion
                    WHERE incentivo_id = ? AND excedente > 0 AND sistema = ?
                    GROUP BY agencia_id, porcentaje_coordinador
                ) pad_tot ON pad_tot.agencia_id = c.agencia_id
                INNER JOIN empleados e ON c.empleado_id = e.empleadoid 
                    AND e.companyid IN (168, 169) AND e.fechasalida IS NULL
            ) AS t
            GROUP BY companyid, company, empleadoid, cedula, nombres, apellidos;",
            [$incentivoId, $sistema]
        );
        return response()->json($data);
    }

    function savePagoCoordinador(Request $request)
    {
        ini_set('max_execution_time', 300); // 5 minutes
        ini_set('memory_limit', '512M');
        $sistema = $request->input('sistema');
        $mes = $request->input('mes');
        $anio = $request->input('year', date('Y'));
        $datos = $request->input('datos');
        // Insertar o traer id de incentivo_temporal_c
        $incentivoId = DB::table('incentivo_temporal_c')
            ->where('anio', $anio)->where('mes', $mes)->value('incentivo_id');
        if (!$incentivoId) {
            $incentivoId = DB::table('incentivo_temporal_c')->insertGetId([
                'anio' => $anio,
                'mes' => $mes
            ]);
        }

        $data = [];
        // Limpiar tabla 
        DB::table('pago_incentivos_coordinador')->where('incentivo_id', $incentivoId)->delete();

        foreach ($datos as $dato) {
            $data[] = [
                'incentivo_id' => $incentivoId,
                'empleadoid' => $dato['empleadoid'],
                'companyid' => $dato['companyid'],
                'cedula' => $dato['cedula'],
                'porcentaje' => floatval(str_replace(',', '', $dato['porcentaje'])),
                'total' => floatval(str_replace(',', '', $dato['total_empleado'])),
            ];
        }

        foreach (array_chunk($data, 5000) as $chunk) {
            DB::table('pago_incentivos_coordinador')->insert($chunk);
        }

        return response()->json(['message' => 'Pago Incentivos guardado exitosamente.']);
    }

    function listPagoCoordinadorDetalle(Request $request)
    {
        ini_set('max_execution_time', 600); // 5 minutes
        ini_set('memory_limit', '1G');

        $cedula = $request->input('cedula');
        $tipo_producto = trim($request->input('tipo_producto', ''));
        $sistema = $request->input('sistema');
        $mes = $request->input('mes');
        $anio = $request->input('year', date('Y'));

        $incentivoId = DB::table('incentivo_temporal_c')
            ->where('anio', $anio)->where('mes', $mes)->value('incentivo_id');

        if ($incentivoId === null) {
            return response()->json(['message' => 'No hay datos registrados en el mes.'], 404);
        }

        $sql = "SELECT 
                    agencia_id, 
                    tipo_producto, 
                    sistema, 
                    FORMAT(venta_mes, 2) AS venta_mes,
                    FORMAT(venta_base, 2) AS venta_base,
                    FORMAT(excedente, 2) AS excedente,
                    FORMAT(porcentaje_coordinador, 3) AS porcentaje_coordinador,
                    FORMAT(monto_coordinador, 2) AS monto_coordinador
                FROM plan_agencias_distribucion
                WHERE incentivo_id = ?
                    AND agencia_id IN (
                        SELECT agencia_id 
                        FROM coordinador c
                        INNER JOIN empleados e ON c.empleado_id = e.empleadoid
                        WHERE e.cedula = ?
                    )
                    AND excedente > 0";

        $bindings = [$incentivoId, $cedula];

        if (!empty($sistema)) {
            $sql .= " AND sistema = ?";
            $bindings[] = $sistema;
        }

        if ($tipo_producto !== '') {
            $sql .= " AND tipo_producto = ?";
            $bindings[] = $tipo_producto;
        }

        $sql .= " ORDER BY agencia_id, tipo_producto, sistema";

        $data = DB::select($sql, $bindings);
        return response()->json($data);
    }

    function listPagoAdmin(Request $request)
    {
        ini_set('max_execution_time', 600); // 5 minutes
        ini_set('memory_limit', '1G');
        $sistema = $request->input('sistema');
        $mes = $request->input('mes');
        $anio = $request->input('year', date('Y'));
        $incentivoId = DB::table('incentivo_temporal_c')
            ->where('anio', $anio)->where('mes', $mes)->value('incentivo_id');

        // Validar si existen datos en incentivo_temporal_c
        if ($incentivoId === null) {
            return response()->json(['message' => 'No hay datos registrados en el mes.'], 404);
        }

        $data = DB::select(
            "WITH totales_producto AS (
                SELECT SUM(monto_administrativo) AS total, tipo_producto
                FROM plan_agencias_distribucion
                WHERE incentivo_id = $incentivoId AND excedente > 0
                GROUP BY tipo_producto
            )
            SELECT 
                CASE WHEN emp.companyid = '168' THEN 'Joselito' ELSE 'Negosur' END AS empresa,
                emp.cedula,
                emp.companyid,
                emp.empleadoid,
                emp.nombres,
                emp.apellidos,
                FORMAT(e.porcentaje, 2) AS porcentaje,
                -- Tradicional
                FORMAT(ROUND(
                    (e.porcentaje / 100) * 
                    IFNULL(MAX(CASE WHEN t.tipo_producto = 'Tradicional' THEN t.total END), 0),
                    2
                ), 2) AS Tradicional,
                -- No Tradicional
                FORMAT(ROUND(
                    (e.porcentaje / 100) * 
                    IFNULL(MAX(CASE WHEN t.tipo_producto = 'No Tradicional' THEN t.total END), 0),
                    2
                ), 2) AS No_Tradicional,
                -- Recargas
                FORMAT(ROUND(
                    (e.porcentaje / 100) * 
                    IFNULL(MAX(CASE WHEN t.tipo_producto = 'Recarga' THEN t.total END), 0),
                    2
                ), 2) AS Recargas,
                -- Paquetico
                FORMAT(ROUND(
                    (e.porcentaje / 100) * 
                    IFNULL(MAX(CASE WHEN t.tipo_producto = 'Paquetico' THEN t.total END), 0),
                    2
                ), 2) AS Paquetico,
                -- Total a cobrar (suma de las 4 columnas anteriores)
                FORMAT(ROUND(
                    (e.porcentaje / 100) * 
                    (
                        IFNULL(MAX(CASE WHEN t.tipo_producto = 'Tradicional'     THEN t.total END), 0) +
                        IFNULL(MAX(CASE WHEN t.tipo_producto = 'No Tradicional'  THEN t.total END), 0) +
                        IFNULL(MAX(CASE WHEN t.tipo_producto = 'Recarga'        THEN t.total END), 0) +
                        IFNULL(MAX(CASE WHEN t.tipo_producto = 'Paquetico'       THEN t.total END), 0)
                    ),
                    2
                ), 2) AS Total_a_cobrar
            FROM porcentaje_administrativo e
            INNER JOIN empleados emp ON e.empleado_id = emp.empleadoid
            CROSS JOIN totales_producto t
            WHERE emp.aplica_incentivo = 'SI' AND emp.tipo_empleado_incentivo = 3
            GROUP BY emp.companyid, emp.cedula, emp.empleadoid,
                emp.nombres, emp.apellidos, e.porcentaje
            ORDER BY Total_a_cobrar DESC;"
        );
        return response()->json($data);
    }

    function savePagoAdmin(Request $request)
    {
        ini_set('max_execution_time', 300); // 5 minutes
        ini_set('memory_limit', '512M');
        $sistema = $request->input('sistema');
        $mes = $request->input('mes');
        $anio = $request->input('year', date('Y'));
        $datos = $request->input('datos');
        // Insertar o traer id de incentivo_temporal_c
        $incentivoId = DB::table('incentivo_temporal_c')
            ->where('anio', $anio)->where('mes', $mes)->value('incentivo_id');

        if (!$incentivoId) {
            $incentivoId = DB::table('incentivo_temporal_c')->insertGetId([
                'anio' => $anio,
                'mes' => $mes
            ]);
        }

        $data = [];
        // Limpiar tabla 
        DB::table('pago_incentivos_admin')->where('incentivo_id', $incentivoId)->delete();

        foreach ($datos as $dato) {
            $data[] = [
                'incentivo_id' => $incentivoId,
                'empleadoid' => $dato['empleadoid'],
                'companyid' => $dato['companyid'],
                'cedula' => $dato['cedula'],
                'tradicional' => floatval(str_replace(',', '', $dato['Tradicional'])),
                'no_tradicional' => floatval(str_replace(',', '', $dato['No_Tradicional'])),
                'recarga' => floatval(str_replace(',', '', $dato['Recargas'])),
                'paquetico' => floatval(str_replace(',', '', $dato['Paquetico'])),
                'total' => floatval(str_replace(',', '', $dato['Total_a_cobrar'])),
            ];
        }

        foreach (array_chunk($data, 5000) as $chunk) {
            DB::table('pago_incentivos_admin')->insert($chunk);
        }

        return response()->json(['message' => 'Pago Incentivos guardado exitosamente.']);
    }

    function listPagoAdminDetalle(Request $request)
    {
        ini_set('max_execution_time', 600); // 5 minutes
        ini_set('memory_limit', '1G');

        $cedula = $request->input('cedula');
        $companyid = $request->input('companyid');
        $mes = $request->input('mes');
        $anio = $request->input('year', date('Y'));

        $incentivoId = DB::table('incentivo_temporal_c')
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->value('incentivo_id');

        if ($incentivoId === null) {
            return response()->json(['message' => 'No hay datos registrados en el mes.'], 404);
        }

        $data = DB::select(
            "WITH totales_producto AS (
                SELECT 
                    MAX(CASE WHEN tipo_producto = 'Tradicional' THEN total END) AS total_tradicional,
                    MAX(CASE WHEN tipo_producto = 'No Tradicional' THEN total END) AS total_no_tradicional,
                    MAX(CASE WHEN tipo_producto = 'Recarga' THEN total END) AS total_recarga,
                    MAX(CASE WHEN tipo_producto = 'Paquetico' THEN total END) AS total_paquetico
                FROM (
                    SELECT tipo_producto, SUM(monto_administrativo) AS total
                    FROM plan_agencias_distribucion
                    WHERE incentivo_id = $incentivoId AND excedente > 0
                    GROUP BY tipo_producto
                ) x
            ),
            empleado_info AS (
                SELECT 
                    e.porcentaje
                FROM porcentaje_administrativo e
                INNER JOIN empleados emp ON e.empleado_id = emp.empleadoid
                WHERE emp.cedula = '$cedula' 
                AND emp.companyid = $companyid
                LIMIT 1
            ),
            detalle AS (
                SELECT
                    'Tradicional' AS tipo_producto,
                    tp.total_tradicional AS total_tipo_producto,
                    ei.porcentaje,
                    (ei.porcentaje / 100 * tp.total_tradicional) AS total_a_pagar
                FROM totales_producto tp, empleado_info ei

                UNION ALL

                SELECT
                    'No Tradicional',
                    tp.total_no_tradicional,
                    ei.porcentaje,
                    (ei.porcentaje / 100 * tp.total_no_tradicional)
                FROM totales_producto tp, empleado_info ei

                UNION ALL

                SELECT
                    'Recarga',
                    tp.total_recarga,
                    ei.porcentaje,
                    (ei.porcentaje / 100 * tp.total_recarga)
                FROM totales_producto tp, empleado_info ei

                UNION ALL

                SELECT
                    'Paquetico',
                    tp.total_paquetico,
                    ei.porcentaje,
                    (ei.porcentaje / 100 * tp.total_paquetico)
                FROM totales_producto tp, empleado_info ei
            )

            -- 🔹 SALIDA FINAL
            SELECT
                tipo_producto,
                FORMAT(total_tipo_producto, 2) AS total_tipo_producto,
                FORMAT(porcentaje, 2) AS porcentaje,
                FORMAT(total_a_pagar, 2) AS total_a_pagar
            FROM detalle

            UNION ALL

            SELECT
                'TOTAL GENERAL',
                FORMAT(SUM(total_tipo_producto), 2),
                FORMAT(MAX(porcentaje), 2),
                FORMAT(SUM(total_a_pagar), 2)
            FROM detalle;"
        );

        return response()->json($data);
    }

    public function reportePagos()
    {
        return view('incentivos.reporte-pagos');
    }

    public function reportePagoIncentivos(Request $request)
    {
        ini_set('max_execution_time', 600); // 10 minutes
        ini_set('memory_limit', '1G');

        $empresaId = '%';
        $empresa = $request->input('empresa');
        if (!empty($empresa)) {
            $empresaId = $empresa;
        }

        $incentivoId = '%';
        $mes = $request->input('mes');
        if (!empty($mes)) {
            $anio = $request->input('year', date('Y'));

            $incentivoId = DB::table('incentivo_temporal_c')
                ->where('anio', $anio)
                ->where('mes', $mes)
                ->value('incentivo_id');

            if ($incentivoId === null) {
                return response()->json(['message' => 'No hay datos registrados en el mes.'], 404);
            }
        }

        $tipoId = '%';
        $tipo = $request->input('tipo');
        if (!empty($tipo)) {
            $tipoId = $tipo;
        }

        $data = DB::select(
            "SELECT 
                CASE 
                    WHEN e.companyid = 168 THEN 'Joselito'
                    WHEN e.companyid = 169 THEN 'Negosur'
                    ELSE 'Otra Empresa'
                END AS company,
                CASE
                    WHEN e.tipo_empleado_incentivo = '1' THEN 'Agente de venta'
                    WHEN e.tipo_empleado_incentivo = '2' THEN 'Coordinador'
                    WHEN e.tipo_empleado_incentivo = '3' THEN 'Administrativo'
                    WHEN e.tipo_empleado_incentivo = '4' THEN 'Operador'
                END AS tipo,
                e.empleadoid AS empleado_id,
                CONCAT(e.nombres, ' ', e.apellidos) AS nombres,
                e.cedula,
                e.ctabanco AS cuenta,
                FORMAT(t.total_monto, 2) AS monto
            FROM (
                SELECT 
                    cedula,
                    empleadoid,
                    SUM(monto_a_pagar) AS total_monto,
                    incentivo_id
                FROM (
                    -- Agentes
                    SELECT 
                        cedula,
                        empleadoid,
                        monto_incentivo AS monto_a_pagar,
                        incentivo_id
                    FROM pago_incentivos
                    UNION ALL
                    -- Administrativos
                    SELECT 
                        cedula,
                        empleadoid,
                        total AS monto_a_pagar,
                        incentivo_id
                    FROM pago_incentivos_admin
                    UNION ALL
                    -- Coordinadores
                    SELECT 
                        cedula,
                        empleadoid,
                        total AS monto_a_pagar,
                        incentivo_id
                    FROM pago_incentivos_coordinador
                ) pagos
                GROUP BY cedula, empleadoid, incentivo_id
            ) t
            LEFT JOIN empleados e ON CAST(t.cedula AS SIGNED) = CAST(e.cedula AS SIGNED)
                AND e.empleadoid = t.empleadoid
            WHERE t.incentivo_id LIKE '$incentivoId'
                AND t.total_monto > 0
                AND e.companyid LIKE '$empresaId'
                AND e.tipo_empleado_incentivo LIKE '$tipoId'
                AND e.fechasalida IS NULL;"
        );

        return response()->json($data);
    }
}
