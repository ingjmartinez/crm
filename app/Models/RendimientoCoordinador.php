<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RendimientoCoordinador extends Model
{
    private const META_MINIMA_VENTA = 100001;

    protected $table = 'coordinador_operador';

    public $timestamps = false;

    /**
     * Construye el reporte sin depender del controlador ni de la vista de Incentivo V5.
     */
    public function generar(array $filtros): array
    {
        $fechaInicio = (string) $filtros['fecha_inicio'];
        $fechaFin = (string) $filtros['fecha_fin'];
        $sistema = (string) ($filtros['sistema'] ?? 'Todos');
        $ventaMinima = self::META_MINIMA_VENTA;
        $diasMinimos = 1;

        $agencias = $this->agenciasEvaluables($sistema);
        $ventas = $this->ventasPorUsuarioTerminal($fechaInicio, $fechaFin, $sistema);

        $agenciasPorTerminal = $agencias
            ->filter(fn ($agencia) => trim((string) $agencia->terminal) !== '')
            ->keyBy(fn ($agencia) => trim((string) $agencia->terminal));
        $agenciasPorId = $agencias->keyBy(fn ($agencia) => (string) $agencia->id);

        $ventasRegistradas = $ventas
            ->filter(fn ($venta) => $agenciasPorTerminal->has(trim((string) $venta->terminal)))
            ->values();

        $cumplimientoUsuarios = $this->calcularCumplimientoUsuarios(
            $ventasRegistradas,
            $ventaMinima,
            $diasMinimos
        );

        $ventasPorAgencia = [];
        foreach ($ventasRegistradas as $venta) {
            $terminal = trim((string) $venta->terminal);
            $agencia = $agenciasPorTerminal->get($terminal);
            if (!$agencia) {
                continue;
            }

            $agenciaId = (string) $agencia->id;
            $cedula = $this->normalizarCedula($venta->cedula);
            $ventasPorAgencia[$agenciaId]['usuarios'][$cedula] =
                ($ventasPorAgencia[$agenciaId]['usuarios'][$cedula] ?? 0) + (float) $venta->monto;
            $ventasPorAgencia[$agenciaId]['monto'] = ($ventasPorAgencia[$agenciaId]['monto'] ?? 0)
                + (float) $venta->monto;
        }

        $nombresUsuarios = $this->nombresUsuarios(array_keys($cumplimientoUsuarios));

        $asignaciones = DB::table('coordinador_operador as co')
            ->join('coordinador_operador_agencia as coa', 'coa.coordinador_operador_id', '=', 'co.id')
            ->join('agencias as a', 'a.id', '=', 'coa.agencia_id')
            ->where('co.puesto', 'coordinador')
            ->when($sistema !== 'Todos', function ($query) use ($sistema) {
                $query->whereRaw("LOWER(TRIM(COALESCE(a.sistema, ''))) = LOWER(?)", [$sistema]);
            })
            ->selectRaw("co.id AS coordinador_id")
            ->selectRaw("TRIM(CONCAT(COALESCE(co.nombre, ''), ' ', COALESCE(co.apellido, ''))) AS coordinador")
            ->selectRaw('a.id AS agencia_id')
            ->get();

        $coordinadoresBase = DB::table('coordinador_operador')
            ->where('puesto', 'coordinador')
            ->selectRaw('id AS coordinador_id')
            ->selectRaw("TRIM(CONCAT(COALESCE(nombre, ''), ' ', COALESCE(apellido, ''))) AS coordinador")
            ->orderBy('coordinador')
            ->get();

        $asignacionesPorCoordinador = $asignaciones->groupBy('coordinador_id');
        $coordinadores = $coordinadoresBase
            ->map(function ($coordinador) use (
                $asignacionesPorCoordinador,
                $agenciasPorId,
                $ventasPorAgencia,
                $cumplimientoUsuarios,
                $nombresUsuarios
            ) {
                $asignadas = $asignacionesPorCoordinador
                    ->get($coordinador->coordinador_id, collect())
                    ->pluck('agencia_id')
                    ->map(fn ($id) => (string) $id)
                    ->unique()
                    ->values();

                $agenciasCumplidas = 0;
                $usuariosVendedores = [];
                $detalleAgenciasCumplieron = [];
                $detalleAgenciasNoCumplieron = [];
                $detalleUsuariosCumplieron = [];
                $detalleUsuariosNoCumplieron = [];

                foreach ($asignadas as $agenciaId) {
                    $usuariosAgencia = array_keys($ventasPorAgencia[$agenciaId]['usuarios'] ?? []);
                    $agenciaCumple = false;
                    $agencia = $agenciasPorId->get($agenciaId);

                    foreach ($usuariosAgencia as $cedula) {
                        $usuariosVendedores[$cedula] = true;
                        $usuarioCumple = ($cumplimientoUsuarios[$cedula]['cumple'] ?? false) === true;
                        if ($usuarioCumple) {
                            $agenciaCumple = true;
                        }

                        $detalleUsuario = [
                            'terminal' => trim((string) ($agencia->terminal ?? '')),
                            'agencia' => trim((string) ($agencia->nombre_agencia ?? '')) ?: 'Agencia sin nombre',
                            'cedula' => str_pad((string) $cedula, 11, '0', STR_PAD_LEFT),
                            'nombre' => $nombresUsuarios[$cedula] ?? 'Actualizar en maestro de empleados',
                            'monto_vendido' => round((float) ($ventasPorAgencia[$agenciaId]['usuarios'][$cedula] ?? 0), 2),
                            'incentivo_ganado' => (int) ($cumplimientoUsuarios[$cedula]['incentivo'] ?? 0),
                            'venta_total_usuario' => round((float) ($cumplimientoUsuarios[$cedula]['ventas'] ?? 0), 2),
                            'faltante_regla' => round(max(
                                self::META_MINIMA_VENTA - (float) ($cumplimientoUsuarios[$cedula]['ventas'] ?? 0),
                                0
                            ), 2),
                            'faltante_pct' => round(max(
                                ((self::META_MINIMA_VENTA - (float) ($cumplimientoUsuarios[$cedula]['ventas'] ?? 0))
                                    / self::META_MINIMA_VENTA) * 100,
                                0
                            ), 2),
                        ];

                        if ($usuarioCumple) {
                            $detalleUsuariosCumplieron[] = $detalleUsuario;
                        } else {
                            $detalleUsuariosNoCumplieron[] = $detalleUsuario;
                        }
                    }

                    $detalleAgencia = [
                        'terminal' => trim((string) ($agencia->terminal ?? '')),
                        'agencia' => trim((string) ($agencia->nombre_agencia ?? '')) ?: 'Agencia sin nombre',
                    ];

                    $mejorVentaUsuario = (float) collect($usuariosAgencia)
                        ->map(fn ($cedula) => (float) ($cumplimientoUsuarios[$cedula]['ventas'] ?? 0))
                        ->max();
                    $detalleAgencia['mejor_venta_usuario'] = round($mejorVentaUsuario, 2);
                    $detalleAgencia['faltante_regla'] = round(max(
                        self::META_MINIMA_VENTA - $mejorVentaUsuario,
                        0
                    ), 2);
                    $detalleAgencia['avance_pct'] = round(min(
                        ($mejorVentaUsuario / self::META_MINIMA_VENTA) * 100,
                        100
                    ), 2);

                    if ($agenciaCumple) {
                        $agenciasCumplidas++;
                        $detalleAgenciasCumplieron[] = $detalleAgencia;
                    } else {
                        $detalleAgenciasNoCumplieron[] = $detalleAgencia;
                    }
                }

                $usuarios = array_keys($usuariosVendedores);
                $usuariosCumplieron = collect($usuarios)
                    ->filter(fn ($cedula) => ($cumplimientoUsuarios[$cedula]['cumple'] ?? false) === true)
                    ->count();

                return [
                    'coordinador_id' => (int) $coordinador->coordinador_id,
                    'coordinador' => trim((string) $coordinador->coordinador) ?: 'Coordinador sin nombre',
                    'agencias_asignadas' => $asignadas->count(),
                    'agencias_cumplieron' => $agenciasCumplidas,
                    'agencias_no_cumplieron' => $asignadas->count() - $agenciasCumplidas,
                    'usuarios_cumplieron' => $usuariosCumplieron,
                    'usuarios_no_cumplieron' => count($usuarios) - $usuariosCumplieron,
                    'detalle_agencias_cumplieron' => collect($detalleAgenciasCumplieron)->sortBy('terminal')->values()->all(),
                    'detalle_agencias_no_cumplieron' => collect($detalleAgenciasNoCumplieron)->sortBy('terminal')->values()->all(),
                    'detalle_usuarios_cumplieron' => collect($detalleUsuariosCumplieron)
                        ->sortBy(fn ($row) => $row['terminal'] . '|' . $row['cedula'])
                        ->values()
                        ->all(),
                    'detalle_usuarios_no_cumplieron' => collect($detalleUsuariosNoCumplieron)
                        ->sortBy(fn ($row) => $row['terminal'] . '|' . $row['cedula'])
                        ->values()
                        ->all(),
                ];
            })
            ->values();

        $agenciasAsignadasIds = $asignaciones
            ->pluck('agencia_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->flip();

        $agenciasSinCoordinador = $agencias
            ->reject(fn ($agencia) => $agenciasAsignadasIds->has((string) $agencia->id))
            ->map(function ($agencia) use ($ventasPorAgencia) {
                $ventaAgencia = $ventasPorAgencia[(string) $agencia->id] ?? [];

                return [
                    'agencia_id' => (int) $agencia->id,
                    'terminal' => trim((string) $agencia->terminal),
                    'agencia' => trim((string) $agencia->nombre_agencia) ?: 'Agencia sin nombre',
                    'empresa' => trim((string) $agencia->empresa) ?: 'Sin empresa',
                    'sistema' => trim((string) $agencia->sistema) ?: 'Sin sistema',
                    'estatus' => (int) ($agencia->estatus ?? 0),
                    'usuarios_vendedores' => count($ventaAgencia['usuarios'] ?? []),
                    'total_vendido' => round((float) ($ventaAgencia['monto'] ?? 0), 2),
                ];
            })
            ->sortByDesc('total_vendido')
            ->values();

        return [
            'coordinadores' => $coordinadores,
            'agencias_sin_coordinador' => $agenciasSinCoordinador,
            'resumen' => [
                'coordinadores' => $coordinadores->count(),
                'agencias_asignadas' => $coordinadores->sum('agencias_asignadas'),
                'agencias_cumplieron' => $coordinadores->sum('agencias_cumplieron'),
                'agencias_no_cumplieron' => $coordinadores->sum('agencias_no_cumplieron'),
                'usuarios_cumplieron' => $coordinadores->sum('usuarios_cumplieron'),
                'usuarios_no_cumplieron' => $coordinadores->sum('usuarios_no_cumplieron'),
                'agencias_sin_coordinador' => $agenciasSinCoordinador->count(),
                'agencias_sin_coordinador_con_ventas' => $agenciasSinCoordinador
                    ->where('total_vendido', '>', 0)
                    ->count(),
            ],
        ];
    }

    private function agenciasEvaluables(string $sistema): Collection
    {
        return DB::table('agencias')
            ->when($sistema !== 'Todos', function ($query) use ($sistema) {
                $query->whereRaw("LOWER(TRIM(COALESCE(sistema, ''))) = LOWER(?)", [$sistema]);
            })
            ->selectRaw('id, TRIM(CAST(terminal AS CHAR)) AS terminal')
            ->selectRaw("COALESCE(NULLIF(TRIM(nombre_agencia), ''), NULLIF(TRIM(agencia), ''), 'Agencia sin nombre') AS nombre_agencia")
            ->selectRaw("COALESCE(NULLIF(TRIM(empresa), ''), 'Sin empresa') AS empresa")
            ->selectRaw("COALESCE(NULLIF(TRIM(sistema), ''), 'Sin sistema') AS sistema")
            ->addSelect('estatus')
            ->get();
    }

    private function ventasPorUsuarioTerminal(string $fechaInicio, string $fechaFin, string $sistema): Collection
    {
        $buildQuery = function (string $tabla) use ($fechaInicio, $fechaFin) {
            return DB::table($tabla)
                ->selectRaw('cedula, TRIM(CAST(agencia_id AS CHAR)) AS terminal, fecha, monto')
                ->whereBetween('fecha', [$fechaInicio, $fechaFin])
                ->whereNotNull('cedula')
                ->whereRaw("TRIM(CAST(cedula AS CHAR)) <> ''")
                ->whereNotNull('agencia_id')
                ->whereRaw("TRIM(CAST(agencia_id AS CHAR)) <> ''");
        };

        if ($sistema === 'Lotobet') {
            $source = $buildQuery('vt_usuarios_bet');
        } elseif ($sistema === 'Lotonet') {
            $source = $buildQuery('vt_usuarios_net');
        } else {
            $source = $buildQuery('vt_usuarios_bet')
                ->unionAll($buildQuery('vt_usuarios_net'));
        }

        return DB::query()
            ->fromSub($source, 'ventas')
            ->selectRaw('ventas.cedula, ventas.terminal, SUM(ventas.monto) AS monto')
            ->selectRaw("GROUP_CONCAT(DISTINCT DATE(ventas.fecha) ORDER BY DATE(ventas.fecha) SEPARATOR ',') AS fechas_venta")
            ->groupBy('ventas.cedula', 'ventas.terminal')
            ->get();
    }

    private function calcularCumplimientoUsuarios(Collection $ventas, float $ventaMinima, int $diasMinimos): array
    {
        $usuarios = [];

        foreach ($ventas as $venta) {
            $cedula = $this->normalizarCedula($venta->cedula);
            if ($cedula === '') {
                continue;
            }

            $usuarios[$cedula]['ventas'] = ($usuarios[$cedula]['ventas'] ?? 0) + (float) $venta->monto;
            foreach (explode(',', (string) ($venta->fechas_venta ?? '')) as $fechaVenta) {
                $fechaVenta = trim($fechaVenta);
                if ($fechaVenta !== '') {
                    $usuarios[$cedula]['dias'][$fechaVenta] = true;
                }
            }
        }

        foreach ($usuarios as &$usuario) {
            $usuario['dias_venta'] = count($usuario['dias'] ?? []);
            $usuario['cumple'] = $usuario['ventas'] >= $ventaMinima
                && $usuario['dias_venta'] >= $diasMinimos;
            $usuario['incentivo'] = $usuario['cumple']
                ? $this->calcularIncentivo((float) $usuario['ventas'])
                : 0;
            unset($usuario['dias']);
        }
        unset($usuario);

        return $usuarios;
    }

    private function calcularIncentivo(float $ventas): int
    {
        $rangos = [
            ['desde' => 100001, 'hasta' => 250000, 'pago' => 1000],
            ['desde' => 250001, 'hasta' => 400000, 'pago' => 2000],
            ['desde' => 400001, 'hasta' => 550000, 'pago' => 4000],
            ['desde' => 550001, 'hasta' => 700000, 'pago' => 6000],
            ['desde' => 700001, 'hasta' => 850000, 'pago' => 8000],
            ['desde' => 850001, 'hasta' => 1000000, 'pago' => 10000],
        ];

        foreach ($rangos as $rango) {
            if ($ventas >= $rango['desde'] && $ventas <= $rango['hasta']) {
                return $rango['pago'];
            }
        }

        return $ventas >= 1000001 ? (int) round($ventas * 0.005) : 0;
    }

    private function nombresUsuarios(array $cedulas): array
    {
        $cedulas = collect($cedulas)->filter()->unique()->values();
        if ($cedulas->isEmpty()) {
            return [];
        }

        $nombres = collect();
        foreach ($cedulas->chunk(1000) as $chunk) {
            $rows = DB::table('empleados')
                ->whereIn(DB::raw('CAST(cedula AS UNSIGNED)'), $chunk->all())
                ->selectRaw('CAST(cedula AS UNSIGNED) AS cedula')
                ->selectRaw("MAX(TRIM(CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, '')))) AS nombre")
                ->groupByRaw('CAST(cedula AS UNSIGNED)')
                ->get();

            $nombres = $nombres->merge($rows);
        }

        return $nombres
            ->mapWithKeys(function ($row) {
                $cedula = $this->normalizarCedula($row->cedula);
                $nombre = trim((string) $row->nombre);

                return [$cedula => $nombre !== '' ? $nombre : 'Actualizar en maestro de empleados'];
            })
            ->all();
    }

    private function normalizarCedula($cedula): string
    {
        $cedula = preg_replace('/\D+/', '', (string) $cedula);
        if ($cedula === '') {
            return '';
        }

        $cedula = ltrim($cedula, '0');

        return $cedula === '' ? '0' : $cedula;
    }
}
