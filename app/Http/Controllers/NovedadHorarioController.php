<?php

namespace App\Http\Controllers;

use App\Exports\NovedadesHorarioExport;
use App\Exports\NovedadesHorarioPagoExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class NovedadHorarioController extends Controller
{
    public function index()
    {
        return view('recursos_humanos.novedades_de_horario.index');
    }

    public function list(Request $request)
    {
        $validated = $request->validate([
            'empresa' => ['required', 'in:todos,grupo_joselito,negosur'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'horas_requeridas' => ['required', 'integer', 'min:1'],
            'detalle' => ['nullable', 'in:todos,cumple,tiene_falta'],
        ]);

        $queryData = $this->buildNovedadesHorarioQuery($validated, $request);
        $recordsTotal = (int) DB::selectOne("SELECT COUNT(*) AS total FROM ({$queryData['baseSql']}) base", $queryData['bindings'])->total;
        $recordsFiltered = (int) DB::selectOne(
            "SELECT COUNT(*) AS total FROM ({$queryData['baseSql']}) base {$queryData['whereSql']}",
            array_merge($queryData['bindings'], $queryData['whereBindings'])
        )->total;
        $resumen = DB::selectOne(
            "
                SELECT
                    COUNT(*) AS total,
                    COUNT(DISTINCT terminal) AS terminales,
                    COUNT(DISTINCT nombre_agencia) AS agencias,
                    COALESCE(SUM(horas_acumuladas), 0) AS horas_acumuladas
                FROM ({$queryData['baseSql']}) base
                {$queryData['whereSql']}
            ",
            array_merge($queryData['bindings'], $queryData['whereBindings'])
        );

        $start = max((int) $request->input('start', 0), 0);
        $length = (int) $request->input('length', 25);
        $maxLength = $request->boolean('export') ? 100000 : 200;
        $length = $length > 0 ? min($length, $maxLength) : 25;
        $novedades = $this->getNovedadesHorarioRows($queryData, $length, $start);

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $novedades->values(),
            'resumen' => [
                'total' => (int) ($resumen->total ?? 0),
                'terminales' => (int) ($resumen->terminales ?? 0),
                'agencias' => (int) ($resumen->agencias ?? 0),
                'horas_acumuladas' => round((float) ($resumen->horas_acumuladas ?? 0), 2),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'empresa' => ['required', 'in:todos,grupo_joselito,negosur'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'horas_requeridas' => ['required', 'integer', 'min:1'],
            'valor_hora' => ['required', 'numeric', 'min:0.01'],
            'detalle' => ['nullable', 'in:todos,cumple,tiene_falta'],
        ]);

        $rows = $this->getNovedadesHorarioRows($this->buildNovedadesHorarioQuery($validated, $request));
        $filename = sprintf(
            'novedades_horario_%s_%s.xlsx',
            str_replace('-', '', $validated['fecha_inicio']),
            str_replace('-', '', $validated['fecha_fin'])
        );

        return Excel::download(
            new NovedadesHorarioExport(
                $rows,
                (float) $validated['horas_requeridas'],
                (float) $validated['valor_hora']
            ),
            $filename
        );
    }

    public function exportPago(Request $request)
    {
        $validated = $request->validate([
            'empresa' => ['required', 'in:todos,grupo_joselito,negosur'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'horas_requeridas' => ['required', 'integer', 'min:1'],
            'valor_hora' => ['required', 'numeric', 'min:0.01'],
            'detalle' => ['nullable', 'in:todos,cumple,tiene_falta'],
        ]);

        $rows = $this->getNovedadesHorarioRows($this->buildNovedadesHorarioQuery($validated, $request));
        $resumenPago = $this->buildResumenPagoRows(
            $rows,
            (float) $validated['horas_requeridas'],
            (float) $validated['valor_hora']
        );
        $filename = sprintf(
            'novedad_de_pago_%s_%s.xlsx',
            str_replace('-', '', $validated['fecha_inicio']),
            str_replace('-', '', $validated['fecha_fin'])
        );

        return Excel::download(new NovedadesHorarioPagoExport($resumenPago), $filename);
    }

    private function buildNovedadesHorarioQuery(array $validated, Request $request): array
    {
        $uniones = [];
        $bindings = [];
        $agenciasPorTerminalSql = "
            SELECT
                COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(terminal AS CHAR))), ''), '0') AS terminal_key,
                MAX(TRIM(CAST(terminal AS CHAR))) AS terminal,
                MAX(nombre_agencia) AS nombre_agencia,
                MAX(ruta) AS ruta,
                MAX(empresa) AS empresa
            FROM agencias
            WHERE terminal IS NOT NULL
              AND TRIM(CAST(terminal AS CHAR)) <> ''
            GROUP BY COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(terminal AS CHAR))), ''), '0')
        ";
        $agenciasPorCodigoSql = "
            SELECT
                COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(agencia AS CHAR))), ''), '0') AS agencia_key,
                MAX(TRIM(CAST(terminal AS CHAR))) AS terminal,
                MAX(nombre_agencia) AS nombre_agencia,
                MAX(ruta) AS ruta,
                MAX(empresa) AS empresa
            FROM agencias
            WHERE agencia IS NOT NULL
              AND TRIM(CAST(agencia AS CHAR)) <> ''
            GROUP BY COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(agencia AS CHAR))), ''), '0')
        ";
        $empleadosSql = "
            SELECT
                TRIM(cedula) AS cedula,
                MAX(TRIM(CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, '')))) AS nombre_empleado
            FROM empleados
            WHERE cedula IS NOT NULL
              AND TRIM(cedula) <> ''
            GROUP BY TRIM(cedula)
        ";

        $uniones[] = "
                SELECT
                    COALESCE(at.empresa, aa.empresa, '') AS empresa,
                    COALESCE(at.terminal, aa.terminal, TRIM(CAST(ab.agencia_id AS CHAR))) AS terminal,
                    COALESCE(at.nombre_agencia, aa.nombre_agencia, ab.agencia_id) AS nombre_agencia,
                    COALESCE(at.ruta, aa.ruta, '') AS ruta,
                    TRIM(COALESCE(NULLIF(e.nombre_empleado, ''), ab.usuario, '')) AS nombre_empleado,
                    ab.cedula AS cedula,
                    DATE(ab.fecha) AS fecha,
                    MIN(ab.primer_login) AS primer_login,
                    MAX(ab.ultimo_login) AS ultimo_login,
                    ROUND(GREATEST(TIMESTAMPDIFF(SECOND, MIN(ab.primer_login), MAX(ab.ultimo_login)), 0) / 3600, 2) AS horas_acumuladas
                FROM asistencias_bet ab
                LEFT JOIN ({$agenciasPorTerminalSql}) at
                    ON at.terminal_key = COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(ab.agencia_id AS CHAR))), ''), '0')
                LEFT JOIN ({$agenciasPorCodigoSql}) aa
                    ON aa.agencia_key = COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(ab.agencia_id AS CHAR))), ''), '0')
                LEFT JOIN ({$empleadosSql}) e
                    ON TRIM(e.cedula) = TRIM(ab.cedula)
                WHERE ab.fecha >= ? AND ab.fecha < DATE_ADD(?, INTERVAL 1 DAY)
                  AND ab.primer_login IS NOT NULL
                  AND ab.ultimo_login IS NOT NULL
                GROUP BY
                    COALESCE(at.empresa, aa.empresa, ''),
                    COALESCE(at.terminal, aa.terminal, TRIM(CAST(ab.agencia_id AS CHAR))),
                    COALESCE(at.nombre_agencia, aa.nombre_agencia, ab.agencia_id),
                    COALESCE(at.ruta, aa.ruta, ''),
                    TRIM(COALESCE(NULLIF(e.nombre_empleado, ''), ab.usuario, '')),
                    ab.cedula,
                    DATE(ab.fecha)
        ";
        $bindings[] = $validated['fecha_inicio'];
        $bindings[] = $validated['fecha_fin'];

        $uniones[] = "
                SELECT
                    COALESCE(at.empresa, aa.empresa, '') AS empresa,
                    COALESCE(at.terminal, aa.terminal, TRIM(CAST(COALESCE(NULLIF(an.terminal, ''), an.agencia) AS CHAR))) AS terminal,
                    COALESCE(at.nombre_agencia, aa.nombre_agencia, an.banca, an.agencia) AS nombre_agencia,
                    COALESCE(at.ruta, aa.ruta, '') AS ruta,
                    TRIM(COALESCE(NULLIF(e.nombre_empleado, ''), an.usuario, an.username, '')) AS nombre_empleado,
                    an.identificacion AS cedula,
                    DATE(an.entrada) AS fecha,
                    MIN(an.entrada) AS primer_login,
                    MAX(COALESCE(an.salida, an.salida_inactividad)) AS ultimo_login,
                    ROUND(GREATEST(TIMESTAMPDIFF(SECOND, MIN(an.entrada), MAX(COALESCE(an.salida, an.salida_inactividad))), 0) / 3600, 2) AS horas_acumuladas
                FROM asistencias_net an
                LEFT JOIN ({$agenciasPorTerminalSql}) at
                    ON at.terminal_key = COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(COALESCE(NULLIF(an.terminal, ''), an.agencia) AS CHAR))), ''), '0')
                LEFT JOIN ({$agenciasPorCodigoSql}) aa
                    ON aa.agencia_key = COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(COALESCE(NULLIF(an.agencia, ''), an.terminal) AS CHAR))), ''), '0')
                LEFT JOIN ({$empleadosSql}) e
                    ON TRIM(e.cedula) = TRIM(an.identificacion)
                WHERE an.entrada >= ? AND an.entrada < DATE_ADD(?, INTERVAL 1 DAY)
                  AND an.entrada IS NOT NULL
                  AND COALESCE(an.salida, an.salida_inactividad) IS NOT NULL
                GROUP BY
                    COALESCE(at.empresa, aa.empresa, ''),
                    COALESCE(at.terminal, aa.terminal, TRIM(CAST(COALESCE(NULLIF(an.terminal, ''), an.agencia) AS CHAR))),
                    COALESCE(at.nombre_agencia, aa.nombre_agencia, an.banca, an.agencia),
                    COALESCE(at.ruta, aa.ruta, ''),
                    TRIM(COALESCE(NULLIF(e.nombre_empleado, ''), an.usuario, an.username, '')),
                    an.identificacion,
                    DATE(an.entrada)
        ";
        $bindings[] = $validated['fecha_inicio'];
        $bindings[] = $validated['fecha_fin'];

        $baseSql = "
            SELECT
                empresa,
                terminal,
                nombre_agencia,
                ruta,
                nombre_empleado,
                cedula,
                fecha,
                primer_login,
                ultimo_login,
                horas_acumuladas
            FROM (
                " . implode(' UNION ALL ', $uniones) . "
            ) novedades
        ";
        $search = trim((string) $request->input('search.value', ''));
        $detalle = $validated['detalle'] ?? 'todos';
        $whereConditions = [];
        $whereBindings = [];

        if ($search !== '') {
            $whereConditions[] = "(terminal LIKE ?
                OR nombre_agencia LIKE ?
                OR ruta LIKE ?
                OR empresa LIKE ?
                OR nombre_empleado LIKE ?
                OR cedula LIKE ?)";
            $searchValue = '%' . $search . '%';
            $whereBindings = array_fill(0, 6, $searchValue);
        }

        if ($validated['empresa'] === 'grupo_joselito') {
            $whereConditions[] = 'LOWER(COALESCE(empresa, "")) LIKE ?';
            $whereBindings[] = '%joselito%';
        }

        if ($validated['empresa'] === 'negosur') {
            $whereConditions[] = 'LOWER(COALESCE(empresa, "")) LIKE ?';
            $whereBindings[] = '%negosur%';
        }

        if ($detalle === 'cumple') {
            $whereConditions[] = 'horas_acumuladas >= ?';
            $whereBindings[] = (int) $validated['horas_requeridas'];
        }

        if ($detalle === 'tiene_falta') {
            $whereConditions[] = 'horas_acumuladas < ?';
            $whereBindings[] = (int) $validated['horas_requeridas'];
        }

        $whereSql = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        return compact('baseSql', 'bindings', 'whereSql', 'whereBindings');
    }

    private function getNovedadesHorarioRows(array $queryData, ?int $length = null, int $start = 0)
    {
        $limitSql = $length === null ? '' : "LIMIT {$length} OFFSET {$start}";
        $dataSql = "
            SELECT *
            FROM ({$queryData['baseSql']}) base
            {$queryData['whereSql']}
            ORDER BY fecha DESC, terminal, nombre_empleado
            {$limitSql}
        ";

        return collect(DB::select($dataSql, array_merge($queryData['bindings'], $queryData['whereBindings'])))
            ->map(function ($row) {
                $row->fecha = $row->fecha ? Carbon::parse($row->fecha)->format('Y-m-d') : null;
                $row->primer_login = $row->primer_login ? Carbon::parse($row->primer_login)->format('Y-m-d H:i:s') : null;
                $row->ultimo_login = $row->ultimo_login ? Carbon::parse($row->ultimo_login)->format('Y-m-d H:i:s') : null;
                $row->horas_acumuladas = round((float) $row->horas_acumuladas, 2);

                return $row;
            });
    }

    private function buildResumenPagoRows($rows, float $horasRequeridas, float $valorHora)
    {
        return $rows
            ->map(function ($row) use ($horasRequeridas, $valorHora) {
                $horasAcumuladas = round((float) ($row->horas_acumuladas ?? 0), 2);
                $horasFaltantes = round(max($horasRequeridas - $horasAcumuladas, 0), 2);

                if ($horasFaltantes <= 0) {
                    return null;
                }

                return [
                    'key' => implode('|', [
                        trim((string) ($row->cedula ?? '')),
                        trim((string) ($row->terminal ?? '')),
                        trim((string) ($row->nombre_agencia ?? '')),
                    ]),
                    'nombre' => trim((string) ($row->nombre_empleado ?? '')),
                    'cedula' => trim((string) ($row->cedula ?? '')),
                    'terminal' => trim((string) ($row->terminal ?? '')),
                    'nombre_agencia' => trim((string) ($row->nombre_agencia ?? '')),
                    'ruta' => trim((string) ($row->ruta ?? '')),
                    'horas_faltantes' => $horasFaltantes,
                    'monto_total' => round($horasFaltantes * $valorHora, 2),
                ];
            })
            ->filter()
            ->groupBy('key')
            ->map(function ($group) {
                $first = $group->first();
                $horasFaltantes = round((float) $group->sum('horas_faltantes'), 2);

                return [
                    'nombre' => $first['nombre'],
                    'cedula' => $first['cedula'],
                    'terminal' => $first['terminal'],
                    'nombre_agencia' => $first['nombre_agencia'],
                    'ruta' => $first['ruta'],
                    'total_horas' => $this->formatHorasMinutos($horasFaltantes),
                    'monto_total' => round((float) $group->sum('monto_total'), 2),
                ];
            })
            ->sortBy([
                ['ruta', 'asc'],
                ['nombre', 'asc'],
                ['terminal', 'asc'],
            ])
            ->values();
    }

    private function formatHorasMinutos(float $horasDecimal): string
    {
        $minutosTotales = (int) round(max($horasDecimal, 0) * 60);
        $horas = intdiv($minutosTotales, 60);
        $minutos = $minutosTotales % 60;
        $partes = [];

        if ($horas > 0) {
            $partes[] = $horas . ' ' . ($horas === 1 ? 'hora' : 'horas');
        }

        if ($minutos > 0 || $partes === []) {
            $partes[] = $minutos . ' ' . ($minutos === 1 ? 'minuto' : 'minutos');
        }

        return implode(' ', $partes);
    }

    public function detalle(Request $request)
    {
        $validated = $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'horas_requeridas' => ['required', 'integer', 'min:1'],
            'valor_hora' => ['required', 'numeric', 'min:0.01'],
            'cedula' => ['required', 'string'],
            'terminal' => ['required', 'string'],
            'empresa' => ['nullable', 'string', 'max:60'],
        ]);

        $uniones = [];
        $bindings = [];
        $agenciasPorTerminalSql = "
            SELECT
                COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(terminal AS CHAR))), ''), '0') AS terminal_key,
                MAX(TRIM(CAST(terminal AS CHAR))) AS terminal,
                MAX(nombre_agencia) AS nombre_agencia,
                MAX(ruta) AS ruta,
                MAX(empresa) AS empresa
            FROM agencias
            WHERE terminal IS NOT NULL
              AND TRIM(CAST(terminal AS CHAR)) <> ''
            GROUP BY COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(terminal AS CHAR))), ''), '0')
        ";
        $agenciasPorCodigoSql = "
            SELECT
                COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(agencia AS CHAR))), ''), '0') AS agencia_key,
                MAX(TRIM(CAST(terminal AS CHAR))) AS terminal,
                MAX(nombre_agencia) AS nombre_agencia,
                MAX(ruta) AS ruta,
                MAX(empresa) AS empresa
            FROM agencias
            WHERE agencia IS NOT NULL
              AND TRIM(CAST(agencia AS CHAR)) <> ''
            GROUP BY COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(agencia AS CHAR))), ''), '0')
        ";
        $empleadosSql = "
            SELECT
                TRIM(cedula) AS cedula,
                MAX(TRIM(CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, '')))) AS nombre_empleado
            FROM empleados
            WHERE cedula IS NOT NULL
              AND TRIM(cedula) <> ''
            GROUP BY TRIM(cedula)
        ";

        $uniones[] = "
                SELECT
                    COALESCE(at.empresa, aa.empresa, '') AS empresa,
                    COALESCE(at.terminal, aa.terminal, TRIM(CAST(ab.agencia_id AS CHAR))) AS terminal,
                    COALESCE(at.nombre_agencia, aa.nombre_agencia, ab.agencia_id) AS nombre_agencia,
                    COALESCE(at.ruta, aa.ruta, '') AS ruta,
                    TRIM(COALESCE(NULLIF(e.nombre_empleado, ''), ab.usuario, '')) AS nombre_empleado,
                    ab.cedula AS cedula,
                    DATE(ab.fecha) AS fecha,
                    MIN(ab.primer_login) AS primer_login,
                    MAX(ab.ultimo_login) AS ultimo_login,
                    ROUND(GREATEST(TIMESTAMPDIFF(SECOND, MIN(ab.primer_login), MAX(ab.ultimo_login)), 0) / 3600, 2) AS horas_acumuladas
                FROM asistencias_bet ab
                LEFT JOIN ({$agenciasPorTerminalSql}) at
                    ON at.terminal_key = COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(ab.agencia_id AS CHAR))), ''), '0')
                LEFT JOIN ({$agenciasPorCodigoSql}) aa
                    ON aa.agencia_key = COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(ab.agencia_id AS CHAR))), ''), '0')
                LEFT JOIN ({$empleadosSql}) e
                    ON TRIM(e.cedula) = TRIM(ab.cedula)
                WHERE ab.fecha >= ? AND ab.fecha < DATE_ADD(?, INTERVAL 1 DAY)
                  AND ab.primer_login IS NOT NULL
                  AND ab.ultimo_login IS NOT NULL
                GROUP BY
                    COALESCE(at.empresa, aa.empresa, ''),
                    COALESCE(at.terminal, aa.terminal, TRIM(CAST(ab.agencia_id AS CHAR))),
                    COALESCE(at.nombre_agencia, aa.nombre_agencia, ab.agencia_id),
                    COALESCE(at.ruta, aa.ruta, ''),
                    TRIM(COALESCE(NULLIF(e.nombre_empleado, ''), ab.usuario, '')),
                    ab.cedula,
                    DATE(ab.fecha)
        ";
        $bindings[] = $validated['fecha_inicio'];
        $bindings[] = $validated['fecha_fin'];

        $uniones[] = "
                SELECT
                    COALESCE(at.empresa, aa.empresa, '') AS empresa,
                    COALESCE(at.terminal, aa.terminal, TRIM(CAST(COALESCE(NULLIF(an.terminal, ''), an.agencia) AS CHAR))) AS terminal,
                    COALESCE(at.nombre_agencia, aa.nombre_agencia, an.banca, an.agencia) AS nombre_agencia,
                    COALESCE(at.ruta, aa.ruta, '') AS ruta,
                    TRIM(COALESCE(NULLIF(e.nombre_empleado, ''), an.usuario, an.username, '')) AS nombre_empleado,
                    an.identificacion AS cedula,
                    DATE(an.entrada) AS fecha,
                    MIN(an.entrada) AS primer_login,
                    MAX(COALESCE(an.salida, an.salida_inactividad)) AS ultimo_login,
                    ROUND(GREATEST(TIMESTAMPDIFF(SECOND, MIN(an.entrada), MAX(COALESCE(an.salida, an.salida_inactividad))), 0) / 3600, 2) AS horas_acumuladas
                FROM asistencias_net an
                LEFT JOIN ({$agenciasPorTerminalSql}) at
                    ON at.terminal_key = COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(COALESCE(NULLIF(an.terminal, ''), an.agencia) AS CHAR))), ''), '0')
                LEFT JOIN ({$agenciasPorCodigoSql}) aa
                    ON aa.agencia_key = COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(COALESCE(NULLIF(an.agencia, ''), an.terminal) AS CHAR))), ''), '0')
                LEFT JOIN ({$empleadosSql}) e
                    ON TRIM(e.cedula) = TRIM(an.identificacion)
                WHERE an.entrada >= ? AND an.entrada < DATE_ADD(?, INTERVAL 1 DAY)
                  AND an.entrada IS NOT NULL
                  AND COALESCE(an.salida, an.salida_inactividad) IS NOT NULL
                GROUP BY
                    COALESCE(at.empresa, aa.empresa, ''),
                    COALESCE(at.terminal, aa.terminal, TRIM(CAST(COALESCE(NULLIF(an.terminal, ''), an.agencia) AS CHAR))),
                    COALESCE(at.nombre_agencia, aa.nombre_agencia, an.banca, an.agencia),
                    COALESCE(at.ruta, aa.ruta, ''),
                    TRIM(COALESCE(NULLIF(e.nombre_empleado, ''), an.usuario, an.username, '')),
                    an.identificacion,
                    DATE(an.entrada)
        ";
        $bindings[] = $validated['fecha_inicio'];
        $bindings[] = $validated['fecha_fin'];

        $baseSql = "
            SELECT
                empresa,
                terminal,
                nombre_agencia,
                ruta,
                nombre_empleado,
                cedula,
                fecha,
                primer_login,
                ultimo_login,
                horas_acumuladas
            FROM (
                " . implode(' UNION ALL ', $uniones) . "
            ) novedades
        ";

        $whereConditions = [
            'TRIM(CAST(cedula AS CHAR)) = ?',
            'TRIM(CAST(terminal AS CHAR)) = ?',
        ];
        $whereBindings = [
            trim((string) $validated['cedula']),
            trim((string) $validated['terminal']),
        ];

        if (!empty($validated['empresa'])) {
            $whereConditions[] = 'empresa = ?';
            $whereBindings[] = $validated['empresa'];
        }

        $rows = collect(DB::select(
            "
                SELECT *
                FROM ({$baseSql}) base
                WHERE " . implode(' AND ', $whereConditions) . "
                ORDER BY fecha ASC
            ",
            array_merge($bindings, $whereBindings)
        ));

        $horasRequeridas = (float) $validated['horas_requeridas'];
        $valorHora = (float) $validated['valor_hora'];
        $detalle = $rows
            ->map(function ($row) use ($horasRequeridas, $valorHora) {
                $horasAcumuladas = round((float) $row->horas_acumuladas, 2);
                $horasFaltantes = round(max($horasRequeridas - $horasAcumuladas, 0), 2);
                $montoDia = round($horasFaltantes * $valorHora, 2);

                return [
                    'fecha' => $row->fecha ? Carbon::parse($row->fecha)->format('Y-m-d') : null,
                    'horas_acumuladas' => $horasAcumuladas,
                    'horas_faltantes' => $horasFaltantes,
                    'monto_dia' => $montoDia,
                ];
            })
            ->filter(fn ($row) => $row['horas_faltantes'] > 0)
            ->values();

        $primerRegistro = $rows->first();

        return response()->json([
            'nombre' => $primerRegistro->nombre_empleado ?? 'Sin especificar',
            'cedula' => $validated['cedula'],
            'agencia' => $primerRegistro->nombre_agencia ?? $validated['terminal'],
            'terminal' => $validated['terminal'],
            'total_faltantes' => round($detalle->sum('horas_faltantes'), 2),
            'monto_total' => round($detalle->sum('monto_dia'), 2),
            'detalle' => $detalle,
        ]);
    }
}
