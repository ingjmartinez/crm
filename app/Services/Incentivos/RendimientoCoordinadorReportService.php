<?php

namespace App\Services\Incentivos;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RendimientoCoordinadorReportService
{
    public const META_USUARIO = 100001;

    public function generar(int $coordinadorId, array $filtros): array
    {
        $inicio = Carbon::parse($filtros['fecha_inicio'])->startOfDay();
        $fin = Carbon::parse($filtros['fecha_fin'])->startOfDay();
        $sistema = (string) ($filtros['sistema'] ?? 'Todos');
        $dias = $inicio->diffInDays($fin) + 1;
        $finAnterior = $inicio->copy()->subDay();
        $inicioAnterior = $finAnterior->copy()->subDays($dias - 1);
        $cacheKey = implode(':', [
            'rendimiento-coordinador-v1',
            $coordinadorId,
            $inicio->toDateString(),
            $fin->toDateString(),
            strtolower($sistema),
        ]);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use (
            $coordinadorId,
            $inicio,
            $fin,
            $inicioAnterior,
            $finAnterior,
            $sistema
        ) {
            $coordinador = DB::table('coordinador_operador')
                ->where('id', $coordinadorId)
                ->where('puesto', 'coordinador')
                ->select(['id', 'nombre', 'apellido'])
                ->first();

            abort_unless($coordinador, 404, 'El coordinador solicitado no existe.');

            $agencias = $this->agenciasAsignadas($coordinadorId, $sistema);
            $terminales = $agencias->pluck('terminal')->filter()->unique()->values();
            $ventas = $this->ventasPorUsuarioTerminal($inicio, $fin, $sistema, $terminales);
            $ventasAnteriores = $this->ventasPorUsuarioTerminal(
                $inicioAnterior,
                $finAnterior,
                $sistema,
                $terminales
            );
            $tendencia = $this->construirTendencia(
                $inicio,
                $fin,
                $inicioAnterior,
                $finAnterior,
                $sistema,
                $terminales
            );

            return $this->construirReporte(
                $coordinador,
                $agencias,
                $ventas,
                $ventasAnteriores,
                $tendencia,
                $inicio,
                $fin,
                $inicioAnterior,
                $finAnterior,
                $sistema
            );
        });
    }

    private function agenciasAsignadas(int $coordinadorId, string $sistema): Collection
    {
        return DB::table('coordinador_operador_agencia as coa')
            ->join('agencias as a', 'a.id', '=', 'coa.agencia_id')
            ->where('coa.coordinador_operador_id', $coordinadorId)
            ->when($sistema !== 'Todos', function ($query) use ($sistema) {
                $query->whereRaw("LOWER(TRIM(COALESCE(a.sistema, ''))) = LOWER(?)", [$sistema]);
            })
            ->selectRaw('a.id')
            ->selectRaw('TRIM(CAST(a.terminal AS CHAR)) AS terminal')
            ->selectRaw("COALESCE(NULLIF(TRIM(a.nombre_agencia), ''), NULLIF(TRIM(a.agencia), ''), 'Agencia sin nombre') AS agencia")
            ->selectRaw("COALESCE(NULLIF(TRIM(a.empresa), ''), 'Sin empresa') AS empresa")
            ->selectRaw("COALESCE(NULLIF(TRIM(a.sistema), ''), 'Sin sistema') AS sistema")
            ->addSelect('a.estatus')
            ->orderBy('agencia')
            ->get();
    }

    private function ventasPorUsuarioTerminal(
        Carbon $inicio,
        Carbon $fin,
        string $sistema,
        Collection $terminales
    ): Collection {
        if ($terminales->isEmpty()) {
            return collect();
        }

        $source = $this->ventasSource($inicio, $fin, $sistema, $terminales);

        return DB::query()
            ->fromSub($source, 'ventas')
            ->selectRaw('ventas.cedula, ventas.terminal, SUM(ventas.monto) AS monto')
            ->groupBy('ventas.cedula', 'ventas.terminal')
            ->get();
    }

    private function ventasSource(
        Carbon $inicio,
        Carbon $fin,
        string $sistema,
        Collection $terminales,
        bool $incluirFecha = false
    ): Builder {
        $build = function (string $tabla) use ($inicio, $fin, $terminales, $incluirFecha) {
            $query = DB::table($tabla)
                ->selectRaw('cedula, TRIM(CAST(agencia_id AS CHAR)) AS terminal, monto')
                ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
                ->whereIn('agencia_id', $terminales->all())
                ->whereNotNull('cedula')
                ->whereRaw("TRIM(CAST(cedula AS CHAR)) <> ''");

            if ($incluirFecha) {
                $query->addSelect('fecha');
            }

            return $query;
        };

        if ($sistema === 'Lotobet') {
            return $build('vt_usuarios_bet');
        }

        if ($sistema === 'Lotonet') {
            return $build('vt_usuarios_net');
        }

        return $build('vt_usuarios_bet')->unionAll($build('vt_usuarios_net'));
    }

    private function construirTendencia(
        Carbon $inicio,
        Carbon $fin,
        Carbon $inicioAnterior,
        Carbon $finAnterior,
        string $sistema,
        Collection $terminales
    ): array {
        if ($terminales->isEmpty()) {
            return [];
        }

        $actual = DB::query()
            ->fromSub($this->ventasSource($inicio, $fin, $sistema, $terminales, true), 'ventas')
            ->selectRaw('DATE(fecha) AS fecha, SUM(monto) AS monto')
            ->groupByRaw('DATE(fecha)')
            ->pluck('monto', 'fecha');
        $anterior = DB::query()
            ->fromSub($this->ventasSource($inicioAnterior, $finAnterior, $sistema, $terminales, true), 'ventas')
            ->selectRaw('DATE(fecha) AS fecha, SUM(monto) AS monto')
            ->groupByRaw('DATE(fecha)')
            ->pluck('monto', 'fecha');
        $fechasAnteriores = collect(CarbonPeriod::create($inicioAnterior, $finAnterior))
            ->map(fn ($fecha) => $fecha->toDateString())
            ->values();

        return collect(CarbonPeriod::create($inicio, $fin))
            ->values()
            ->map(function ($fecha, $indice) use ($actual, $anterior, $fechasAnteriores) {
                $fechaActual = $fecha->toDateString();
                $fechaAnterior = $fechasAnteriores->get($indice);

                return [
                    'fecha' => $fechaActual,
                    'etiqueta' => $fecha->format('d/m'),
                    'venta_actual' => round((float) ($actual[$fechaActual] ?? 0), 2),
                    'fecha_anterior' => $fechaAnterior,
                    'venta_anterior' => round((float) ($anterior[$fechaAnterior] ?? 0), 2),
                ];
            })
            ->all();
    }

    private function construirReporte(
        object $coordinador,
        Collection $agencias,
        Collection $ventas,
        Collection $ventasAnteriores,
        array $tendencia,
        Carbon $inicio,
        Carbon $fin,
        Carbon $inicioAnterior,
        Carbon $finAnterior,
        string $sistema
    ): array {
        $agenciasPorTerminal = $agencias->keyBy('terminal');
        $usuarios = [];
        $ventasAgencia = [];

        foreach ($ventas as $venta) {
            $cedula = $this->normalizarCedula($venta->cedula);
            $terminal = trim((string) $venta->terminal);
            if ($cedula === '' || !$agenciasPorTerminal->has($terminal)) {
                continue;
            }

            $monto = (float) $venta->monto;
            $usuarios[$cedula]['venta_total'] = ($usuarios[$cedula]['venta_total'] ?? 0) + $monto;
            $usuarios[$cedula]['agencias'][$terminal] = ($usuarios[$cedula]['agencias'][$terminal] ?? 0) + $monto;
            $ventasAgencia[$terminal]['total'] = ($ventasAgencia[$terminal]['total'] ?? 0) + $monto;
            $ventasAgencia[$terminal]['usuarios'][$cedula] = ($ventasAgencia[$terminal]['usuarios'][$cedula] ?? 0) + $monto;
        }

        $nombres = $this->nombresUsuarios(array_keys($usuarios));
        $usuariosReporte = collect($usuarios)->map(function ($data, $cedula) use ($nombres, $agenciasPorTerminal) {
            $venta = round((float) $data['venta_total'], 2);
            $cumple = $venta >= self::META_USUARIO;
            $agenciasUsuario = collect($data['agencias'])
                ->map(function ($monto, $terminal) use ($agenciasPorTerminal) {
                    $agencia = $agenciasPorTerminal->get($terminal);

                    return [
                        'terminal' => $terminal,
                        'agencia' => $agencia->agencia ?? 'Agencia sin nombre',
                        'monto' => round((float) $monto, 2),
                    ];
                })
                ->sortByDesc('monto')
                ->values()
                ->all();

            return [
                'cedula' => str_pad((string) $cedula, 11, '0', STR_PAD_LEFT),
                'nombre' => $nombres[$cedula] ?? 'Actualizar en maestro de empleados',
                'venta_total' => $venta,
                'avance_pct' => round(($venta / self::META_USUARIO) * 100, 2),
                'faltante' => round(max(self::META_USUARIO - $venta, 0), 2),
                'cumple' => $cumple,
                'clasificacion' => $this->clasificacion($venta),
                'incentivo' => $cumple ? $this->calcularIncentivo($venta) : 0,
                'agencias' => $agenciasUsuario,
                'agencia_principal' => $agenciasUsuario[0]['agencia'] ?? 'Sin venta asociada',
                'terminal_principal' => $agenciasUsuario[0]['terminal'] ?? '',
            ];
        })->sortByDesc('venta_total')->values()->map(function ($row, $indice) {
            $row['ranking'] = $indice + 1;

            return $row;
        });

        $usuariosPorCedula = $usuariosReporte->keyBy(fn ($row) => ltrim($row['cedula'], '0') ?: '0');
        $agenciasReporte = $agencias->map(function ($agencia) use ($ventasAgencia, $usuariosPorCedula) {
            $data = $ventasAgencia[$agencia->terminal] ?? ['total' => 0, 'usuarios' => []];
            $usuariosAgencia = collect($data['usuarios'] ?? []);
            $mejorCedula = (string) ($usuariosAgencia->sortDesc()->keys()->first() ?? '');
            $mejorUsuario = $usuariosPorCedula->get($mejorCedula);
            $cantidadUsuarios = $usuariosAgencia->count();
            $usuariosCumplieron = $usuariosAgencia->keys()
                ->filter(fn ($cedula) => (bool) ($usuariosPorCedula->get((string) $cedula)['cumple'] ?? false))
                ->count();

            return [
                'agencia_id' => (int) $agencia->id,
                'terminal' => (string) $agencia->terminal,
                'agencia' => (string) $agencia->agencia,
                'empresa' => (string) $agencia->empresa,
                'sistema' => (string) $agencia->sistema,
                'activa' => (int) $agencia->estatus === 1,
                'venta_total' => round((float) ($data['total'] ?? 0), 2),
                'usuarios' => $cantidadUsuarios,
                'usuarios_cumplieron' => $usuariosCumplieron,
                'cumplimiento_usuarios_pct' => $cantidadUsuarios > 0
                    ? round(($usuariosCumplieron / $cantidadUsuarios) * 100, 2)
                    : 0,
                'promedio_usuario' => $cantidadUsuarios > 0
                    ? round((float) ($data['total'] ?? 0) / $cantidadUsuarios, 2)
                    : 0,
                'mejor_usuario' => $mejorUsuario['nombre'] ?? 'Sin vendedores',
                'mejor_usuario_venta_agencia' => round((float) ($usuariosAgencia[$mejorCedula] ?? 0), 2),
                'tiene_usuario_meta' => $usuariosCumplieron > 0,
            ];
        })->sortByDesc('venta_total')->values()->map(function ($row, $indice) {
            $row['ranking'] = $indice + 1;

            return $row;
        });

        $ventaActual = round((float) $ventas->sum('monto'), 2);
        $ventaAnterior = round((float) $ventasAnteriores->sum('monto'), 2);
        $variacion = $ventaActual - $ventaAnterior;
        $usuariosCumplieron = $usuariosReporte->where('cumple', true)->count();
        $agenciasConMeta = $agenciasReporte->where('tiene_usuario_meta', true)->count();

        return [
            'meta' => [
                'coordinador_id' => (int) $coordinador->id,
                'coordinador' => trim((string) $coordinador->nombre . ' ' . (string) $coordinador->apellido)
                    ?: 'Coordinador sin nombre',
                'fecha_inicio' => $inicio->toDateString(),
                'fecha_fin' => $fin->toDateString(),
                'periodo' => $inicio->format('d/m/Y') . ' - ' . $fin->format('d/m/Y'),
                'periodo_anterior' => $inicioAnterior->format('d/m/Y') . ' - ' . $finAnterior->format('d/m/Y'),
                'sistema' => $sistema,
                'meta_usuario' => self::META_USUARIO,
                'generado' => now()->format('d/m/Y h:i A'),
            ],
            'resumen' => [
                'venta_total' => $ventaActual,
                'agencias_asignadas' => $agenciasReporte->count(),
                'agencias_activas' => $agenciasReporte->where('activa', true)->count(),
                'agencias_con_ventas' => $agenciasReporte->where('venta_total', '>', 0)->count(),
                'agencias_sin_ventas' => $agenciasReporte->where('venta_total', '<=', 0)->count(),
                'agencias_con_usuario_meta' => $agenciasConMeta,
                'cumplimiento_agencias_pct' => $agenciasReporte->count() > 0
                    ? round(($agenciasConMeta / $agenciasReporte->count()) * 100, 2)
                    : 0,
                'usuarios_vendedores' => $usuariosReporte->count(),
                'usuarios_cumplieron' => $usuariosCumplieron,
                'usuarios_no_cumplieron' => $usuariosReporte->count() - $usuariosCumplieron,
                'cumplimiento_usuarios_pct' => $usuariosReporte->count() > 0
                    ? round(($usuariosCumplieron / $usuariosReporte->count()) * 100, 2)
                    : 0,
                'incentivo_total' => round((float) $usuariosReporte->sum('incentivo'), 2),
                'promedio_agencia' => $agenciasReporte->count() > 0
                    ? round($ventaActual / $agenciasReporte->count(), 2)
                    : 0,
                'promedio_usuario' => $usuariosReporte->count() > 0
                    ? round($ventaActual / $usuariosReporte->count(), 2)
                    : 0,
            ],
            'comparacion' => [
                'venta_actual' => $ventaActual,
                'venta_anterior' => $ventaAnterior,
                'variacion' => round($variacion, 2),
                'variacion_pct' => $ventaAnterior > 0 ? round(($variacion / $ventaAnterior) * 100, 2) : null,
            ],
            'agencias' => $agenciasReporte->all(),
            'usuarios' => $usuariosReporte->all(),
            'tendencia' => $tendencia,
        ];
    }

    private function nombresUsuarios(array $cedulas): array
    {
        if (empty($cedulas)) {
            return [];
        }

        $castType = DB::connection()->getDriverName() === 'sqlite' ? 'INTEGER' : 'UNSIGNED';
        $rows = collect();
        foreach (array_chunk(array_map('intval', $cedulas), 1000) as $chunk) {
            $rows = $rows->merge(
                DB::table('empleados')
                    ->whereIn(DB::raw("CAST(cedula AS {$castType})"), $chunk)
                    ->select(['cedula', 'nombres', 'apellidos'])
                    ->get()
            );
        }

        return $rows
            ->groupBy(fn ($row) => $this->normalizarCedula($row->cedula))
            ->map(fn ($rows) => $rows
                ->map(fn ($row) => trim((string) $row->nombres . ' ' . (string) $row->apellidos))
                ->filter()
                ->sortDesc()
                ->first())
            ->filter()
            ->all();
    }

    private function clasificacion(float $venta): string
    {
        $porcentaje = ($venta / self::META_USUARIO) * 100;

        return match (true) {
            $porcentaje >= 150 => 'Excelente',
            $porcentaje >= 100 => 'Cumple',
            $porcentaje >= 80 => 'Cerca',
            $porcentaje >= 50 => 'En seguimiento',
            default => 'Crítico',
        };
    }

    private function calcularIncentivo(float $ventas): int
    {
        foreach ([
            [100001, 250000, 1000],
            [250001, 400000, 2000],
            [400001, 550000, 4000],
            [550001, 700000, 6000],
            [700001, 850000, 8000],
            [850001, 1000000, 10000],
        ] as [$desde, $hasta, $pago]) {
            if ($ventas >= $desde && $ventas <= $hasta) {
                return $pago;
            }
        }

        return $ventas >= 1000001 ? (int) round($ventas * 0.005) : 0;
    }

    private function normalizarCedula($cedula): string
    {
        $cedula = preg_replace('/\D+/', '', (string) $cedula);
        $cedula = ltrim((string) $cedula, '0');

        return $cedula === '' ? '0' : $cedula;
    }
}
