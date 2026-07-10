<?php

namespace App\Http\Controllers;

use App\Imports\AgenciasActualizacionMasivaImport;
use App\Models\CoordinadorOperador;
use App\Models\IncentivoAdministrativo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class IncentivosController extends Controller
{
    private const GRUPOS_ADMINISTRATIVOS_V5 = [
        '1. Gtes. Y Encarg.',
        '2. Monitoreo',
        '4. Operadores',
        '5. Servs. Tecnicos',
        '6. Seguridad',
    ];

    private const GRUPOS_ADMINISTRATIVOS_FIJOS_V5 = [
        '4. Operadores',
        '5. Servs. Tecnicos',
        '6. Seguridad',
    ];

    public function index()
    {
        $productosExcluidos = DB::table('catalogo_juegos')
            ->whereIn('producto_id', [538, 539])
            ->get();

        return view('incentivos.index', [
            'productosExcluidos' => $productosExcluidos
        ]);
    }

    public function procesar()
    {
        $productosExcluidos = DB::table('catalogo_juegos')
            ->whereIn('producto_id', [538, 539])
            ->get();

        return view('incentivos.procesar', [
            'productosExcluidos' => $productosExcluidos
        ]);
    }

    function list(Request $request)
    {
        ini_set('max_execution_time', 300); // 5 minutes
        ini_set('memory_limit', '512M');
        $mes = $request->input('mes');
        $excluidos = $request->input('excluidos', '');
        $year = $request->input('year', '');
        $incentivos = DB::select('CALL CalculoIncentivo(?, ?, ?)', [$mes,  $year, $excluidos]);
        return response()->json($incentivos);
    }

    function save(Request $request)
    {
        ini_set('max_execution_time', 300); // 5 minutes
        ini_set('memory_limit', '512M');

        $mes = $request->input('mes');
        $anio = $request->input('year', date('Y'));
        $datos = $request->input('datos');
        $reset = $request->boolean('reset', true);
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
        // Limpiar tabla solo si es el primer lote
        if ($reset) {
            DB::table('incentivo_temporal')->where('incentivo_id', $incentivoId)->delete();
        }

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
            return response()->json(['message' => 'No hay datos registrados en el mes.']);
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
            INNER JOIN agencias a
                ON CAST(TRIM(it.agencia_id) AS UNSIGNED) = CAST(a.terminal AS UNSIGNED)
                AND a.aplica_incentivo = 1
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
        $reset = $request->boolean('reset', true);
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
        // Limpiar tabla solo si es el primer lote
        if ($reset) {
            DB::table('plan_agencias_distribucion')->where('incentivo_id', $incentivoId)->delete();
        }

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
            return response()->json(['message' => 'No hay datos registrados en el mes.']);
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
            INNER JOIN agencias a
                ON CAST(TRIM(it.agencia_id) AS UNSIGNED) = CAST(a.terminal AS UNSIGNED)
                AND a.aplica_incentivo = 1
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
                AND it.venta_mes > 0
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
        $reset = $request->boolean('reset', true);
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
        // Limpiar tabla solo si es el primer lote
        if ($reset) {
            DB::table('efectividad_usuarios')->where('incentivo_id', $incentivoId)->delete();
        }

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
            return response()->json(['message' => 'No hay datos registrados en el mes.']);
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
                AND CAST(eu.agencia_id AS UNSIGNED) = CAST(pad.agencia_id AS UNSIGNED)
                AND eu.tipo_producto = pad.tipo_producto
            INNER JOIN incentivo_temporal it on eu.incentivo_id = it.incentivo_id
                AND CAST(eu.agencia_id AS UNSIGNED) = CAST(it.agencia_id AS UNSIGNED)
                AND eu.tipo_producto = it.tipo_producto
            INNER JOIN agencias a ON CAST(it.agencia_id AS UNSIGNED) = CAST(a.terminal AS UNSIGNED)
                AND a.aplica_incentivo = 1
            WHERE eu.incentivo_id = $incentivoId AND eu.sistema = '$sistema'
                AND it.venta_mes >= it.venta_base
                AND pad.monto_agente > 0
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
        $reset = $request->boolean('reset', true);
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
        // Limpiar tabla solo si es el primer lote
        if ($reset) {
            DB::table('pago_incentivos')
                ->where('incentivo_id', $incentivoId)
                ->where('sistema', $sistema)
                ->delete();
        }

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
            return response()->json(['message' => 'No hay datos registrados en el mes.']);
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
                    SELECT pad.agencia_id, SUM(pad.monto_coordinador) AS total_agencia, pad.porcentaje_coordinador
                    FROM plan_agencias_distribucion pad
                    INNER JOIN agencias a ON CAST(TRIM(pad.agencia_id) AS UNSIGNED) = CAST(a.terminal AS UNSIGNED)
                        AND a.aplica_incentivo = 1
                    WHERE pad.incentivo_id = ? AND pad.excedente > 0 AND pad.sistema = ?
                    GROUP BY pad.agencia_id, pad.porcentaje_coordinador
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
        $reset = $request->boolean('reset', true);
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
        // Limpiar tabla solo si es el primer lote
        if ($reset) {
            DB::table('pago_incentivos_coordinador')->where('incentivo_id', $incentivoId)->delete();
        }

        foreach ($datos as $dato) {
            $data[] = [
                'incentivo_id' => $incentivoId,
                'empleadoid' => $dato['empleadoid'],
                'companyid' => $dato['companyid'],
                'cedula' => $dato['cedula'],
                'porcentaje' => 0,
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
            return response()->json(['message' => 'No hay datos registrados en el mes.']);
        }

        $sql = "SELECT 
                    agencia_id, 
                    tipo_producto, 
                    pad.sistema, 
                    FORMAT(venta_mes, 2) AS venta_mes,
                    FORMAT(venta_base, 2) AS venta_base,
                    FORMAT(excedente, 2) AS excedente,
                    FORMAT(porcentaje_coordinador, 3) AS porcentaje_coordinador,
                    FORMAT(monto_coordinador, 2) AS monto_coordinador
                FROM plan_agencias_distribucion pad
                INNER JOIN agencias a
                    ON CAST(TRIM(pad.agencia_id) AS UNSIGNED) = CAST(a.terminal AS UNSIGNED)
                    AND a.aplica_incentivo = 1
                WHERE pad.incentivo_id = ?
                    AND pad.agencia_id IN (
                        SELECT agencia_id 
                        FROM coordinador c
                        INNER JOIN empleados e ON c.empleado_id = e.empleadoid
                        WHERE e.cedula = ?
                    )
                    AND excedente > 0";

        $bindings = [$incentivoId, $cedula];

        if (!empty($sistema)) {
            $sql .= " AND pad.sistema = ?";
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
            return response()->json(['message' => 'No hay datos registrados en el mes.']);
        }

        $data = DB::select(
            "WITH totales_producto AS (
                SELECT SUM(pad.monto_administrativo) AS total, pad.tipo_producto
                FROM plan_agencias_distribucion pad
                INNER JOIN agencias a ON CAST(TRIM(pad.agencia_id) AS UNSIGNED) = CAST(a.terminal AS UNSIGNED)
                    AND a.aplica_incentivo = 1
                WHERE pad.incentivo_id = $incentivoId AND pad.excedente > 0
                GROUP BY pad.tipo_producto
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
        $reset = $request->boolean('reset', true);
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
        // Limpiar tabla solo si es el primer lote
        if ($reset) {
            DB::table('pago_incentivos_admin')->where('incentivo_id', $incentivoId)->delete();
        }

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
            return response()->json(['message' => 'No hay datos registrados en el mes.']);
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
                    FROM plan_agencias_distribucion pad
                    INNER JOIN agencias a
                        ON CAST(TRIM(pad.agencia_id) AS UNSIGNED) = CAST(a.terminal AS UNSIGNED)
                        AND a.aplica_incentivo = 1
                    WHERE pad.incentivo_id = $incentivoId AND pad.excedente > 0
                    GROUP BY pad.tipo_producto
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

    public function reporteNuevoIncentivoView()
    {
        return view('incentivos.reporte-nuevo-incentivo');
    }

    public function reporteNuevoIncentivo(Request $request)
    {
        ini_set('max_execution_time', 600);
        ini_set('memory_limit', '1G');

        $request->validate([
            'fecha_ini' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_ini',
            'sistema' => 'nullable|in:Todos,Lotobet,Lotonet',
            'minimo_agencia' => 'nullable|numeric|min:0',
            'min_dias_venta' => 'nullable|integer|min:1',
            'filtro_cumplimiento' => 'nullable|in:todos,cumplidos,no_cumplidos',
            'pct_1' => 'nullable|numeric|min:0',
            'pct_2' => 'nullable|numeric|min:0',
            'pct_3' => 'nullable|numeric|min:0',
            'pct_4' => 'nullable|numeric|min:0',
        ]);

        $fechaIniSeleccionada = Carbon::parse($request->input('fecha_ini'))->toDateString();
        $fechaFinSeleccionada = Carbon::parse($request->input('fecha_fin'))->toDateString();

        // El "último mes" se define como el mes ANTERIOR completo según la fecha fin seleccionada.
        // Ejemplo: si fecha_fin está en febrero, se evalúa enero completo.
        $mesAnterior = Carbon::parse($fechaFinSeleccionada)->subMonthNoOverflow();
        $evalIni = $mesAnterior->copy()->startOfMonth()->toDateString();
        $evalFin = $mesAnterior->copy()->endOfMonth()->toDateString();

        $sistema = $request->input('sistema', 'Todos');
        $minimoAgencia = (float) $request->input('minimo_agencia', 80000);
        $minDiasVenta = (int) $request->input('min_dias_venta', 10);
        $filtroCumplimiento = $request->input('filtro_cumplimiento', 'todos');

        // Porcentaje real aplicado sobre ventas del mes actual (1 => 1%)
        $pct1 = (float) $request->input('pct_1', 1);
        $pct2 = (float) $request->input('pct_2', 2);
        $pct3 = (float) $request->input('pct_3', 3);
        $pct4 = (float) $request->input('pct_4', 4);

        $buildBaseQuery = function (string $desde, string $hasta) use ($sistema) {
            $betQuery = DB::table('vt_usuarios_bet')
                ->selectRaw("cedula, monto, fecha, 'Lotobet' as sistema")
                ->whereBetween('fecha', [$desde, $hasta]);

            $netQuery = DB::table('vt_usuarios_net')
                ->selectRaw("cedula, monto, fecha, 'Lotonet' as sistema")
                ->whereBetween('fecha', [$desde, $hasta]);

            if ($sistema === 'Lotobet') {
                return $betQuery;
            }

            if ($sistema === 'Lotonet') {
                return $netQuery;
            }

            return $betQuery->unionAll($netQuery);
        };

        $rowsUltimoMes = DB::query()
            ->fromSub($buildBaseQuery($evalIni, $evalFin), 'y')
            ->selectRaw('y.cedula, SUM(y.monto) AS ventas_ultimo_mes, COUNT(DISTINCT DATE(y.fecha)) AS dias_ventas_ultimo_mes')
            ->whereNotNull('y.cedula')
            ->where('y.cedula', '<>', '')
            ->groupBy('y.cedula')
            ->get();

        $rowsMesActual = DB::query()
            ->fromSub($buildBaseQuery($fechaIniSeleccionada, $fechaFinSeleccionada), 'z')
            ->selectRaw('z.cedula, SUM(z.monto) AS ventas_mes_actual, COUNT(DISTINCT DATE(z.fecha)) AS dias_ventas_mes_actual')
            ->whereNotNull('z.cedula')
            ->where('z.cedula', '<>', '')
            ->groupBy('z.cedula')
            ->get();

        $ultimoMesByCedula = $rowsUltimoMes->keyBy('cedula');
        $mesActualByCedula = $rowsMesActual->keyBy('cedula');
        $cedulas = $ultimoMesByCedula->keys()->merge($mesActualByCedula->keys())->unique()->values();

        $rawData = $cedulas->map(function ($cedula) use ($ultimoMesByCedula, $mesActualByCedula, $minimoAgencia, $minDiasVenta, $pct1, $pct2, $pct3, $pct4) {
            $rowUltimoMes = $ultimoMesByCedula->get($cedula);
            $rowMesActual = $mesActualByCedula->get($cedula);

            $ventas = $rowUltimoMes ? (float) $rowUltimoMes->ventas_ultimo_mes : 0;
            $ventasMesActual = $rowMesActual ? (float) $rowMesActual->ventas_mes_actual : 0;
            $diasMesActual = $rowMesActual ? (int) $rowMesActual->dias_ventas_mes_actual : 0;

            // Cumplimiento: mínimo vendido y mínimo de días en el mes actual (rango filtrado)
            $cumple = $ventasMesActual >= $minimoAgencia && $diasMesActual >= $minDiasVenta;

            $pct = 0.00;
            $factor = 0.00;

            if ($cumple) {
                if ($ventasMesActual < 100000) {
                    $pct = $pct1;
                } elseif ($ventasMesActual < 150000) {
                    $pct = $pct2;
                } elseif ($ventasMesActual < 200000) {
                    $pct = $pct3;
                } else {
                    $pct = $pct4;
                }

                $factor = $pct / 100;
            }

            return [
                'cedula' => $cedula,
                'ventas_num' => $ventas,
                'ventas_mes_actual_num' => $ventasMesActual,
                'dias_ventas_mes_actual' => $diasMesActual,
                'cumple_bool' => $cumple,
                'pct_num' => $pct,
                'nuevo_incentivo_num' => $ventasMesActual * $factor,
            ];
        })->sortByDesc('ventas_num')->values();

        if ($filtroCumplimiento === 'cumplidos') {
            $rawData = $rawData->where('cumple_bool', true)->values();
        } elseif ($filtroCumplimiento === 'no_cumplidos') {
            $rawData = $rawData->where('cumple_bool', false)->values();
        }

        // Total vendido debe reflejar la columna "Ventas Mes Actual"
        $totalVendido = (float) $rawData->sum('ventas_mes_actual_num');
        $totalIncentivo = (float) $rawData->sum('nuevo_incentivo_num');

        $data = $rawData->map(function ($row) use ($minimoAgencia) {
            $pctTexto = $row['pct_num'] > 0
                ? rtrim(rtrim(number_format($row['pct_num'], 2, '.', ''), '0'), '.') . '%'
                : '0%';

            return [
                'cedula' => $row['cedula'],
                'ventas_ultimo_mes' => number_format($row['ventas_num'], 2, '.', ','),
                'ventas_mes_actual' => number_format($row['ventas_mes_actual_num'], 2, '.', ','),
                'dias_ventas_mes_actual' => $row['dias_ventas_mes_actual'],
                'minimo_agencia' => number_format($minimoAgencia, 2, '.', ','),
                'cumple_minimo' => $row['cumple_bool'] ? 'SI' : 'NO',
                'pct_comision' => $pctTexto,
                'nuevo_incentivo' => number_format($row['nuevo_incentivo_num'], 2, '.', ','),
            ];
        })->values();

        return response()->json([
            'meta' => [
                'sistema' => $sistema,
                'fecha_ini' => $request->input('fecha_ini'),
                'fecha_fin' => $request->input('fecha_fin'),
                'eval_ini' => $evalIni,
                'eval_fin' => $evalFin,
                'minimo_agencia' => $minimoAgencia,
                'min_dias_venta' => $minDiasVenta,
                'filtro_cumplimiento' => $filtroCumplimiento,
                'pct_1' => $pct1,
                'pct_2' => $pct2,
                'pct_3' => $pct3,
                'pct_4' => $pct4,
                'total_vendido' => $totalVendido,
                'total_vendido_ultimo_mes' => (float) $rawData->sum('ventas_num'),
                'total_vendido_mes_actual' => (float) $rawData->sum('ventas_mes_actual_num'),
                'total_incentivo' => $totalIncentivo,
                'total_vendido_format' => number_format($totalVendido, 2, '.', ','),
                'total_vendido_ultimo_mes_format' => number_format((float) $rawData->sum('ventas_num'), 2, '.', ','),
                'total_vendido_mes_actual_format' => number_format((float) $rawData->sum('ventas_mes_actual_num'), 2, '.', ','),
                'total_incentivo_format' => number_format($totalIncentivo, 2, '.', ','),
            ],
            'data' => $data,
        ]);
    }

    public function reporteNuevoIncentivoV2View()
    {
        return view('incentivos.reporte-nuevo-incentivo-v2');
    }

    public function reporteNuevoIncentivoV2(Request $request)
    {
        ini_set('max_execution_time', 600);
        ini_set('memory_limit', '1G');

        $request->validate([
            'fecha_ini' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_ini',
            'sistema' => 'nullable|in:Todos,Lotobet,Lotonet',
            'min_dias_venta' => 'nullable|integer|min:1',
            'filtro_cumplimiento' => 'nullable|in:todos,cumplidos,no_cumplidos',
            'tramo_activo' => 'nullable|in:tramo1,tramo2',
            'rangos_pago' => 'nullable|string',
            'terminales_excluidas' => 'nullable|string',
        ]);

        $fechaIniSeleccionada = Carbon::parse($request->input('fecha_ini'))->toDateString();
        $fechaFinSeleccionada = Carbon::parse($request->input('fecha_fin'))->toDateString();

        $mesAnterior = Carbon::parse($fechaFinSeleccionada)->subMonthNoOverflow();
        $evalIni = $mesAnterior->copy()->startOfMonth()->toDateString();
        $evalFin = $mesAnterior->copy()->endOfMonth()->toDateString();

        $sistema = $request->input('sistema', 'Todos');
        $minDiasVenta = (int) $request->input('min_dias_venta', 10);
        $filtroCumplimiento = $request->input('filtro_cumplimiento', 'todos');
        $tramoActivo = $request->input('tramo_activo', 'tramo1');
        $terminalesExcluidas = $this->normalizarTerminalesExcluidasReporteNuevoIncentivoV5($request->input('terminales_excluidas'));

        $rangosPagoDefault = [
            ['desde' => 100001, 'hasta' => 250000, 'pago' => 1000],
            ['desde' => 250001, 'hasta' => 400000, 'pago' => 2000],
            ['desde' => 400001, 'hasta' => 550000, 'pago' => 4000],
            ['desde' => 550001, 'hasta' => 700000, 'pago' => 6000],
            ['desde' => 700001, 'hasta' => 850000, 'pago' => 8000],
            ['desde' => 850001, 'hasta' => 1000000, 'pago' => 10000],
            ['desde' => 1000001, 'hasta' => 1150000, 'pago' => 12000],
            ['desde' => 1150001, 'hasta' => 1300000, 'pago' => 14000],
            ['desde' => 1300001, 'hasta' => 1450000, 'pago' => 16000],
            ['desde' => 1450001, 'hasta' => 1600000, 'pago' => 18000],
            ['desde' => 1600001, 'hasta' => 1750000, 'pago' => 20000],
            ['desde' => 1750001, 'hasta' => 1900000, 'pago' => 22000],
            ['desde' => 1900001, 'hasta' => 2050000, 'pago' => 24000],
            ['desde' => 2050001, 'hasta' => 2200000, 'pago' => 26000],
            ['desde' => 2200001, 'hasta' => 2350000, 'pago' => 28000],
            ['desde' => 2350001, 'hasta' => 2500000, 'pago' => 30000],
            ['desde' => 2500001, 'hasta' => 2650000, 'pago' => 32000],
            ['desde' => 2650001, 'hasta' => 2800000, 'pago' => 34000],
            ['desde' => 2800001, 'hasta' => 2950000, 'pago' => 36000],
            ['desde' => 2950001, 'hasta' => 3100000, 'pago' => 38000],
            ['desde' => 3100001, 'hasta' => 3250000, 'pago' => 40000],
            ['desde' => 3250001, 'hasta' => 3400000, 'pago' => 42000],
            ['desde' => 3400001, 'hasta' => 3550000, 'pago' => 44000],
            ['desde' => 3550001, 'hasta' => 3700000, 'pago' => 46000],
            ['desde' => 3700001, 'hasta' => 3850000, 'pago' => 48000],
            ['desde' => 3850001, 'hasta' => 5000000, 'pago' => 50000],
        ];

        $rangosPago = $rangosPagoDefault;
        $rangosPagoInput = $request->input('rangos_pago');
        if (is_string($rangosPagoInput) && trim($rangosPagoInput) !== '') {
            $decoded = json_decode($rangosPagoInput, true);
            if (is_array($decoded) && count($decoded) > 0) {
                $sanitized = collect($decoded)
                    ->map(function ($row) {
                        if (!is_array($row)) {
                            return null;
                        }

                        $desde = isset($row['desde']) ? (float) $row['desde'] : 0;
                        $hasta = isset($row['hasta']) ? (float) $row['hasta'] : 0;
                        $pago = isset($row['pago']) ? (float) $row['pago'] : 0;

                        if ($desde < 0 || $hasta < 0 || $pago < 0 || $desde > $hasta) {
                            return null;
                        }

                        return [
                            'desde' => $desde,
                            'hasta' => $hasta,
                            'pago' => $pago,
                        ];
                    })
                    ->filter()
                    ->sortBy('desde')
                    ->values()
                    ->all();

                if (!empty($sanitized)) {
                    $rangosPago = $sanitized;
                }
            }
        }

        $buildBaseQuery = function (string $desde, string $hasta) use ($sistema, $terminalesExcluidas) {
            $betQuery = DB::table('vt_usuarios_bet')
                ->selectRaw("cedula, monto, fecha, 'Lotobet' as sistema")
                ->whereBetween('fecha', [$desde, $hasta]);

            $netQuery = DB::table('vt_usuarios_net')
                ->selectRaw("cedula, monto, fecha, 'Lotonet' as sistema")
                ->whereBetween('fecha', [$desde, $hasta]);

            if ($terminalesExcluidas->isNotEmpty()) {
                $betQuery->whereNotIn(DB::raw('TRIM(CAST(agencia_id AS CHAR))'), $terminalesExcluidas->all());
                $netQuery->whereNotIn(DB::raw('TRIM(CAST(agencia_id AS CHAR))'), $terminalesExcluidas->all());
            }

            if ($sistema === 'Lotobet') {
                return $betQuery;
            }

            if ($sistema === 'Lotonet') {
                return $netQuery;
            }

            return $betQuery->unionAll($netQuery);
        };

        $rowsUltimoMes = DB::query()
            ->fromSub($buildBaseQuery($evalIni, $evalFin), 'y')
            ->selectRaw('y.cedula, SUM(y.monto) AS ventas_ultimo_mes, COUNT(DISTINCT DATE(y.fecha)) AS dias_ventas_ultimo_mes')
            ->whereNotNull('y.cedula')
            ->where('y.cedula', '<>', '')
            ->groupBy('y.cedula')
            ->get();

        $rowsMesActual = DB::query()
            ->fromSub($buildBaseQuery($fechaIniSeleccionada, $fechaFinSeleccionada), 'z')
            ->selectRaw('z.cedula, SUM(z.monto) AS ventas_mes_actual, COUNT(DISTINCT DATE(z.fecha)) AS dias_ventas_mes_actual')
            ->whereNotNull('z.cedula')
            ->where('z.cedula', '<>', '')
            ->groupBy('z.cedula')
            ->get();

        $ultimoMesByCedula = $rowsUltimoMes->keyBy('cedula');
        $mesActualByCedula = $rowsMesActual->keyBy('cedula');
        $cedulas = $ultimoMesByCedula->keys()->merge($mesActualByCedula->keys())->unique()->values();
        $empresaByCedula = [];

        if ($cedulas->isNotEmpty()) {
            $buildAgencyTerminalQuery = function (string $tabla) use ($fechaIniSeleccionada, $fechaFinSeleccionada, $terminalesExcluidas) {
                $query = DB::table($tabla)
                    ->selectRaw('cedula, TRIM(CAST(agencia_id AS CHAR)) AS terminal, COUNT(*) AS total')
                    ->whereBetween('fecha', [$fechaIniSeleccionada, $fechaFinSeleccionada])
                    ->whereNotNull('cedula')
                    ->where('cedula', '<>', '')
                    ->whereNotNull('agencia_id')
                    ->whereRaw("TRIM(CAST(agencia_id AS CHAR)) <> ''");

                if ($terminalesExcluidas->isNotEmpty()) {
                    $query->whereNotIn(DB::raw('TRIM(CAST(agencia_id AS CHAR))'), $terminalesExcluidas->all());
                }

                return $query->groupBy('cedula', DB::raw('TRIM(CAST(agencia_id AS CHAR))'));
            };

            if ($sistema === 'Lotobet') {
                $terminalSourceQuery = $buildAgencyTerminalQuery('vt_usuarios_bet');
            } elseif ($sistema === 'Lotonet') {
                $terminalSourceQuery = $buildAgencyTerminalQuery('vt_usuarios_net');
            } else {
                $terminalSourceQuery = $buildAgencyTerminalQuery('vt_usuarios_bet')
                    ->unionAll($buildAgencyTerminalQuery('vt_usuarios_net'));
            }

            $terminalRows = DB::query()
                ->fromSub($terminalSourceQuery, 'terminales_usuario')
                ->selectRaw('cedula, terminal, SUM(total) AS total')
                ->groupBy('cedula', 'terminal')
                ->orderBy('cedula')
                ->orderByDesc('total')
                ->get();

            $terminales = $terminalRows
                ->pluck('terminal')
                ->map(function ($terminal) {
                    return trim((string) $terminal);
                })
                ->filter(function ($terminal) {
                    return $terminal !== '';
                })
                ->unique()
                ->values();

            $empresaByTerminal = [];
            foreach ($terminales->chunk(1000) as $terminalChunk) {
                DB::table('agencias')
                    ->whereIn(DB::raw('TRIM(CAST(terminal AS CHAR))'), $terminalChunk->all())
                    ->selectRaw("TRIM(CAST(terminal AS CHAR)) AS terminal, COALESCE(NULLIF(TRIM(empresa), ''), 'Sin empresa') AS empresa")
                    ->orderBy('terminal')
                    ->get()
                    ->each(function ($row) use (&$empresaByTerminal) {
                        $empresaByTerminal[(string) $row->terminal] = (string) $row->empresa;
                    });
            }

            $empresaCounterByCedula = [];
            foreach ($terminalRows as $row) {
                $cedulaKey = (string) $row->cedula;
                $terminal = trim((string) $row->terminal);
                $empresa = $empresaByTerminal[$terminal] ?? 'Sin empresa';

                if (!isset($empresaCounterByCedula[$cedulaKey])) {
                    $empresaCounterByCedula[$cedulaKey] = [];
                }

                $empresaCounterByCedula[$cedulaKey][$empresa] = ($empresaCounterByCedula[$cedulaKey][$empresa] ?? 0) + (int) $row->total;
            }

            foreach ($empresaCounterByCedula as $cedulaKey => $empresas) {
                arsort($empresas);
                $empresaByCedula[$cedulaKey] = (string) array_key_first($empresas);
            }
        }

        $rawData = $cedulas->map(function ($cedula) use ($ultimoMesByCedula, $mesActualByCedula, $minDiasVenta, $rangosPago, $tramoActivo, $empresaByCedula) {
            $rowUltimoMes = $ultimoMesByCedula->get($cedula);
            $rowMesActual = $mesActualByCedula->get($cedula);

            $ventas = $rowUltimoMes ? (float) $rowUltimoMes->ventas_ultimo_mes : 0;
            $ventasMesActual = $rowMesActual ? (float) $rowMesActual->ventas_mes_actual : 0;
            $diasMesActual = $rowMesActual ? (int) $rowMesActual->dias_ventas_mes_actual : 0;
            $empresa = (string) ($empresaByCedula[(string) $cedula] ?? 'Sin empresa');

            $cumple = $diasMesActual >= $minDiasVenta;
            $pagoEscala = 0.00;

            if ($cumple) {
                foreach ($rangosPago as $rango) {
                    if ($ventasMesActual >= (float) $rango['desde'] && $ventasMesActual <= (float) $rango['hasta']) {
                        if ($tramoActivo === 'tramo2' && (float) $rango['desde'] >= 1000001) {
                            $pagoEscala = $ventasMesActual * ((float) $rango['pago'] / 100);
                        } else {
                            $pagoEscala = (float) $rango['pago'];
                        }
                        break;
                    }
                }

                if ($pagoEscala === 0.0 && !empty($rangosPago)) {
                    $ultimoRango = end($rangosPago);
                    if ($ventasMesActual >= (float) $ultimoRango['desde']) {
                        if ($tramoActivo === 'tramo2' && (float) $ultimoRango['desde'] >= 1000001) {
                            $pagoEscala = $ventasMesActual * ((float) $ultimoRango['pago'] / 100);
                        } else {
                            $pagoEscala = (float) $ultimoRango['pago'];
                        }
                    }
                    reset($rangosPago);
                }
            }

            return [
                'cedula' => $cedula,
                'empresa' => $empresa,
                'ventas_num' => $ventas,
                'ventas_mes_actual_num' => $ventasMesActual,
                'dias_ventas_mes_actual' => $diasMesActual,
                'cumple_bool' => $cumple,
                'pago_escala_num' => $pagoEscala,
                'nuevo_incentivo_num' => $pagoEscala,
            ];
        })->sortByDesc('ventas_num')->values();

        if ($filtroCumplimiento === 'cumplidos') {
            $rawData = $rawData->where('cumple_bool', true)->values();
        } elseif ($filtroCumplimiento === 'no_cumplidos') {
            $rawData = $rawData->where('cumple_bool', false)->values();
        }

        $totalVendido = (float) $rawData->sum('ventas_mes_actual_num');
        $totalIncentivo = (float) $rawData->sum('nuevo_incentivo_num');
        $cedulasNormalizadas = $rawData->pluck('cedula')
            ->map(fn ($cedula) => preg_replace('/\D+/', '', (string) $cedula))
            ->filter()
            ->unique()
            ->values();
        $cedulaLookupKey = function ($cedula) {
            $digits = preg_replace('/\D+/', '', (string) $cedula);
            $normalized = ltrim($digits, '0');

            return $normalized === '' ? '0' : $normalized;
        };
        $empleadosPorCedula = DB::table('empleados')
            ->whereIn(DB::raw('CAST(cedula AS UNSIGNED)'), $cedulasNormalizadas->all())
            ->selectRaw('CAST(cedula AS UNSIGNED) AS cedula')
            ->selectRaw("MAX(TRIM(CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, '')))) AS nombre")
            ->selectRaw('MAX(empleadoid) AS empleadoid')
            ->groupByRaw('CAST(cedula AS UNSIGNED)')
            ->get()
            ->mapWithKeys(function ($row) use ($cedulaLookupKey) {
                return [$cedulaLookupKey($row->cedula) => $row];
            });

        $ultimaVentaPorCedula = [];
        if ($cedulasNormalizadas->isNotEmpty()) {
            $buildUltimaVentaQuery = function (string $tabla) use ($fechaIniSeleccionada, $fechaFinSeleccionada, $terminalesExcluidas) {
                $query = DB::table($tabla)
                    ->selectRaw('CAST(cedula AS UNSIGNED) AS cedula, TRIM(CAST(agencia_id AS CHAR)) AS terminal, MAX(DATE(fecha)) AS ultimo_dia_venta')
                    ->whereBetween('fecha', [$fechaIniSeleccionada, $fechaFinSeleccionada])
                    ->whereNotNull('cedula')
                    ->where('cedula', '<>', '')
                    ->whereNotNull('agencia_id')
                    ->whereRaw("TRIM(CAST(agencia_id AS CHAR)) <> ''");

                if ($terminalesExcluidas->isNotEmpty()) {
                    $query->whereNotIn(DB::raw('TRIM(CAST(agencia_id AS CHAR))'), $terminalesExcluidas->all());
                }

                return $query->groupByRaw('CAST(cedula AS UNSIGNED), TRIM(CAST(agencia_id AS CHAR))');
            };

            if ($sistema === 'Lotobet') {
                $ultimaVentaQuery = $buildUltimaVentaQuery('vt_usuarios_bet');
            } elseif ($sistema === 'Lotonet') {
                $ultimaVentaQuery = $buildUltimaVentaQuery('vt_usuarios_net');
            } else {
                $ultimaVentaQuery = $buildUltimaVentaQuery('vt_usuarios_bet')
                    ->unionAll($buildUltimaVentaQuery('vt_usuarios_net'));
            }

            $ultimaVentaRows = DB::query()
                ->fromSub($ultimaVentaQuery, 'uv')
                ->whereIn('uv.cedula', $cedulasNormalizadas->all())
                ->selectRaw('uv.cedula, uv.terminal, uv.ultimo_dia_venta')
                ->orderBy('uv.cedula')
                ->orderByDesc('uv.ultimo_dia_venta')
                ->get();

            $terminalesUltimaVenta = $ultimaVentaRows
                ->pluck('terminal')
                ->filter()
                ->unique()
                ->values();
            $agenciasPorTerminal = collect();

            if ($terminalesUltimaVenta->isNotEmpty()) {
                $agenciasPorTerminal = DB::table('agencias')
                    ->whereIn(DB::raw('TRIM(CAST(terminal AS CHAR))'), $terminalesUltimaVenta->all())
                    ->selectRaw("TRIM(CAST(terminal AS CHAR)) AS terminal, COALESCE(NULLIF(TRIM(nombre_agencia), ''), NULLIF(TRIM(agencia), ''), 'SIN AGENCIA') AS nombre_agencia")
                    ->get()
                    ->keyBy('terminal');
            }

            foreach ($ultimaVentaRows as $venta) {
                $cedulaKey = $cedulaLookupKey($venta->cedula);
                if (isset($ultimaVentaPorCedula[$cedulaKey])) {
                    continue;
                }

                $terminal = trim((string) $venta->terminal);
                $agencia = $agenciasPorTerminal->get($terminal);
                $ultimaVentaPorCedula[$cedulaKey] = [
                    'terminal' => $terminal,
                    'nombre_agencia' => (string) ($agencia->nombre_agencia ?? 'SIN AGENCIA'),
                    'ultimo_dia_venta' => (string) $venta->ultimo_dia_venta,
                ];
            }
        }

        $data = $rawData->map(function ($row) use ($empleadosPorCedula, $ultimaVentaPorCedula, $cedulaLookupKey) {
            $cedulaKey = $cedulaLookupKey($row['cedula'] ?? '');
            $empleado = $empleadosPorCedula->get($cedulaKey);
            $nombre = trim((string) ($empleado->nombre ?? ''));
            $ultimaVenta = $ultimaVentaPorCedula[$cedulaKey] ?? [];

            return [
                'cedula' => $row['cedula'],
                'empleadoid' => (string) ($empleado->empleadoid ?? ''),
                'nombre' => $nombre !== '' ? $nombre : 'Actualizar en maestro de empleados',
                'ultima_terminal' => $ultimaVenta['terminal'] ?? '',
                'ultima_agencia_nombre' => $ultimaVenta['nombre_agencia'] ?? 'SIN AGENCIA',
                'ultimo_dia_venta' => $ultimaVenta['ultimo_dia_venta'] ?? '',
                'empresa' => $row['empresa'] ?? 'Sin empresa',
                'ventas_ultimo_mes' => number_format($row['ventas_num'], 2, '.', ','),
                'ventas_mes_actual' => number_format($row['ventas_mes_actual_num'], 2, '.', ','),
                'dias_ventas_mes_actual' => $row['dias_ventas_mes_actual'],
                'cumple_minimo' => $row['cumple_bool'] ? 'SI' : 'NO',
                'pago_escala' => number_format($row['pago_escala_num'], 2, '.', ','),
                'nuevo_incentivo' => number_format($row['nuevo_incentivo_num'], 2, '.', ','),
            ];
        })->values();

        return response()->json([
            'meta' => [
                'sistema' => $sistema,
                'fecha_ini' => $request->input('fecha_ini'),
                'fecha_fin' => $request->input('fecha_fin'),
                'eval_ini' => $evalIni,
                'eval_fin' => $evalFin,
                'min_dias_venta' => $minDiasVenta,
                'filtro_cumplimiento' => $filtroCumplimiento,
                'tramo_activo' => $tramoActivo,
                'rangos_pago' => $rangosPago,
                'total_vendido' => $totalVendido,
                'total_vendido_ultimo_mes' => (float) $rawData->sum('ventas_num'),
                'total_vendido_mes_actual' => (float) $rawData->sum('ventas_mes_actual_num'),
                'total_incentivo' => $totalIncentivo,
                'total_vendido_format' => number_format($totalVendido, 2, '.', ','),
                'total_vendido_ultimo_mes_format' => number_format((float) $rawData->sum('ventas_num'), 2, '.', ','),
                'total_vendido_mes_actual_format' => number_format((float) $rawData->sum('ventas_mes_actual_num'), 2, '.', ','),
                'total_incentivo_format' => number_format($totalIncentivo, 2, '.', ','),
            ],
            'data' => $data,
        ]);
    }

    public function reporteNuevoIncentivoV3View()
    {
        $coordinadores = CoordinadorOperador::query()
            ->where('puesto', 'coordinador')
            ->withCount('agencias')
            ->orderBy('nombre')
            ->orderBy('apellido')
            ->get(['id', 'nombre', 'apellido'])
            ->map(function ($coordinador) {
                return [
                    'id' => $coordinador->id,
                    'nombre' => trim(($coordinador->nombre ?? '') . ' ' . ($coordinador->apellido ?? '')),
                    'agencias' => (int) $coordinador->agencias_count,
                    'agencias_validas' => 0,
                    'monto_usuarios' => 0,
                    'pct' => 0.0055,
                ];
            })
            ->values();

        return view('incentivos.reporte-nuevo-incentivo-v3', compact('coordinadores'));
    }

    public function reporteNuevoIncentivoV3(Request $request)
    {
        $request->merge([
            'tramo_activo' => 'tramo2',
        ]);

        $response = $this->reporteNuevoIncentivoV2($request);
        $payload = $response->getData(true);

        if (!isset($payload['data']) || !is_array($payload['data'])) {
            return $response;
        }

        $totalIncentivo = 0;
        foreach ($payload['data'] as &$row) {
            $ventasMesActual = (float) str_replace(',', '', $row['ventas_mes_actual'] ?? 0);
            $nuevoIncentivo = (float) str_replace(',', '', $row['nuevo_incentivo'] ?? 0);

            if ($ventasMesActual >= 1000001 && $nuevoIncentivo > 50000) {
                $nuevoIncentivo = 50000;
                $row['pago_escala'] = number_format($nuevoIncentivo, 2, '.', ',');
                $row['nuevo_incentivo'] = number_format($nuevoIncentivo, 2, '.', ',');
            }

            $totalIncentivo += $nuevoIncentivo;
        }
        unset($row);

        $qualifiedCedulas = collect($payload['data'])
            ->filter(function ($row) {
                return ($row['cumple_minimo'] ?? 'NO') === 'SI'
                    && (float) str_replace(',', '', $row['nuevo_incentivo'] ?? 0) > 0;
            })
            ->pluck('cedula')
            ->filter()
            ->unique()
            ->values();

        $coordinatorValidAgencies = [];
        $coordinatorUserIncentiveAmounts = [];
        $coordinatorUserDetails = [];
        if ($qualifiedCedulas->isNotEmpty()) {
            $incentiveByCedula = collect($payload['data'])
                ->filter(function ($row) {
                    return ($row['cumple_minimo'] ?? 'NO') === 'SI'
                        && (float) str_replace(',', '', $row['nuevo_incentivo'] ?? 0) > 0;
                })
                ->mapWithKeys(function ($row) {
                    return [
                        (string) $row['cedula'] => (float) str_replace(',', '', $row['nuevo_incentivo'] ?? 0),
                    ];
                });
            $employeeNamesByCedula = DB::table('empleados')
                ->whereIn('cedula', $qualifiedCedulas->all())
                ->selectRaw("CAST(cedula AS CHAR) AS cedula, TRIM(CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, ''))) AS nombre")
                ->get()
                ->mapWithKeys(function ($row) {
                    return [(string) $row->cedula => (string) $row->nombre];
                });

            $fechaIniSeleccionada = Carbon::parse($request->input('fecha_ini'))->toDateString();
            $fechaFinSeleccionada = Carbon::parse($request->input('fecha_fin'))->toDateString();
            $sistema = $request->input('sistema', 'Todos');
            $terminalesExcluidas = $this->normalizarTerminalesExcluidasReporteNuevoIncentivoV5($request->input('terminales_excluidas'));

            $qualifiedCedulaSet = $qualifiedCedulas
                ->mapWithKeys(function ($cedula) {
                    return [(string) $cedula => true];
                });

            $buildAgencyQuery = function (string $tabla) use ($fechaIniSeleccionada, $fechaFinSeleccionada, $terminalesExcluidas) {
                $query = DB::table($tabla)
                    ->selectRaw('cedula, TRIM(CAST(agencia_id AS CHAR)) AS terminal, COUNT(*) AS total')
                    ->whereBetween('fecha', [$fechaIniSeleccionada, $fechaFinSeleccionada])
                    ->whereNotNull('cedula')
                    ->where('cedula', '<>', '')
                    ->whereNotNull('agencia_id')
                    ->whereRaw("TRIM(CAST(agencia_id AS CHAR)) <> ''");

                if ($terminalesExcluidas->isNotEmpty()) {
                    $query->whereNotIn(DB::raw('TRIM(CAST(agencia_id AS CHAR))'), $terminalesExcluidas->all());
                }

                return $query->groupBy('cedula', DB::raw('TRIM(CAST(agencia_id AS CHAR))'));
            };

            if ($sistema === 'Lotobet') {
                $validTerminalQuery = $buildAgencyQuery('vt_usuarios_bet');
            } elseif ($sistema === 'Lotonet') {
                $validTerminalQuery = $buildAgencyQuery('vt_usuarios_net');
            } else {
                $validTerminalQuery = $buildAgencyQuery('vt_usuarios_bet')
                    ->unionAll($buildAgencyQuery('vt_usuarios_net'));
            }

            $validCedulaTerminals = DB::query()
                ->fromSub($validTerminalQuery, 'valid_agencies')
                ->select('cedula', 'terminal')
                ->groupBy('cedula', 'terminal')
                ->get()
                ->filter(function ($row) use ($qualifiedCedulaSet) {
                    return isset($qualifiedCedulaSet[(string) $row->cedula]);
                })
                ->values();

            $validTerminals = $validCedulaTerminals
                ->pluck('terminal')
                ->unique()
                ->values();

            if ($validTerminals->isNotEmpty()) {
                $coordinatorAgencyRows = DB::table('coordinador_operador_agencia as coa')
                    ->join('agencias as a', 'a.id', '=', 'coa.agencia_id')
                    ->join('coordinador_operador as co', 'co.id', '=', 'coa.coordinador_operador_id')
                    ->where('co.puesto', 'coordinador')
                    ->whereIn(DB::raw('TRIM(CAST(a.terminal AS CHAR))'), $validTerminals->all())
                    ->selectRaw('coa.coordinador_operador_id, coa.agencia_id, TRIM(CAST(a.terminal AS CHAR)) AS terminal')
                    ->get();

                $coordinatorValidAgencies = $coordinatorAgencyRows
                    ->groupBy('coordinador_operador_id')
                    ->map(function ($rows) {
                        return $rows->pluck('agencia_id')->unique()->count();
                    })
                    ->mapWithKeys(function ($total, $coordinadorId) {
                        return [(string) $coordinadorId => (int) $total];
                    })
                    ->all();

                $coordinatorTerminals = $coordinatorAgencyRows
                    ->groupBy('coordinador_operador_id')
                    ->map(function ($rows) {
                        return $rows->pluck('terminal')->unique()->flip();
                    })
                    ->all();

                $coordinatorCedulas = [];
                foreach ($validCedulaTerminals as $row) {
                    foreach ($coordinatorTerminals as $coordinadorId => $terminales) {
                        if (isset($terminales[$row->terminal])) {
                            $coordinatorCedulas[(string) $coordinadorId][(string) $row->cedula] = true;
                        }
                    }
                }

                foreach ($coordinatorCedulas as $coordinadorId => $cedulasMap) {
                    $cedulas = array_keys($cedulasMap);
                    $coordinatorUserIncentiveAmounts[(string) $coordinadorId] = collect($cedulas)
                        ->sum(function ($cedula) use ($incentiveByCedula) {
                            return (float) ($incentiveByCedula[(string) $cedula] ?? 0);
                        });
                    $coordinatorUserDetails[(string) $coordinadorId] = collect($cedulas)
                        ->map(function ($cedula) use ($incentiveByCedula, $employeeNamesByCedula) {
                            $cedulaString = (string) $cedula;

                            return [
                                'cedula' => $cedulaString,
                                'usuario' => $employeeNamesByCedula[$cedulaString] ?? '',
                                'incentivo' => (float) ($incentiveByCedula[$cedulaString] ?? 0),
                            ];
                        })
                        ->sortByDesc('incentivo')
                        ->values()
                        ->all();
                }
            }
        }

        $payload['meta']['tipo_pago'] = $request->input('tipo_pago', 'tramos_60');
        $payload['meta']['tramo_activo'] = 'incentivo_v3';
        $payload['meta']['coordinador_agencias_validas'] = $coordinatorValidAgencies;
        $payload['meta']['coordinador_monto_usuarios'] = $coordinatorUserIncentiveAmounts;
        $payload['meta']['coordinador_detalle_usuarios'] = $coordinatorUserDetails;
        $payload['meta']['total_incentivo'] = $totalIncentivo;
        $payload['meta']['total_incentivo_format'] = number_format($totalIncentivo, 2, '.', ',');

        return response()->json($payload);
    }

    public function reporteNuevoIncentivoV4View()
    {
        $coordinadores = collect();

        if (
            Schema::hasTable('coordinador_operador')
            && Schema::hasTable('coordinador_operador_agencia')
            && Schema::hasTable('agencias')
        ) {
            $coordinadores = CoordinadorOperador::query()
                ->where('puesto', 'coordinador')
                ->withCount('agencias')
                ->orderBy('nombre')
                ->orderBy('apellido')
                ->get(['id', 'nombre', 'apellido'])
                ->map(function ($coordinador) {
                    return [
                        'id' => $coordinador->id,
                        'nombre' => trim(($coordinador->nombre ?? '') . ' ' . ($coordinador->apellido ?? '')),
                        'agencias' => (int) $coordinador->agencias_count,
                        'agencias_validas' => 0,
                        'monto_usuarios' => 0,
                        'pct' => 0.0055,
                    ];
                })
                ->values();
        }

        $administrativosConfig = collect();

        if (
            Schema::hasTable('incentivo_administrativos')
            && Schema::hasColumn('incentivo_administrativos', 'grupo')
            && Schema::hasColumn('incentivo_administrativos', 'nombre')
            && Schema::hasColumn('incentivo_administrativos', 'empresa')
            && Schema::hasColumn('incentivo_administrativos', 'pct_total')
        ) {
            $administrativosConfig = IncentivoAdministrativo::query()
                ->orderBy('grupo')
                ->orderBy('empresa')
                ->orderBy('nombre')
                ->get(['id', 'grupo', 'nombre', 'empresa', 'pct_total'])
                ->map(function ($row) {
                    return [
                        'id' => (int) $row->id,
                        'grupo' => (string) ($row->grupo ?? ''),
                        'nombre' => (string) ($row->nombre ?? ''),
                        'empresa' => (string) ($row->empresa ?? ''),
                        'pct' => (float) ($row->pct_total ?? 0),
                    ];
                })
                ->values();
        }

        return view('incentivos.reporte-nuevo-incentivo-v4', compact('coordinadores', 'administrativosConfig'));
    }

    public function reporteNuevoIncentivoV4(Request $request)
    {
        $response = $this->reporteNuevoIncentivoV3($request);
        $payload = $response->getData(true);

        if (isset($payload['meta']) && is_array($payload['meta'])) {
            $payload['meta']['tramo_activo'] = 'incentivo_v4';
        }

        return response()->json($payload, $response->status());
    }

    public function faltantesReporteNuevoIncentivoV4(Request $request)
    {
        $validated = $request->validate([
            'cedulas' => 'required|array|min:1',
            'cedulas.*' => 'nullable|string|max:50',
            'fecha_ini' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_ini',
        ]);

        $cedulas = collect($validated['cedulas'])
            ->map(fn ($cedula) => preg_replace('/\D+/', '', (string) $cedula))
            ->filter()
            ->unique()
            ->values();

        if ($cedulas->isEmpty()) {
            return response()->json([
                'total_monto' => 0,
                'total_faltantes' => 0,
                'data' => [],
            ]);
        }

        $faltantesBet = DB::table('faltantes_bet')
            ->select('identificacion', 'faltante_id', 'monto', 'fecha');

        $faltantesNet = DB::table('faltantes_net')
            ->select('identificacion', 'faltante_id', 'monto', 'fecha');

        $rows = DB::query()
            ->fromSub($faltantesBet->unionAll($faltantesNet), 'faltantes')
            ->whereBetween('fecha', [$validated['fecha_ini'], $validated['fecha_fin']])
            ->whereIn(DB::raw('CAST(identificacion AS UNSIGNED)'), $cedulas->all())
            ->selectRaw('CAST(identificacion AS UNSIGNED) AS cedula')
            ->selectRaw('COUNT(faltante_id) AS cantidad_faltantes')
            ->selectRaw('ROUND(SUM(COALESCE(monto, 0)), 2) AS monto')
            ->groupByRaw('CAST(identificacion AS UNSIGNED)')
            ->orderByDesc('monto')
            ->get();

        $nombresPorCedula = DB::table('empleados')
            ->whereIn(DB::raw('CAST(cedula AS UNSIGNED)'), $rows->pluck('cedula')->all())
            ->selectRaw('CAST(cedula AS UNSIGNED) AS cedula')
            ->selectRaw("MAX(TRIM(CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, '')))) AS nombre")
            ->groupByRaw('CAST(cedula AS UNSIGNED)')
            ->pluck('nombre', 'cedula');

        return response()->json([
            'total_monto' => round((float) $rows->sum('monto'), 2),
            'total_faltantes' => (int) $rows->sum('cantidad_faltantes'),
            'data' => $rows->map(function ($row) use ($nombresPorCedula) {
                $nombre = trim((string) ($nombresPorCedula[$row->cedula] ?? ''));

                return [
                    'cedula' => (string) $row->cedula,
                    'nombre' => $nombre !== '' ? $nombre : 'Actualizar en maestro de empleados',
                    'cantidad_faltantes' => (int) $row->cantidad_faltantes,
                    'monto' => round((float) $row->monto, 2),
                ];
            })->values(),
        ]);
    }

    public function recargasPaqueticosReporteNuevoIncentivoV4(Request $request)
    {
        $validated = $request->validate([
            'cedulas' => 'required|array|min:1',
            'cedulas.*' => 'nullable|string|max:50',
            'fecha_ini' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_ini',
            'sistema' => 'nullable|string|in:Todos,Lotobet,Lotonet',
        ]);

        $cedulas = collect($validated['cedulas'])
            ->map(fn ($cedula) => preg_replace('/\D+/', '', (string) $cedula))
            ->filter()
            ->unique()
            ->values();

        if ($cedulas->isEmpty()) {
            return response()->json([
                'total_ventas' => 0,
                'total_recargas' => 0,
                'total_paqueticos' => 0,
                'cantidad_ventas' => 0,
                'data' => [],
            ]);
        }

        $buildVentasQuery = function (string $tabla, string $sistema) use ($validated) {
            return DB::table($tabla)
                ->selectRaw(
                    "cedula, monto, fecha, producto_id, tipo, ? AS sistema",
                    [$sistema]
                )
                ->whereBetween('fecha', [$validated['fecha_ini'], $validated['fecha_fin']])
                ->where(function ($query) {
                    $query->whereIn(DB::raw('CAST(producto_id AS SIGNED)'), [-1, -2])
                        ->orWhereIn(DB::raw('LOWER(TRIM(tipo))'), ['recarga', 'recargas', 'paquetico', 'paqueticos']);
                });
        };

        $sistema = $validated['sistema'] ?? 'Todos';

        if ($sistema === 'Lotobet') {
            $ventasQuery = $buildVentasQuery('vt_usuarios_bet', 'Lotobet');
        } elseif ($sistema === 'Lotonet') {
            $ventasQuery = $buildVentasQuery('vt_usuarios_net', 'Lotonet');
        } else {
            $ventasQuery = $buildVentasQuery('vt_usuarios_bet', 'Lotobet')
                ->unionAll($buildVentasQuery('vt_usuarios_net', 'Lotonet'));
        }

        $rows = DB::query()
            ->fromSub($ventasQuery, 'ventas')
            ->whereNotNull('cedula')
            ->where('cedula', '<>', '')
            ->whereIn(DB::raw('CAST(cedula AS UNSIGNED)'), $cedulas->all())
            ->selectRaw('CAST(cedula AS UNSIGNED) AS cedula')
            ->selectRaw('COUNT(*) AS cantidad_ventas')
            ->selectRaw("ROUND(SUM(CASE WHEN CAST(producto_id AS SIGNED) = -1 OR LOWER(TRIM(tipo)) IN ('recarga', 'recargas') THEN COALESCE(monto, 0) ELSE 0 END), 2) AS total_recargas")
            ->selectRaw("ROUND(SUM(CASE WHEN CAST(producto_id AS SIGNED) = -2 OR LOWER(TRIM(tipo)) IN ('paquetico', 'paqueticos') THEN COALESCE(monto, 0) ELSE 0 END), 2) AS total_paqueticos")
            ->selectRaw('ROUND(SUM(COALESCE(monto, 0)), 2) AS total_ventas')
            ->groupByRaw('CAST(cedula AS UNSIGNED)')
            ->orderByDesc('total_ventas')
            ->get();

        $nombresPorCedula = DB::table('empleados')
            ->whereIn(DB::raw('CAST(cedula AS UNSIGNED)'), $rows->pluck('cedula')->all())
            ->selectRaw('CAST(cedula AS UNSIGNED) AS cedula')
            ->selectRaw("MAX(TRIM(CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, '')))) AS nombre")
            ->groupByRaw('CAST(cedula AS UNSIGNED)')
            ->pluck('nombre', 'cedula');

        return response()->json([
            'total_ventas' => round((float) $rows->sum('total_ventas'), 2),
            'total_recargas' => round((float) $rows->sum('total_recargas'), 2),
            'total_paqueticos' => round((float) $rows->sum('total_paqueticos'), 2),
            'cantidad_ventas' => (int) $rows->sum('cantidad_ventas'),
            'data' => $rows->map(function ($row) use ($nombresPorCedula) {
                $nombre = trim((string) ($nombresPorCedula[$row->cedula] ?? ''));

                return [
                    'cedula' => (string) $row->cedula,
                    'nombre' => $nombre !== '' ? $nombre : 'Actualizar en maestro de empleados',
                    'cantidad_ventas' => (int) $row->cantidad_ventas,
                    'total_recargas' => round((float) $row->total_recargas, 2),
                    'total_paqueticos' => round((float) $row->total_paqueticos, 2),
                    'total_ventas' => round((float) $row->total_ventas, 2),
                ];
            })->values(),
        ]);
    }

    public function reporteNuevoIncentivoV5View()
    {
        $coordinadores = collect();

        if (
            Schema::hasTable('coordinador_operador')
            && Schema::hasTable('coordinador_operador_agencia')
            && Schema::hasTable('agencias')
        ) {
            $cedulaLookupKey = function ($cedula) {
                $digits = preg_replace('/\D+/', '', (string) $cedula);
                $normalized = ltrim($digits, '0');

                return $normalized === '' ? '0' : $normalized;
            };

            $coordinadoresBase = CoordinadorOperador::query()
                ->where('puesto', 'coordinador')
                ->withCount('agencias')
                ->orderBy('nombre')
                ->orderBy('apellido')
                ->get(['id', 'nombre', 'apellido', 'cedula']);

            $empleadosPorCedula = collect();
            if (
                Schema::hasTable('empleados')
                && Schema::hasColumn('empleados', 'cedula')
                && Schema::hasColumn('empleados', 'empleadoid')
            ) {
                $cedulasCoordinadores = $coordinadoresBase
                    ->pluck('cedula')
                    ->map(fn ($cedula) => preg_replace('/\D+/', '', (string) $cedula))
                    ->filter()
                    ->unique()
                    ->values();

                if ($cedulasCoordinadores->isNotEmpty()) {
                    $empleadosPorCedula = DB::table('empleados')
                        ->whereIn(DB::raw('CAST(cedula AS UNSIGNED)'), $cedulasCoordinadores->all())
                        ->selectRaw('CAST(cedula AS UNSIGNED) AS cedula')
                        ->selectRaw('MAX(empleadoid) AS empleadoid')
                        ->groupByRaw('CAST(cedula AS UNSIGNED)')
                        ->get()
                        ->mapWithKeys(function ($row) use ($cedulaLookupKey) {
                            return [$cedulaLookupKey($row->cedula) => (string) ($row->empleadoid ?? '')];
                        });
                }
            }

            $coordinadores = $coordinadoresBase
                ->map(function ($coordinador) use ($empleadosPorCedula, $cedulaLookupKey) {
                    $cedula = (string) ($coordinador->cedula ?? '');

                    return [
                        'id' => $coordinador->id,
                        'nombre' => trim(($coordinador->nombre ?? '') . ' ' . ($coordinador->apellido ?? '')),
                        'cedula' => $cedula,
                        'empleadoid' => (string) ($empleadosPorCedula[$cedulaLookupKey($cedula)] ?? ''),
                        'agencias' => (int) $coordinador->agencias_count,
                        'agencias_validas' => 0,
                        'monto_usuarios' => 0,
                        'pct' => 0.0055,
                    ];
                })
                ->values();
        }

        $administrativosConfig = collect();

        if (
            Schema::hasTable('incentivo_administrativos')
            && Schema::hasColumn('incentivo_administrativos', 'grupo')
            && Schema::hasColumn('incentivo_administrativos', 'nombre')
            && Schema::hasColumn('incentivo_administrativos', 'empresa')
            && Schema::hasColumn('incentivo_administrativos', 'pct_total')
        ) {
            $administrativosConfig = IncentivoAdministrativo::query()
                ->orderBy('grupo')
                ->orderBy('empresa')
                ->orderBy('nombre')
                ->get(['grupo', 'nombre', 'empresa', 'pct_total'])
                ->map(function ($row) {
                    return [
                        'grupo' => (string) ($row->grupo ?? ''),
                        'nombre' => (string) ($row->nombre ?? ''),
                        'empresa' => (string) ($row->empresa ?? ''),
                        'pct' => (float) ($row->pct_total ?? 0),
                    ];
                })
                ->values();
        }

        $terminalesExcluidasIncentivo = $this->terminalesExcluidasIncentivoGuardadas();

        return view('incentivos.reporte-nuevo-incentivo-v5', compact('coordinadores', 'administrativosConfig', 'terminalesExcluidasIncentivo'));
    }

    public function sincronizarAdministrativosReporteNuevoIncentivoV5(Request $request)
    {
        $validated = $request->validate([
            'rows' => 'required|array',
            'rows.*.id' => 'nullable|integer|exists:incentivo_administrativos,id',
            'rows.*.grupo' => [
                'required',
                'string',
                'max:70',
                Rule::in(self::GRUPOS_ADMINISTRATIVOS_V5),
            ],
            'rows.*.nombre' => 'required|string|max:120',
            'rows.*.empresa' => ['required', 'string', 'max:50', Rule::in(['Consorcio Joselito', 'Negosur'])],
            'rows.*.pct_total' => 'required|numeric|min:0|max:9999999',
        ]);

        $rows = collect($validated['rows'])
            ->map(function ($row) {
                $grupo = trim((string) $row['grupo']);
                $pctTotal = round((float) $row['pct_total'], 4);

                return [
                    'id' => isset($row['id']) ? (int) $row['id'] : null,
                    'grupo' => $grupo,
                    'nombre' => trim((string) $row['nombre']),
                    'empresa' => trim((string) $row['empresa']),
                    'pct_total' => $pctTotal,
                ];
            })
            ->values();

        $porcentajesInvalidos = $rows
            ->filter(fn ($row) => !in_array($row['grupo'], self::GRUPOS_ADMINISTRATIVOS_FIJOS_V5, true))
            ->filter(fn ($row) => (float) $row['pct_total'] > 100)
            ->values();

        if ($porcentajesInvalidos->isNotEmpty()) {
            return response()->json([
                'message' => 'El % Total no puede ser mayor que 100 para gerentes, encargados o monitoreo.',
            ], 422);
        }

        $duplicados = $rows
            ->groupBy(fn ($row) => strtolower($row['grupo'] . '|' . $row['nombre'] . '|' . $row['empresa']))
            ->filter(fn ($items) => $items->count() > 1)
            ->keys()
            ->values();

        if ($duplicados->isNotEmpty()) {
            return response()->json([
                'message' => 'Hay filas duplicadas por grupo, nombre y empresa. Ajusta la plantilla antes de guardar.',
            ], 422);
        }

        DB::transaction(function () use ($rows) {
            $idsPayload = $rows
                ->pluck('id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            IncentivoAdministrativo::query()
                ->when(!empty($idsPayload), fn ($query) => $query->whereNotIn('id', $idsPayload))
                ->delete();

            foreach ($rows as $row) {
                $payload = [
                    'grupo' => $row['grupo'],
                    'nombre' => $row['nombre'],
                    'empresa' => $row['empresa'],
                    'pct_total' => $row['pct_total'],
                ];

                if ($row['id']) {
                    IncentivoAdministrativo::whereKey($row['id'])->update($payload);
                } else {
                    IncentivoAdministrativo::create($payload);
                }
            }
        });

        $data = IncentivoAdministrativo::query()
            ->orderBy('grupo')
            ->orderBy('empresa')
            ->orderBy('nombre')
            ->get(['id', 'grupo', 'nombre', 'empresa', 'pct_total'])
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'grupo' => (string) ($row->grupo ?? ''),
                    'nombre' => (string) ($row->nombre ?? ''),
                    'empresa' => (string) ($row->empresa ?? ''),
                    'pct' => (float) ($row->pct_total ?? 0),
                ];
            })
            ->values();

        return response()->json([
            'message' => 'Plantilla administrativa guardada correctamente.',
            'data' => $data,
        ]);
    }

    public function reconocerTerminalesExcluidasReporteNuevoIncentivoV5(Request $request)
    {
        $request->validate([
            'file' => 'nullable|mimes:xlsx,xls,csv|max:4096',
            'terminales_manual' => 'nullable|string',
        ]);

        if (!$request->hasFile('file') && trim((string) $request->input('terminales_manual', '')) === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Debe seleccionar un archivo o escribir al menos una terminal.',
            ], 422);
        }

        try {
            $terminales = collect();
            $totalFilas = 0;

            if ($request->hasFile('file')) {
                $import = new AgenciasActualizacionMasivaImport();
                Excel::import($import, $request->file('file'));

                $rows = $import->rows ?? collect();
                $totalFilas = $rows->count();
                $terminales = $terminales->merge($rows
                    ->map(function ($rowCollection) {
                        $row = collect($rowCollection)->toArray();
                        return $this->valorColumnaReporteNuevoIncentivoV5($row, ['terminal']);
                    })
                    ->filter(fn ($terminal) => trim((string) $terminal) !== '')
                    ->values());
            }

            $terminales = $terminales
                ->merge($this->extraerTerminalesTextoReporteNuevoIncentivoV5($request->input('terminales_manual', '')))
                ->map(fn ($terminal) => trim((string) $terminal))
                ->filter(fn ($terminal) => $terminal !== '')
                ->unique()
                ->values();

            if ($terminales->isEmpty()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No se encontraron terminales para reconocer. La plantilla debe tener una columna llamada Terminal.',
                ], 422);
            }

            $terminalesEncontradas = DB::table('agencias')
                ->whereIn(DB::raw('TRIM(CAST(terminal AS CHAR))'), $terminales->all())
                ->selectRaw('TRIM(CAST(terminal AS CHAR)) AS terminal')
                ->pluck('terminal')
                ->map(fn ($terminal) => trim((string) $terminal))
                ->filter(fn ($terminal) => $terminal !== '')
                ->unique()
                ->values();

            $terminalesNoEncontradas = $terminales
                ->diff($terminalesEncontradas)
                ->values();

            return response()->json([
                'ok' => true,
                'total_filas' => $totalFilas,
                'terminales_leidas' => $terminales->count(),
                'terminales_unicas' => $terminales->count(),
                'encontradas' => $terminalesEncontradas->count(),
                'no_encontradas' => $terminalesNoEncontradas->count(),
                'terminales_encontradas' => $terminalesEncontradas->all(),
                'terminales_no_encontradas' => $terminalesNoEncontradas->all(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Error al reconocer terminales: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function listarTerminalesExcluidasIncentivoReporteNuevoIncentivoV5()
    {
        $terminales = $this->terminalesExcluidasIncentivoGuardadas();

        return response()->json([
            'ok' => true,
            'terminales' => $terminales->all(),
            'count' => $terminales->count(),
        ]);
    }

    public function guardarTerminalesExcluidasIncentivoReporteNuevoIncentivoV5(Request $request)
    {
        $request->validate([
            'terminales' => 'nullable',
        ]);

        $terminales = $this->normalizarTerminalesExcluidasReporteNuevoIncentivoV5($request->input('terminales'));

        if (!Schema::hasTable('terminales_excluidas_incentivo')) {
            return response()->json([
                'ok' => false,
                'message' => 'La tabla terminales_excluidas_incentivo no existe. Ejecuta las migraciones pendientes.',
            ], 500);
        }

        $userId = auth()->id();

        DB::transaction(function () use ($terminales, $userId) {
            DB::table('terminales_excluidas_incentivo')
                ->when($terminales->isNotEmpty(), fn ($query) => $query->whereNotIn('terminal', $terminales->all()))
                ->delete();

            foreach ($terminales as $terminal) {
                DB::table('terminales_excluidas_incentivo')->updateOrInsert(
                    ['terminal' => $terminal],
                    [
                        'updated_by' => $userId,
                        'updated_at' => now(),
                        'created_by' => $userId,
                        'created_at' => now(),
                    ]
                );
            }
        });

        $guardadas = $this->terminalesExcluidasIncentivoGuardadas();

        return response()->json([
            'ok' => true,
            'message' => 'Terminales excluidas guardadas correctamente.',
            'terminales' => $guardadas->all(),
            'count' => $guardadas->count(),
        ]);
    }

    public function plantillaTerminalesExcluidasReporteNuevoIncentivoV5()
    {
        $data = [
            ['Terminal'],
        ];

        return Excel::download(new class($data) implements
            \Maatwebsite\Excel\Concerns\FromArray,
            \Maatwebsite\Excel\Concerns\WithStyles,
            \Maatwebsite\Excel\Concerns\ShouldAutoSize
        {
            protected array $data;

            public function __construct(array $data)
            {
                $this->data = $data;
            }

            public function array(): array
            {
                return $this->data;
            }

            public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
            {
                return [
                    1 => ['font' => ['bold' => true]],
                ];
            }
        }, 'plantilla_terminales_excluidas_incentivo_v5.xlsx');
    }

    public function reporteNuevoIncentivoV5(Request $request)
    {
        $request->validate([
            'fecha_ini' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_ini',
            'sistema' => 'nullable|in:Todos,Lotobet,Lotonet',
            'min_dias_venta' => 'nullable|integer|min:1',
            'filtro_cumplimiento' => 'nullable|in:todos,cumplidos,no_cumplidos',
            'tipo_pago' => 'nullable|string',
            'rangos_pago' => 'nullable|string',
            'modo_calculo' => 'nullable|in:general,separado_empresa',
            'terminales_excluidas' => 'nullable|string',
        ]);

        $modoCalculo = $request->input('modo_calculo', 'general');
        $fechaIniSeleccionada = Carbon::parse($request->input('fecha_ini'))->toDateString();
        $fechaFinSeleccionada = Carbon::parse($request->input('fecha_fin'))->toDateString();
        $sistema = $request->input('sistema', 'Todos');
        $terminalesExcluidas = $request->has('terminales_excluidas')
            ? $this->normalizarTerminalesExcluidasReporteNuevoIncentivoV5($request->input('terminales_excluidas'))
            : $this->terminalesExcluidasIncentivoGuardadas();

        if ($modoCalculo === 'general') {
            $response = $this->reporteNuevoIncentivoV4($request);
            $payload = $response->getData(true);

            if (isset($payload['meta']) && is_array($payload['meta'])) {
                $payload['meta']['tramo_activo'] = 'incentivo_v5';
                $payload['meta']['modo_calculo'] = 'general';
                $payload['meta']['modo_calculo_label'] = 'General consolidado';
                $payload['meta']['resumen_empresas'] = $this->resumenEmpresasReporteNuevoIncentivoV5($request);
                $payload['meta']['terminales_excluidas'] = $terminalesExcluidas->all();
                $payload['meta']['terminales_excluidas_count'] = $terminalesExcluidas->count();
            }

            $payload = $this->normalizarPayloadEnteroReporteNuevoIncentivoV5($payload);
            $payload = $this->agregarHorasTotalesPayloadReporteNuevoIncentivoV5(
                $payload,
                $fechaIniSeleccionada,
                $fechaFinSeleccionada,
                $sistema
            );

            return response()->json($payload, $response->status());
        }

        ini_set('max_execution_time', 600);
        ini_set('memory_limit', '1G');

        $mesAnterior = Carbon::parse($fechaFinSeleccionada)->subMonthNoOverflow();
        $evalIni = $mesAnterior->copy()->startOfMonth()->toDateString();
        $evalFin = $mesAnterior->copy()->endOfMonth()->toDateString();
        $minDiasVenta = (int) $request->input('min_dias_venta', 1);
        $filtroCumplimiento = $request->input('filtro_cumplimiento', 'todos');

        $rangosPago = [];
        $rangosPagoInput = $request->input('rangos_pago');
        if (is_string($rangosPagoInput) && trim($rangosPagoInput) !== '') {
            $decoded = json_decode($rangosPagoInput, true);
            if (is_array($decoded)) {
                $rangosPago = collect($decoded)
                    ->map(function ($row) {
                        if (!is_array($row)) {
                            return null;
                        }

                        $desde = isset($row['desde']) ? (int) round((float) $row['desde']) : 0;
                        $hasta = array_key_exists('hasta', $row) && $row['hasta'] !== null && $row['hasta'] !== ''
                            ? (int) round((float) $row['hasta'])
                            : null;
                        $pago = isset($row['pago']) ? (float) $row['pago'] : 0;
                        $tipo = ($row['tipo'] ?? 'fijo') === 'porcentaje' ? 'porcentaje' : 'fijo';

                        if ($desde < 0 || ($hasta !== null && $hasta < 0) || $pago < 0 || ($hasta !== null && $desde > $hasta)) {
                            return null;
                        }

                        return compact('desde', 'hasta', 'pago', 'tipo');
                    })
                    ->filter()
                    ->sortBy('desde')
                    ->values()
                    ->all();
            }
        }

        if (empty($rangosPago)) {
            $rangosPago = [
                ['desde' => 100001, 'hasta' => 250000, 'pago' => 1000, 'tipo' => 'fijo'],
                ['desde' => 250001, 'hasta' => 400000, 'pago' => 2000, 'tipo' => 'fijo'],
                ['desde' => 400001, 'hasta' => 550000, 'pago' => 4000, 'tipo' => 'fijo'],
                ['desde' => 550001, 'hasta' => 700000, 'pago' => 6000, 'tipo' => 'fijo'],
                ['desde' => 700001, 'hasta' => 850000, 'pago' => 8000, 'tipo' => 'fijo'],
                ['desde' => 850001, 'hasta' => 1000000, 'pago' => 10000, 'tipo' => 'fijo'],
                ['desde' => 1000001, 'hasta' => null, 'pago' => 0.5, 'tipo' => 'porcentaje'],
            ];
        }

        $empresaKey = function ($empresa) {
            return strtolower(trim((string) ($empresa ?: 'Sin empresa')));
        };
        $rowKey = function ($cedula, $empresa) use ($empresaKey) {
            return trim((string) $cedula) . '|' . $empresaKey($empresa);
        };
        $normalizarEmpresaNegocio = function ($empresa) {
            $texto = trim((string) $empresa);
            if ($texto === '' || strtolower($texto) === 'sin empresa') {
                return 'Agencias por asignar empresa';
            }

            $lower = strtolower($texto);
            if (strpos($lower, 'joselito') !== false) {
                return 'Grupo Joselito';
            }
            if (strpos($lower, 'negosur') !== false) {
                return 'Negosur';
            }

            return $texto;
        };
        $enteroMonto = fn ($value): int => (int) round((float) $value);

        $calcularIncentivo = function (int $ventas, int $dias) use ($rangosPago, $minDiasVenta) {
            if ($dias < $minDiasVenta) {
                return 0;
            }

            foreach ($rangosPago as $rango) {
                $desde = (int) round((float) ($rango['desde'] ?? 0));
                $hasta = $rango['hasta'] ?? null;

                if ($ventas >= $desde && ($hasta === null || $ventas <= (int) round((float) $hasta))) {
                    $pago = (float) ($rango['pago'] ?? 0);
                    $monto = ($rango['tipo'] ?? 'fijo') === 'porcentaje'
                        ? $ventas * ($pago / 100)
                        : $pago;

                    return (int) round($monto);
                }
            }

            return 0;
        };

        $buildVentasTerminalQuery = function (string $tabla, string $sistemaLabel, string $desde, string $hasta) use ($terminalesExcluidas) {
            $query = DB::table("{$tabla} as v")
                ->selectRaw(
                    "v.cedula,
                    v.monto,
                    v.fecha,
                    TRIM(CAST(v.agencia_id AS CHAR)) AS terminal,
                    ? AS sistema",
                    [$sistemaLabel]
                )
                ->whereBetween('v.fecha', [$desde, $hasta])
                ->whereNotNull('v.cedula')
                ->where('v.cedula', '<>', '')
                ->whereNotNull('v.agencia_id')
                ->whereRaw("TRIM(CAST(v.agencia_id AS CHAR)) <> ''");

            if ($terminalesExcluidas->isNotEmpty()) {
                $query->whereNotIn(DB::raw('TRIM(CAST(v.agencia_id AS CHAR))'), $terminalesExcluidas->all());
            }

            return $query;
        };

        $buildSourceQuery = function (string $desde, string $hasta) use ($sistema, $buildVentasTerminalQuery) {
            if ($sistema === 'Lotobet') {
                return $buildVentasTerminalQuery('vt_usuarios_bet', 'Lotobet', $desde, $hasta);
            }

            if ($sistema === 'Lotonet') {
                return $buildVentasTerminalQuery('vt_usuarios_net', 'Lotonet', $desde, $hasta);
            }

            return $buildVentasTerminalQuery('vt_usuarios_bet', 'Lotobet', $desde, $hasta)
                ->unionAll($buildVentasTerminalQuery('vt_usuarios_net', 'Lotonet', $desde, $hasta));
        };

        $rowsUltimoTerminal = DB::query()
            ->fromSub($buildSourceQuery($evalIni, $evalFin), 'ventas')
            ->selectRaw('ventas.cedula, ventas.terminal, SUM(ventas.monto) AS ventas_ultimo_mes')
            ->groupBy('ventas.cedula', 'ventas.terminal')
            ->get();

        $rowsMesActualTerminal = DB::query()
            ->fromSub($buildSourceQuery($fechaIniSeleccionada, $fechaFinSeleccionada), 'ventas')
            ->selectRaw('ventas.cedula, ventas.terminal, DATE(ventas.fecha) AS fecha_venta, SUM(ventas.monto) AS ventas_mes_actual')
            ->groupByRaw('ventas.cedula, ventas.terminal, DATE(ventas.fecha)')
            ->get();

        $terminales = $rowsUltimoTerminal->pluck('terminal')
            ->merge($rowsMesActualTerminal->pluck('terminal'))
            ->map(fn ($terminal) => trim((string) $terminal))
            ->filter()
            ->unique()
            ->values();

        $agenciaInfoByTerminal = [];
        foreach ($terminales->chunk(1000) as $terminalChunk) {
            DB::table('agencias')
                ->whereIn(DB::raw('TRIM(CAST(terminal AS CHAR))'), $terminalChunk->all())
                ->selectRaw("TRIM(CAST(terminal AS CHAR)) AS terminal, COALESCE(NULLIF(TRIM(empresa), ''), 'Sin empresa') AS empresa, COALESCE(NULLIF(TRIM(nombre_agencia), ''), NULLIF(TRIM(agencia), ''), 'SIN AGENCIA') AS nombre_agencia")
                ->get()
                ->each(function ($row) use (&$agenciaInfoByTerminal) {
                    $agenciaInfoByTerminal[(string) $row->terminal] = [
                        'empresa' => (string) $row->empresa,
                        'nombre_agencia' => (string) $row->nombre_agencia,
                    ];
                });
        }

        $resolveEmpresa = function ($terminal) use (&$agenciaInfoByTerminal, $normalizarEmpresaNegocio) {
            return $normalizarEmpresaNegocio($agenciaInfoByTerminal[trim((string) $terminal)]['empresa'] ?? 'Agencias por asignar empresa');
        };
        $resolveAgenciaNombre = function ($terminal) use (&$agenciaInfoByTerminal) {
            return $agenciaInfoByTerminal[trim((string) $terminal)]['nombre_agencia'] ?? 'SIN AGENCIA';
        };

        $ultimoAgrupado = [];
        foreach ($rowsUltimoTerminal as $row) {
            $empresa = $resolveEmpresa($row->terminal);
            $key = $rowKey($row->cedula, $empresa);
            if (!isset($ultimoAgrupado[$key])) {
                $ultimoAgrupado[$key] = [
                    'cedula' => (string) $row->cedula,
                    'empresa' => $empresa,
                    'ventas_ultimo_mes' => 0,
                ];
            }

            $ultimoAgrupado[$key]['ventas_ultimo_mes'] += $enteroMonto($row->ventas_ultimo_mes);
        }

        $mesActualAgrupado = [];
        $ultimaVentaPorKey = [];
        foreach ($rowsMesActualTerminal as $row) {
            $empresa = $resolveEmpresa($row->terminal);
            $key = $rowKey($row->cedula, $empresa);
            if (!isset($mesActualAgrupado[$key])) {
                $mesActualAgrupado[$key] = [
                    'cedula' => (string) $row->cedula,
                    'empresa' => $empresa,
                    'ventas_mes_actual' => 0,
                    'dias' => [],
                ];
            }

            $mesActualAgrupado[$key]['ventas_mes_actual'] += $enteroMonto($row->ventas_mes_actual);
            $mesActualAgrupado[$key]['dias'][(string) $row->fecha_venta] = true;

            if (
                !isset($ultimaVentaPorKey[$key])
                || strcmp((string) $row->fecha_venta, (string) $ultimaVentaPorKey[$key]['ultimo_dia_venta']) > 0
            ) {
                $ultimaVentaPorKey[$key] = [
                    'terminal' => trim((string) $row->terminal),
                    'nombre_agencia' => $resolveAgenciaNombre($row->terminal),
                    'ultimo_dia_venta' => (string) $row->fecha_venta,
                ];
            }
        }

        $rowsUltimoMes = collect(array_values($ultimoAgrupado))
            ->map(fn ($row) => (object) $row);
        $rowsMesActual = collect(array_values($mesActualAgrupado))
            ->map(function ($row) {
                return (object) [
                    'cedula' => $row['cedula'],
                    'empresa' => $row['empresa'],
                    'ventas_mes_actual' => $row['ventas_mes_actual'],
                    'dias_ventas_mes_actual' => count($row['dias']),
                ];
            });

        $ultimoMesByKey = $rowsUltimoMes->keyBy(fn ($row) => $rowKey($row->cedula, $row->empresa));
        $mesActualByKey = $rowsMesActual->keyBy(fn ($row) => $rowKey($row->cedula, $row->empresa));
        $keys = $ultimoMesByKey->keys()->merge($mesActualByKey->keys())->unique()->values();

        $rawData = $keys->map(function ($key) use ($ultimoMesByKey, $mesActualByKey, $calcularIncentivo) {
            $rowUltimoMes = $ultimoMesByKey->get($key);
            $rowMesActual = $mesActualByKey->get($key);
            $baseRow = $rowMesActual ?: $rowUltimoMes;
            $ventas = $rowUltimoMes ? (int) $rowUltimoMes->ventas_ultimo_mes : 0;
            $ventasMesActual = $rowMesActual ? (int) $rowMesActual->ventas_mes_actual : 0;
            $diasMesActual = $rowMesActual ? (int) $rowMesActual->dias_ventas_mes_actual : 0;
            $pagoEscala = $calcularIncentivo($ventasMesActual, $diasMesActual);

            return [
                'cedula' => (string) ($baseRow->cedula ?? ''),
                'empresa' => (string) ($baseRow->empresa ?? 'Agencias por asignar empresa'),
                'ventas_num' => $ventas,
                'ventas_mes_actual_num' => $ventasMesActual,
                'dias_ventas_mes_actual' => $diasMesActual,
                'cumple_bool' => $diasMesActual >= 1 && $pagoEscala > 0,
                'pago_escala_num' => $pagoEscala,
                'nuevo_incentivo_num' => $pagoEscala,
            ];
        })->sortByDesc('ventas_mes_actual_num')->values();

        if ($filtroCumplimiento === 'cumplidos') {
            $rawData = $rawData->where('cumple_bool', true)->values();
        } elseif ($filtroCumplimiento === 'no_cumplidos') {
            $rawData = $rawData->where('cumple_bool', false)->values();
        }

        $cedulasNormalizadas = $rawData->pluck('cedula')
            ->map(fn ($cedula) => preg_replace('/\D+/', '', (string) $cedula))
            ->filter()
            ->unique()
            ->values();
        $cedulaLookupKey = function ($cedula) {
            $digits = preg_replace('/\D+/', '', (string) $cedula);
            $normalized = ltrim($digits, '0');

            return $normalized === '' ? '0' : $normalized;
        };
        $empleadosPorCedula = collect();
        if ($cedulasNormalizadas->isNotEmpty()) {
            $empleadosPorCedula = DB::table('empleados')
                ->whereIn(DB::raw('CAST(cedula AS UNSIGNED)'), $cedulasNormalizadas->all())
                ->selectRaw('CAST(cedula AS UNSIGNED) AS cedula')
                ->selectRaw("MAX(TRIM(CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, '')))) AS nombre")
                ->selectRaw('MAX(empleadoid) AS empleadoid')
                ->groupByRaw('CAST(cedula AS UNSIGNED)')
                ->get()
                ->mapWithKeys(function ($row) use ($cedulaLookupKey) {
                    return [$cedulaLookupKey($row->cedula) => $row];
                });
        }

        $horasTotalesPorCedula = $this->obtenerHorasTotalesReporteNuevoIncentivoV5(
            $fechaIniSeleccionada,
            $fechaFinSeleccionada,
            $sistema,
            $cedulasNormalizadas
        );

        $data = $rawData->map(function ($row) use ($empleadosPorCedula, $ultimaVentaPorKey, $rowKey, $cedulaLookupKey, $horasTotalesPorCedula) {
            $cedulaLookup = $cedulaLookupKey($row['cedula'] ?? '');
            $cedulaKey = preg_replace('/\D+/', '', (string) ($row['cedula'] ?? ''));
            $empleado = $empleadosPorCedula->get($cedulaLookup);
            $nombre = trim((string) ($empleado->nombre ?? ''));
            $ultimaVenta = $ultimaVentaPorKey[$rowKey($cedulaKey, $row['empresa'] ?? 'Agencias por asignar empresa')] ?? [];

            return [
                'cedula' => $row['cedula'],
                'empleadoid' => (string) ($empleado->empleadoid ?? ''),
                'nombre' => $nombre !== '' ? $nombre : 'Actualizar en maestro de empleados',
                'ultima_terminal' => $ultimaVenta['terminal'] ?? '',
                'ultima_agencia_nombre' => $ultimaVenta['nombre_agencia'] ?? 'SIN AGENCIA',
                'ultimo_dia_venta' => $ultimaVenta['ultimo_dia_venta'] ?? '',
                'empresa' => $row['empresa'] ?? 'Agencias por asignar empresa',
                'ventas_ultimo_mes' => number_format($row['ventas_num'], 0, '.', ','),
                'ventas_mes_actual' => number_format($row['ventas_mes_actual_num'], 0, '.', ','),
                'dias_ventas_mes_actual' => $row['dias_ventas_mes_actual'],
                'horas_total' => number_format((float) ($horasTotalesPorCedula[$cedulaLookup] ?? 0), 2, '.', ''),
                'cumple_minimo' => $row['cumple_bool'] ? 'SI' : 'NO',
                'pago_escala' => number_format($row['pago_escala_num'], 0, '.', ','),
                'nuevo_incentivo' => number_format($row['nuevo_incentivo_num'], 0, '.', ','),
            ];
        })->values();

        $qualifiedRows = $rawData
            ->filter(fn ($row) => ($row['cumple_bool'] ?? false) && (float) ($row['nuevo_incentivo_num'] ?? 0) > 0)
            ->values();
        $qualifiedKeys = $qualifiedRows
            ->mapWithKeys(fn ($row) => [$rowKey($row['cedula'], $row['empresa']) => true]);
        $incentiveByKey = $qualifiedRows
            ->mapWithKeys(fn ($row) => [$rowKey($row['cedula'], $row['empresa']) => (float) $row['nuevo_incentivo_num']]);
        $employeeNamesByCedula = collect();
        if ($cedulasNormalizadas->isNotEmpty()) {
            $employeeNamesByCedula = DB::table('empleados')
                ->whereIn(DB::raw('CAST(cedula AS UNSIGNED)'), $cedulasNormalizadas->all())
                ->selectRaw('CAST(cedula AS UNSIGNED) AS cedula')
                ->selectRaw("MAX(TRIM(CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, '')))) AS nombre")
                ->groupByRaw('CAST(cedula AS UNSIGNED)')
                ->get()
                ->mapWithKeys(function ($row) use ($cedulaLookupKey) {
                    return [$cedulaLookupKey($row->cedula) => (string) $row->nombre];
                });
        }

        $coordinatorValidAgencies = [];
        $coordinatorUserIncentiveAmounts = [];
        $coordinatorUserDetails = [];
        if ($qualifiedRows->isNotEmpty()) {
            $validCedulaTerminals = $rowsMesActualTerminal
                ->map(function ($row) use ($resolveEmpresa) {
                    return (object) [
                        'cedula' => preg_replace('/\D+/', '', (string) $row->cedula),
                        'terminal' => trim((string) $row->terminal),
                        'empresa' => $resolveEmpresa($row->terminal),
                    ];
                })
                ->unique(fn ($row) => $row->cedula . '|' . $row->terminal . '|' . strtolower($row->empresa))
                ->filter(fn ($row) => isset($qualifiedKeys[$rowKey($row->cedula, $row->empresa)]))
                ->values();

            $validTerminals = $validCedulaTerminals->pluck('terminal')->filter()->unique()->values();
            if ($validTerminals->isNotEmpty()) {
                $coordinatorAgencyRows = DB::table('coordinador_operador_agencia as coa')
                    ->join('agencias as a', 'a.id', '=', 'coa.agencia_id')
                    ->join('coordinador_operador as co', 'co.id', '=', 'coa.coordinador_operador_id')
                    ->where('co.puesto', 'coordinador')
                    ->whereIn(DB::raw('TRIM(CAST(a.terminal AS CHAR))'), $validTerminals->all())
                    ->selectRaw('coa.coordinador_operador_id, coa.agencia_id, TRIM(CAST(a.terminal AS CHAR)) AS terminal')
                    ->get();

                $coordinatorValidAgencies = $coordinatorAgencyRows
                    ->groupBy('coordinador_operador_id')
                    ->map(fn ($rows) => $rows->pluck('agencia_id')->unique()->count())
                    ->mapWithKeys(fn ($total, $coordinadorId) => [(string) $coordinadorId => (int) $total])
                    ->all();

                $coordinatorTerminals = $coordinatorAgencyRows
                    ->groupBy('coordinador_operador_id')
                    ->map(fn ($rows) => $rows->pluck('terminal')->unique()->flip())
                    ->all();

                $coordinatorKeys = [];
                foreach ($validCedulaTerminals as $row) {
                    $qualifiedKey = $rowKey($row->cedula, $row->empresa);
                    foreach ($coordinatorTerminals as $coordinadorId => $terminales) {
                        if (isset($terminales[$row->terminal])) {
                            $coordinatorKeys[(string) $coordinadorId][$qualifiedKey] = [
                                'cedula' => (string) $row->cedula,
                                'empresa' => (string) $row->empresa,
                            ];
                        }
                    }
                }

                foreach ($coordinatorKeys as $coordinadorId => $keysMap) {
                    $coordinatorUserIncentiveAmounts[(string) $coordinadorId] = collect($keysMap)
                        ->keys()
                        ->sum(fn ($key) => (float) ($incentiveByKey[$key] ?? 0));
                    $coordinatorUserDetails[(string) $coordinadorId] = collect($keysMap)
                        ->map(function ($item, $key) use ($incentiveByKey, $employeeNamesByCedula) {
                            $cedulaString = (string) ($item['cedula'] ?? '');
                            $empresa = (string) ($item['empresa'] ?? 'Agencias por asignar empresa');
                            $cedulaLookup = ltrim(preg_replace('/\D+/', '', $cedulaString), '0');
                            $cedulaLookup = $cedulaLookup === '' ? '0' : $cedulaLookup;

                            return [
                                'cedula' => $cedulaString,
                                'usuario' => trim(($employeeNamesByCedula[$cedulaLookup] ?? '') . ' | ' . $empresa),
                                'incentivo' => (float) ($incentiveByKey[$key] ?? 0),
                            ];
                        })
                        ->sortByDesc('incentivo')
                        ->values()
                        ->all();
                }
            }
        }

        $resumenEmpresas = $rawData
            ->groupBy(fn ($row) => (string) ($row['empresa'] ?? 'Agencias por asignar empresa'))
            ->map(function ($rows, $empresa) {
                return [
                    'empresa' => (string) $empresa,
                    'total_vendido' => (int) round((float) $rows->sum('ventas_mes_actual_num')),
                    'total_incentivo' => (int) round((float) $rows->sum('nuevo_incentivo_num')),
                    'usuarios' => $rows->pluck('cedula')->unique()->count(),
                ];
            })
            ->values()
            ->all();

        $totalVendido = (int) round((float) $rawData->sum('ventas_mes_actual_num'));
        $totalIncentivo = (int) round((float) $rawData->sum('nuevo_incentivo_num'));
        $agenciasPorAsignar = collect($resumenEmpresas)
            ->firstWhere('empresa', 'Agencias por asignar empresa');

        return response()->json([
            'meta' => [
                'sistema' => $sistema,
                'fecha_ini' => $request->input('fecha_ini'),
                'fecha_fin' => $request->input('fecha_fin'),
                'eval_ini' => $evalIni,
                'eval_fin' => $evalFin,
                'min_dias_venta' => $minDiasVenta,
                'filtro_cumplimiento' => $filtroCumplimiento,
                'tramo_activo' => 'incentivo_v5',
                'modo_calculo' => 'separado_empresa',
                'modo_calculo_label' => 'Separado por empresa',
                'rangos_pago' => $rangosPago,
                'total_vendido' => $totalVendido,
                'total_vendido_ultimo_mes' => (int) round((float) $rawData->sum('ventas_num')),
                'total_vendido_mes_actual' => $totalVendido,
                'total_incentivo' => $totalIncentivo,
                'total_vendido_format' => number_format($totalVendido, 0, '.', ','),
                'total_vendido_ultimo_mes_format' => number_format((int) round((float) $rawData->sum('ventas_num')), 0, '.', ','),
                'total_vendido_mes_actual_format' => number_format($totalVendido, 0, '.', ','),
                'total_incentivo_format' => number_format($totalIncentivo, 0, '.', ','),
                'resumen_empresas' => $resumenEmpresas,
                'terminales_excluidas' => $terminalesExcluidas->all(),
                'terminales_excluidas_count' => $terminalesExcluidas->count(),
                'aviso_agencias_por_asignar_empresa' => $agenciasPorAsignar
                    ? 'Hay ventas en agencias sin empresa asignada. Filtra "Agencias por asignar empresa" y actualiza la columna empresa en agencias.'
                    : '',
                'coordinador_agencias_validas' => $coordinatorValidAgencies,
                'coordinador_monto_usuarios' => $coordinatorUserIncentiveAmounts,
                'coordinador_detalle_usuarios' => $coordinatorUserDetails,
            ],
            'data' => $data,
        ]);
    }

    private function resumenEmpresasReporteNuevoIncentivoV5(Request $request): array
    {
        $fechaIniSeleccionada = Carbon::parse($request->input('fecha_ini'))->toDateString();
        $fechaFinSeleccionada = Carbon::parse($request->input('fecha_fin'))->toDateString();
        $sistema = $request->input('sistema', 'Todos');
        $minDiasVenta = (int) $request->input('min_dias_venta', 1);
        $terminalesExcluidas = $request->has('terminales_excluidas')
            ? $this->normalizarTerminalesExcluidasReporteNuevoIncentivoV5($request->input('terminales_excluidas'))
            : $this->terminalesExcluidasIncentivoGuardadas();

        $rangosPago = [];
        $rangosPagoInput = $request->input('rangos_pago');
        if (is_string($rangosPagoInput) && trim($rangosPagoInput) !== '') {
            $decoded = json_decode($rangosPagoInput, true);
            if (is_array($decoded)) {
                $rangosPago = collect($decoded)
                    ->map(function ($row) {
                        if (!is_array($row)) {
                            return null;
                        }

                        $desde = isset($row['desde']) ? (int) round((float) $row['desde']) : 0;
                        $hasta = array_key_exists('hasta', $row) && $row['hasta'] !== null && $row['hasta'] !== ''
                            ? (int) round((float) $row['hasta'])
                            : null;
                        $pago = isset($row['pago']) ? (float) $row['pago'] : 0;
                        $tipo = ($row['tipo'] ?? 'fijo') === 'porcentaje' ? 'porcentaje' : 'fijo';

                        if ($desde < 0 || ($hasta !== null && $hasta < 0) || $pago < 0 || ($hasta !== null && $desde > $hasta)) {
                            return null;
                        }

                        return compact('desde', 'hasta', 'pago', 'tipo');
                    })
                    ->filter()
                    ->sortBy('desde')
                    ->values()
                    ->all();
            }
        }

        if (empty($rangosPago)) {
            $rangosPago = [
                ['desde' => 100001, 'hasta' => 250000, 'pago' => 1000, 'tipo' => 'fijo'],
                ['desde' => 250001, 'hasta' => 400000, 'pago' => 2000, 'tipo' => 'fijo'],
                ['desde' => 400001, 'hasta' => 550000, 'pago' => 4000, 'tipo' => 'fijo'],
                ['desde' => 550001, 'hasta' => 700000, 'pago' => 6000, 'tipo' => 'fijo'],
                ['desde' => 700001, 'hasta' => 850000, 'pago' => 8000, 'tipo' => 'fijo'],
                ['desde' => 850001, 'hasta' => 1000000, 'pago' => 10000, 'tipo' => 'fijo'],
                ['desde' => 1000001, 'hasta' => null, 'pago' => 0.5, 'tipo' => 'porcentaje'],
            ];
        }

        $normalizarEmpresaNegocio = function ($empresa) {
            $texto = trim((string) $empresa);
            if ($texto === '' || strtolower($texto) === 'sin empresa') {
                return 'Agencias por asignar empresa';
            }

            $lower = strtolower($texto);
            if (strpos($lower, 'joselito') !== false) {
                return 'Grupo Joselito';
            }
            if (strpos($lower, 'negosur') !== false) {
                return 'Negosur';
            }

            return $texto;
        };

        $enteroMonto = fn ($value): int => (int) round((float) $value);

        $calcularIncentivo = function (int $ventas, int $dias) use ($rangosPago, $minDiasVenta) {
            if ($dias < $minDiasVenta) {
                return 0;
            }

            foreach ($rangosPago as $rango) {
                $desde = (int) round((float) ($rango['desde'] ?? 0));
                $hasta = $rango['hasta'] ?? null;

                if ($ventas >= $desde && ($hasta === null || $ventas <= (int) round((float) $hasta))) {
                    $pago = (float) ($rango['pago'] ?? 0);
                    $monto = ($rango['tipo'] ?? 'fijo') === 'porcentaje'
                        ? $ventas * ($pago / 100)
                        : $pago;

                    return (int) round($monto);
                }
            }

            return 0;
        };

        $buildVentasTerminalQuery = function (string $tabla, string $sistemaLabel) use ($fechaIniSeleccionada, $fechaFinSeleccionada, $terminalesExcluidas) {
            $query = DB::table("{$tabla} as v")
                ->selectRaw(
                    "v.cedula,
                    TRIM(CAST(v.agencia_id AS CHAR)) AS terminal,
                    DATE(v.fecha) AS fecha_venta,
                    SUM(v.monto) AS monto,
                    ? AS sistema",
                    [$sistemaLabel]
                )
                ->whereBetween('v.fecha', [$fechaIniSeleccionada, $fechaFinSeleccionada])
                ->whereNotNull('v.cedula')
                ->where('v.cedula', '<>', '')
                ->whereNotNull('v.agencia_id')
                ->whereRaw("TRIM(CAST(v.agencia_id AS CHAR)) <> ''")
                ->groupByRaw('v.cedula, TRIM(CAST(v.agencia_id AS CHAR)), DATE(v.fecha)');

            if ($terminalesExcluidas->isNotEmpty()) {
                $query->whereNotIn(DB::raw('TRIM(CAST(v.agencia_id AS CHAR))'), $terminalesExcluidas->all());
            }

            return $query;
        };

        if ($sistema === 'Lotobet') {
            $sourceQuery = $buildVentasTerminalQuery('vt_usuarios_bet', 'Lotobet');
        } elseif ($sistema === 'Lotonet') {
            $sourceQuery = $buildVentasTerminalQuery('vt_usuarios_net', 'Lotonet');
        } else {
            $sourceQuery = $buildVentasTerminalQuery('vt_usuarios_bet', 'Lotobet')
                ->unionAll($buildVentasTerminalQuery('vt_usuarios_net', 'Lotonet'));
        }

        $ventasRows = DB::query()
            ->fromSub($sourceQuery, 'ventas')
            ->selectRaw('ventas.cedula, ventas.terminal, ventas.fecha_venta, SUM(ventas.monto) AS monto')
            ->groupBy('ventas.cedula', 'ventas.terminal', 'ventas.fecha_venta')
            ->get();

        $terminales = $ventasRows
            ->pluck('terminal')
            ->map(fn ($terminal) => trim((string) $terminal))
            ->filter()
            ->unique()
            ->values();

        $empresaByTerminal = [];
        foreach ($terminales->chunk(1000) as $terminalChunk) {
            DB::table('agencias')
                ->whereIn(DB::raw('TRIM(CAST(terminal AS CHAR))'), $terminalChunk->all())
                ->selectRaw("TRIM(CAST(terminal AS CHAR)) AS terminal, COALESCE(NULLIF(TRIM(empresa), ''), 'Agencias por asignar empresa') AS empresa")
                ->get()
                ->each(function ($row) use (&$empresaByTerminal, $normalizarEmpresaNegocio) {
                    $empresaByTerminal[(string) $row->terminal] = $normalizarEmpresaNegocio($row->empresa);
                });
        }

        $rowsByCedulaEmpresa = [];
        foreach ($ventasRows as $row) {
            $empresa = $empresaByTerminal[trim((string) $row->terminal)] ?? 'Agencias por asignar empresa';
            $key = trim((string) $row->cedula) . '|' . strtolower($empresa);

            if (!isset($rowsByCedulaEmpresa[$key])) {
                $rowsByCedulaEmpresa[$key] = [
                    'cedula' => trim((string) $row->cedula),
                    'empresa' => $empresa,
                    'ventas' => 0,
                    'dias' => [],
                ];
            }

            $rowsByCedulaEmpresa[$key]['ventas'] += $enteroMonto($row->monto);
            $rowsByCedulaEmpresa[$key]['dias'][(string) $row->fecha_venta] = true;
        }

        return collect($rowsByCedulaEmpresa)
            ->map(function ($row) use ($calcularIncentivo) {
                return [
                    'empresa' => $row['empresa'],
                    'cedula' => $row['cedula'],
                    'ventas' => (int) $row['ventas'],
                    'incentivo' => $calcularIncentivo((int) $row['ventas'], count($row['dias'])),
                ];
            })
            ->groupBy('empresa')
            ->map(function ($rows, $empresa) {
                return [
                    'empresa' => (string) $empresa,
                    'total_vendido' => (int) round((float) $rows->sum('ventas')),
                    'total_incentivo' => (int) round((float) $rows->sum('incentivo')),
                    'usuarios' => $rows->pluck('cedula')->unique()->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function normalizarPayloadEnteroReporteNuevoIncentivoV5(array $payload): array
    {
        $camposData = [
            'ventas_ultimo_mes',
            'ventas_mes_actual',
            'pago_escala',
            'nuevo_incentivo',
            'total_incentivo',
            'total_a_pagar',
        ];

        if (isset($payload['data']) && is_array($payload['data'])) {
            foreach ($payload['data'] as &$row) {
                if (!is_array($row)) {
                    continue;
                }

                foreach ($camposData as $campo) {
                    if (array_key_exists($campo, $row)) {
                        $monto = $this->enteroMontoReporteNuevoIncentivoV5($row[$campo]);
                        $row[$campo] = number_format($monto, 0, '.', ',');
                    }
                }
            }
            unset($row);
        }

        if (isset($payload['meta']) && is_array($payload['meta'])) {
            $camposMeta = [
                'total_vendido',
                'total_vendido_ultimo_mes',
                'total_vendido_mes_actual',
                'total_incentivo',
            ];

            foreach ($camposMeta as $campo) {
                if (array_key_exists($campo, $payload['meta'])) {
                    $payload['meta'][$campo] = $this->enteroMontoReporteNuevoIncentivoV5($payload['meta'][$campo]);
                }
            }

            $camposMetaFormato = [
                'total_vendido_format' => 'total_vendido',
                'total_vendido_ultimo_mes_format' => 'total_vendido_ultimo_mes',
                'total_vendido_mes_actual_format' => 'total_vendido_mes_actual',
                'total_incentivo_format' => 'total_incentivo',
            ];

            foreach ($camposMetaFormato as $campoFormato => $campoBase) {
                if (array_key_exists($campoBase, $payload['meta'])) {
                    $payload['meta'][$campoFormato] = number_format((int) $payload['meta'][$campoBase], 0, '.', ',');
                }
            }

            if (isset($payload['meta']['resumen_empresas']) && is_array($payload['meta']['resumen_empresas'])) {
                foreach ($payload['meta']['resumen_empresas'] as &$row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    foreach (['total_vendido', 'total_incentivo'] as $campo) {
                        if (array_key_exists($campo, $row)) {
                            $row[$campo] = $this->enteroMontoReporteNuevoIncentivoV5($row[$campo]);
                        }
                    }
                }
                unset($row);
            }

            if (isset($payload['meta']['coordinador_monto_usuarios']) && is_array($payload['meta']['coordinador_monto_usuarios'])) {
                foreach ($payload['meta']['coordinador_monto_usuarios'] as $key => $value) {
                    $payload['meta']['coordinador_monto_usuarios'][$key] = $this->enteroMontoReporteNuevoIncentivoV5($value);
                }
            }

            if (isset($payload['meta']['coordinador_detalle_usuarios']) && is_array($payload['meta']['coordinador_detalle_usuarios'])) {
                foreach ($payload['meta']['coordinador_detalle_usuarios'] as &$usuarios) {
                    if (!is_array($usuarios)) {
                        continue;
                    }

                    foreach ($usuarios as &$usuario) {
                        if (is_array($usuario) && array_key_exists('incentivo', $usuario)) {
                            $usuario['incentivo'] = $this->enteroMontoReporteNuevoIncentivoV5($usuario['incentivo']);
                        }
                    }
                    unset($usuario);
                }
                unset($usuarios);
            }
        }

        return $payload;
    }

    private function agregarHorasTotalesPayloadReporteNuevoIncentivoV5(array $payload, string $fechaIni, string $fechaFin, string $sistema): array
    {
        if (!isset($payload['data']) || !is_array($payload['data']) || empty($payload['data'])) {
            return $payload;
        }

        $cedulas = collect($payload['data'])
            ->map(fn ($row) => preg_replace('/\D+/', '', (string) ($row['cedula'] ?? '')))
            ->filter()
            ->unique()
            ->values();

        $horasTotalesPorCedula = $this->obtenerHorasTotalesReporteNuevoIncentivoV5(
            $fechaIni,
            $fechaFin,
            $sistema,
            $cedulas
        );

        foreach ($payload['data'] as &$row) {
            if (!is_array($row)) {
                continue;
            }

            $cedula = preg_replace('/\D+/', '', (string) ($row['cedula'] ?? ''));
            $cedulaLookup = ltrim($cedula, '0');
            $cedulaLookup = $cedulaLookup === '' ? '0' : $cedulaLookup;
            $row['horas_total'] = number_format((float) ($horasTotalesPorCedula[$cedulaLookup] ?? 0), 2, '.', '');
        }
        unset($row);

        return $payload;
    }

    private function normalizarTerminalesExcluidasReporteNuevoIncentivoV5($value)
    {
        $terminales = collect();

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $terminales = collect($decoded);
            } else {
                $terminales = $this->extraerTerminalesTextoReporteNuevoIncentivoV5($value);
            }
        } elseif (is_array($value)) {
            $terminales = collect($value);
        }

        return $terminales
            ->map(fn ($terminal) => trim((string) $terminal))
            ->filter(fn ($terminal) => $terminal !== '')
            ->unique()
            ->values();
    }

    private function terminalesExcluidasIncentivoGuardadas()
    {
        if (!Schema::hasTable('terminales_excluidas_incentivo')) {
            return collect();
        }

        return DB::table('terminales_excluidas_incentivo')
            ->selectRaw('TRIM(CAST(terminal AS CHAR)) AS terminal')
            ->whereNotNull('terminal')
            ->orderBy('terminal')
            ->pluck('terminal')
            ->map(fn ($terminal) => trim((string) $terminal))
            ->filter(fn ($terminal) => $terminal !== '')
            ->unique()
            ->values();
    }

    private function extraerTerminalesTextoReporteNuevoIncentivoV5($texto)
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return collect();
        }

        return collect(preg_split('/[\r\n,;\t ]+/', $texto))
            ->map(fn ($terminal) => trim((string) $terminal))
            ->filter(fn ($terminal) => $terminal !== '')
            ->values();
    }

    private function valorColumnaReporteNuevoIncentivoV5(array $row, array $aliases)
    {
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $row)) {
                return $row[$alias];
            }

            $normalizedAlias = strtolower(str_replace([' ', '-', '.'], '_', $alias));
            foreach ($row as $key => $value) {
                $normalizedKey = strtolower(str_replace([' ', '-', '.'], '_', (string) $key));
                if ($normalizedKey === $normalizedAlias) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function obtenerHorasTotalesReporteNuevoIncentivoV5(string $fechaIni, string $fechaFin, string $sistema, $cedulas)
    {
        $cedulas = collect($cedulas)
            ->map(fn ($cedula) => preg_replace('/\D+/', '', (string) $cedula))
            ->filter()
            ->unique()
            ->values();

        if ($cedulas->isEmpty()) {
            return collect();
        }

        $queries = [];

        if ($sistema === 'Todos' || $sistema === 'Lotonet') {
            $queries[] = DB::table('asistencias_net')
                ->selectRaw("REPLACE(identificacion, '-', '') AS cedula")
                ->selectRaw('SUM(GREATEST(TIMESTAMPDIFF(SECOND, entrada, salida), 0)) / 3600 AS horas')
                ->where('entrada', '>=', $fechaIni)
                ->whereRaw('entrada < DATE_ADD(?, INTERVAL 1 DAY)', [$fechaFin])
                ->whereNotNull('salida')
                ->whereIn(DB::raw('CAST(REPLACE(identificacion, "-", "") AS UNSIGNED)'), $cedulas->all())
                ->groupByRaw("REPLACE(identificacion, '-', '')");
        }

        if ($sistema === 'Todos' || $sistema === 'Lotobet') {
            $queries[] = DB::table('asistencias_bet')
                ->selectRaw("REPLACE(cedula, '-', '') AS cedula")
                ->selectRaw('SUM(GREATEST(TIMESTAMPDIFF(SECOND, primer_login, ultimo_login), 0)) / 3600 AS horas')
                ->whereBetween('fecha', [$fechaIni, $fechaFin])
                ->whereNotNull('primer_login')
                ->whereNotNull('ultimo_login')
                ->whereIn(DB::raw('CAST(REPLACE(cedula, "-", "") AS UNSIGNED)'), $cedulas->all())
                ->groupByRaw("REPLACE(cedula, '-', '')");
        }

        if (empty($queries)) {
            return collect();
        }

        $query = array_shift($queries);
        foreach ($queries as $unionQuery) {
            $query->unionAll($unionQuery);
        }

        return DB::query()
            ->fromSub($query, 'horas')
            ->selectRaw('CAST(cedula AS UNSIGNED) AS cedula')
            ->selectRaw('ROUND(SUM(COALESCE(horas, 0)), 2) AS horas_total')
            ->groupByRaw('CAST(cedula AS UNSIGNED)')
            ->get()
            ->mapWithKeys(function ($row) {
                $cedula = ltrim(preg_replace('/\D+/', '', (string) $row->cedula), '0');
                $cedula = $cedula === '' ? '0' : $cedula;

                return [$cedula => (float) $row->horas_total];
            });
    }

    private function enteroMontoReporteNuevoIncentivoV5($value): int
    {
        if (is_string($value)) {
            $value = str_replace(',', '', $value);
        }

        return (int) round((float) $value);
    }

    public function faltantesReporteNuevoIncentivoV5(Request $request)
    {
        return $this->faltantesReporteNuevoIncentivoV4($request);
    }

    public function desvinculadosReporteNuevoIncentivoV5(Request $request)
    {
        $validated = $request->validate([
            'cedulas' => 'required|array|min:1',
            'cedulas.*' => 'nullable|string|max:50',
        ]);

        $cedulas = collect($validated['cedulas'])
            ->map(fn ($cedula) => preg_replace('/\D+/', '', (string) $cedula))
            ->filter()
            ->unique()
            ->values();

        if ($cedulas->isEmpty()) {
            return response()->json([
                'total_desvinculados' => 0,
                'total_desactivados' => 0,
                'total_con_fecha_salida' => 0,
                'data' => [],
            ]);
        }

        $hasActivo = Schema::hasColumn('empleados', 'activo');
        $hasFechaSalida = Schema::hasColumn('empleados', 'fechasalida');
        $hasFechaSalidaAlt = Schema::hasColumn('empleados', 'fecha_salida');

        if (!$hasActivo && !$hasFechaSalida && !$hasFechaSalidaAlt) {
            return response()->json([
                'total_desvinculados' => 0,
                'total_desactivados' => 0,
                'total_con_fecha_salida' => 0,
                'data' => [],
            ]);
        }

        $rows = DB::table('empleados')
            ->whereIn(DB::raw('CAST(cedula AS UNSIGNED)'), $cedulas->all())
            ->where(function ($query) use ($hasActivo, $hasFechaSalida, $hasFechaSalidaAlt) {
                if ($hasActivo) {
                    $query->orWhereRaw('COALESCE(activo, 1) = 0');
                }

                if ($hasFechaSalida) {
                    $query->orWhereRaw("fechasalida IS NOT NULL AND TRIM(CAST(fechasalida AS CHAR)) <> '' AND TRIM(CAST(fechasalida AS CHAR)) <> '0000-00-00'");
                }

                if ($hasFechaSalidaAlt) {
                    $query->orWhereRaw("fecha_salida IS NOT NULL AND TRIM(CAST(fecha_salida AS CHAR)) <> '' AND TRIM(CAST(fecha_salida AS CHAR)) <> '0000-00-00'");
                }
            })
            ->selectRaw('CAST(cedula AS UNSIGNED) AS cedula')
            ->selectRaw("MAX(TRIM(CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, '')))) AS nombre")
            ->selectRaw($hasActivo ? 'MIN(COALESCE(activo, 1)) AS activo' : '1 AS activo')
            ->selectRaw(
                $hasFechaSalida && $hasFechaSalidaAlt
                    ? "MAX(COALESCE(NULLIF(TRIM(CAST(fecha_salida AS CHAR)), ''), NULLIF(TRIM(CAST(fechasalida AS CHAR)), ''))) AS fecha_salida"
                    : ($hasFechaSalidaAlt
                        ? "MAX(NULLIF(TRIM(CAST(fecha_salida AS CHAR)), '')) AS fecha_salida"
                        : ($hasFechaSalida
                            ? "MAX(NULLIF(TRIM(CAST(fechasalida AS CHAR)), '')) AS fecha_salida"
                            : "'' AS fecha_salida"))
            )
            ->groupByRaw('CAST(cedula AS UNSIGNED)')
            ->orderBy('nombre')
            ->get()
            ->map(function ($row) {
                $fechaSalida = trim((string) ($row->fecha_salida ?? ''));
                if ($fechaSalida === '0000-00-00') {
                    $fechaSalida = '';
                }

                $estaDesactivado = (int) ($row->activo ?? 1) === 0;
                $motivos = [];

                if ($estaDesactivado) {
                    $motivos[] = 'Desactivado';
                }

                if ($fechaSalida !== '') {
                    $motivos[] = 'Fecha de salida registrada';
                }

                $nombre = trim((string) ($row->nombre ?? ''));

                return [
                    'cedula' => (string) $row->cedula,
                    'nombre' => $nombre !== '' ? $nombre : 'Actualizar en maestro de empleados',
                    'estatus' => $motivos ? implode(' / ', $motivos) : 'Desvinculado',
                    'desactivado' => $estaDesactivado,
                    'fecha_salida' => $fechaSalida,
                ];
            })
            ->values();

        return response()->json([
            'total_desvinculados' => $rows->count(),
            'total_desactivados' => $rows->where('desactivado', true)->count(),
            'total_con_fecha_salida' => $rows->filter(fn ($row) => trim((string) ($row['fecha_salida'] ?? '')) !== '')->count(),
            'data' => $rows,
        ]);
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
        if (empty($mes)) {
            return response()->json(['message' => 'Seleccione mes.'], 400);
        }
        $anio = $request->input('year', date('Y'));
        $incentivoId = DB::table('incentivo_temporal_c')
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->value('incentivo_id');

        if ($incentivoId === null) {
            return response()->json(['message' => 'No hay datos registrados en el mes.']);
        }

        $tipoId = '%';
        $tipo = $request->input('tipo');
        if (!empty($tipo)) {
            $tipoId = $tipo;
        }

        $califican = $request->input('califican', '1'); // 1=Todos, 2=Califican, 3=No Califican
        $horas = $request->input('horas', '1'); // 1=Todos, 2=> 150
        $pago = $request->input('pago', '1'); // 1=Todos, 2=< $200.00

        // Construir el filtro de faltantes según el parámetro califican
        $filtroFaltantes = '';
        if ($califican === '2') {
            // Califican: excluir los que tienen faltantes (NOT IN)
            $filtroFaltantes = "AND CAST(e.cedula AS SIGNED) NOT IN (
                    SELECT CAST(identificacion AS SIGNED) FROM faltantes_bet WHERE YEAR(fecha) = $anio AND MONTH(fecha) = $mes
                    UNION ALL
                    SELECT CAST(identificacion AS SIGNED) FROM faltantes_net WHERE YEAR(fecha) = $anio AND MONTH(fecha) = $mes
                )";
        } elseif ($califican === '3') {
            // No califican: incluir solo los que tienen faltantes (IN)
            $filtroFaltantes = "AND CAST(e.cedula AS SIGNED) IN (
                    SELECT CAST(identificacion AS SIGNED) FROM faltantes_bet WHERE YEAR(fecha) = $anio AND MONTH(fecha) = $mes
                    UNION ALL
                    SELECT CAST(identificacion AS SIGNED) FROM faltantes_net WHERE YEAR(fecha) = $anio AND MONTH(fecha) = $mes
                )";
        }
        // Si califican === '1' (Todos), no se aplica ningún filtro

        // Construir filtro de horas
        $filtroHoras = '';
        if ($horas === '2') {
            $filtroHoras = "AND EXISTS (
                    SELECT 1 FROM (
                        SELECT combined.cedula AS emp_cedula, SUM(combined.total_horas) AS horas_totales
                        FROM (
                            SELECT ab.cedula, SUM(TIMESTAMPDIFF(HOUR, ab.primer_login, ab.ultimo_login)) AS total_horas
                            FROM asistencias_bet ab
                            WHERE YEAR(ab.fecha) = $anio AND MONTH(ab.fecha) = $mes
                            GROUP BY ab.cedula
                            UNION ALL
                            SELECT an.identificacion AS cedula, SUM(TIMESTAMPDIFF(HOUR, an.entrada, an.salida)) AS total_horas
                            FROM asistencias_net an
                            WHERE YEAR(an.entrada) = $anio AND MONTH(an.entrada) = $mes
                            GROUP BY an.identificacion
                        ) combined
                        GROUP BY combined.cedula
                        HAVING SUM(combined.total_horas) > 150
                    ) a
                    WHERE CAST(a.emp_cedula AS SIGNED) = CAST(e.cedula AS SIGNED)
                )";
        }

        // Construir filtro de pago
        $filtroPago = '';
        if ($pago === '2') {
            $filtroPago = 'AND t.total_monto < 200';
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
                AND e.fechasalida IS NULL
                $filtroFaltantes
                $filtroHoras
                $filtroPago;"
        );

        return response()->json($data);
    }
}

