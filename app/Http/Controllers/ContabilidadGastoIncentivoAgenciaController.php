<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReporteGastoIncentivoAgenciaRequest;
use App\Services\Contabilidad\DistribuidorIncentivoAgencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ContabilidadGastoIncentivoAgenciaController extends Controller
{
    public function __construct(
        private readonly IncentivosController $incentivosController,
        private readonly DistribuidorIncentivoAgencia $distribuidor,
    ) {}

    public function index(): View
    {
        return view('contabilidad.reportes.gastos_incentivo_agencia');
    }

    public function data(ReporteGastoIncentivoAgenciaRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['terminales_excluidas'] = $validated['terminales_excluidas']
            ?? json_encode($this->terminalesExcluidas(), JSON_THROW_ON_ERROR);
        $incentivoRequest = Request::create(
            $request->url(),
            'GET',
            array_merge($validated, [
                'filtro_cumplimiento' => 'todos',
                'modo_calculo' => 'general',
            ])
        );

        $incentivoResponse = $this->incentivosController->reporteNuevoIncentivoV5($incentivoRequest);
        $payload = $incentivoResponse->getData(true);

        if ($incentivoResponse->status() >= 400) {
            return response()->json($payload, $incentivoResponse->status());
        }

        $agentes = $this->agruparAgentes(collect($payload['data'] ?? []));
        $cedulas = $agentes->keys()->values();

        if ($cedulas->isEmpty()) {
            return response()->json([
                'data' => [],
                'meta' => $this->construirMeta($validated, collect(), $payload['meta'] ?? []),
            ]);
        }

        $ventas = $this->ventasPorAgencia($validated, $cedulas);
        $rows = $this->distribuirPorAgencias($agentes, $ventas);

        return response()->json([
            'data' => $rows->values(),
            'meta' => $this->construirMeta($validated, $rows, $payload['meta'] ?? []),
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<string, array<string, mixed>>
     */
    private function agruparAgentes(Collection $rows): Collection
    {
        return $rows
            ->filter(fn (array $row): bool => $this->cedulaKey($row['cedula'] ?? '') !== '0')
            ->filter(fn (array $row): bool => $this->distribuidor->incentivoGanadoCentavos($row) > 0)
            ->groupBy(fn (array $row): string => $this->cedulaKey($row['cedula'] ?? ''))
            ->map(function (Collection $agenteRows, string $cedula): array {
                $first = $agenteRows->first();

                return [
                    'cedula' => $cedula,
                    'empleadoid' => (string) ($first['empleadoid'] ?? ''),
                    'nombre' => (string) ($first['nombre'] ?? ''),
                    'ventas_calificacion' => $agenteRows->sum(
                        fn (array $row): float => $this->monto($row['ventas_mes_actual'] ?? 0)
                    ),
                    'incentivo_centavos' => $agenteRows->sum(
                        fn (array $row): int => $this->distribuidor->incentivoGanadoCentavos($row)
                    ),
                ];
            });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  Collection<int, string>  $cedulas
     * @return Collection<int, object>
     */
    private function ventasPorAgencia(array $validated, Collection $cedulas): Collection
    {
        $terminalesExcluidas = collect(json_decode((string) $validated['terminales_excluidas'], true))
            ->map(fn ($terminal): string => trim((string) $terminal))
            ->filter()
            ->unique()
            ->values();

        $buildQuery = function (string $table, string $sistema) use ($validated, $terminalesExcluidas, $cedulas) {
            $query = DB::table("{$table} as ventas")
                ->selectRaw(
                    'CAST(ventas.cedula AS UNSIGNED) AS cedula,
                    TRIM(CAST(ventas.agencia_id AS CHAR)) AS terminal,
                    SUM(ventas.monto) AS ventas,
                    ? AS sistema',
                    [$sistema]
                )
                ->whereBetween('ventas.fecha', [$validated['fecha_ini'], $validated['fecha_fin']])
                ->whereNotNull('ventas.cedula')
                ->whereNotNull('ventas.agencia_id')
                ->whereRaw("TRIM(CAST(ventas.agencia_id AS CHAR)) <> ''")
                ->whereIn(DB::raw('CAST(ventas.cedula AS UNSIGNED)'), $cedulas->all())
                ->groupByRaw('CAST(ventas.cedula AS UNSIGNED), TRIM(CAST(ventas.agencia_id AS CHAR))');

            if ($terminalesExcluidas->isNotEmpty()) {
                $query->whereNotIn(DB::raw('TRIM(CAST(ventas.agencia_id AS CHAR))'), $terminalesExcluidas->all());
            }

            return $query;
        };

        $sistema = $validated['sistema'] ?? 'Todos';
        $source = match ($sistema) {
            'Lotobet' => $buildQuery('vt_usuarios_bet', 'Lotobet'),
            'Lotonet' => $buildQuery('vt_usuarios_net', 'Lotonet'),
            default => $buildQuery('vt_usuarios_bet', 'Lotobet')
                ->unionAll($buildQuery('vt_usuarios_net', 'Lotonet')),
        };

        return DB::query()
            ->fromSub($source, 'distribucion')
            ->leftJoin('agencias as agencia', function ($join) {
                $join->whereRaw('TRIM(CAST(agencia.terminal AS CHAR)) = distribucion.terminal');
            })
            ->whereIn('distribucion.cedula', $cedulas->all())
            ->selectRaw(
                "distribucion.cedula,
                distribucion.terminal,
                COALESCE(NULLIF(TRIM(agencia.nombre_agencia), ''), NULLIF(TRIM(agencia.agencia), ''), 'SIN AGENCIA') AS agencia,
                COALESCE(NULLIF(TRIM(agencia.empresa), ''), 'SIN EMPRESA') AS empresa,
                SUM(distribucion.ventas) AS ventas"
            )
            ->groupBy('distribucion.cedula', 'distribucion.terminal', 'agencia.nombre_agencia', 'agencia.agencia', 'agencia.empresa')
            ->orderBy('agencia')
            ->get();
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $agentes
     * @param  Collection<int, object>  $ventas
     * @return Collection<int, array<string, mixed>>
     */
    private function distribuirPorAgencias(Collection $agentes, Collection $ventas): Collection
    {
        return $ventas
            ->groupBy(fn (object $row): string => $this->cedulaKey($row->cedula))
            ->flatMap(function (Collection $ventasAgente, string $cedula) use ($agentes): array {
                $agente = $agentes->get($cedula);
                if ($agente === null) {
                    return [];
                }

                $totalVentas = (float) $ventasAgente->sum('ventas');
                $incentivoEntero = (int) round(((int) $agente['incentivo_centavos']) / 100);
                $asignaciones = $this->distribuidor->distribuir(
                    $incentivoEntero,
                    $ventasAgente->pluck('ventas')->all()
                );

                return $ventasAgente
                    ->values()
                    ->map(function (object $venta, int $index) use ($agente, $asignaciones, $incentivoEntero, $totalVentas): array {
                        $montoVenta = (float) $venta->ventas;

                        return [
                            'cedula' => $agente['cedula'],
                            'empleadoid' => $agente['empleadoid'],
                            'nombre' => $agente['nombre'],
                            'terminal' => (string) $venta->terminal,
                            'agencia' => (string) $venta->agencia,
                            'empresa' => (string) $venta->empresa,
                            'ventas_total_cedula' => round((float) $agente['ventas_calificacion'], 2),
                            'ventas_agencia' => round($montoVenta, 2),
                            'participacion' => $totalVentas > 0 ? round(($montoVenta / $totalVentas) * 100, 6) : 0,
                            'incentivo_total_agente' => $incentivoEntero,
                            'incentivo_agencia' => $asignaciones[$index] ?? 0,
                        ];
                    })
                    ->all();
            })
            ->sortBy([
                ['agencia', 'asc'],
                ['nombre', 'asc'],
            ])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $incentivoMeta
     * @return array<string, mixed>
     */
    private function construirMeta(array $validated, Collection $rows, array $incentivoMeta): array
    {
        return [
            'fecha_ini' => $validated['fecha_ini'],
            'fecha_fin' => $validated['fecha_fin'],
            'sistema' => $validated['sistema'] ?? 'Todos',
            'total_agencias' => $rows->pluck('terminal')->unique()->count(),
            'total_agentes' => $rows->pluck('cedula')->unique()->count(),
            'total_ventas' => round((float) $rows->sum('ventas_agencia'), 2),
            'total_incentivo' => (int) $rows->sum('incentivo_agencia'),
            'incentivo_origen' => $this->monto($incentivoMeta['total_incentivo'] ?? 0),
        ];
    }

    private function cedulaKey(mixed $cedula): string
    {
        $digits = preg_replace('/\D+/', '', (string) $cedula);
        $normalized = ltrim($digits, '0');

        return $normalized === '' ? '0' : $normalized;
    }

    private function monto(mixed $value): float
    {
        return (float) str_replace(',', '', (string) $value);
    }

    /**
     * @return array<int, string>
     */
    private function terminalesExcluidas(): array
    {
        if (! Schema::hasTable('terminales_excluidas_incentivo')) {
            return [];
        }

        return DB::table('terminales_excluidas_incentivo')
            ->whereNotNull('terminal')
            ->pluck('terminal')
            ->map(fn ($terminal): string => trim((string) $terminal))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
