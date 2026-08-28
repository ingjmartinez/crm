<?php

namespace App\Http\Controllers\Gerencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultarGerencialRequest;
use App\Models\Agencia;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GerencialController extends Controller
{
    public function index(ConsultarGerencialRequest $request): View
    {
        $anio = $this->normalizeYear($request->query('anio'));
        $mesInicio = $this->normalizeMonth($request->query('mes_inicio'));
        $mesFin = $this->normalizeMonth($request->query('mes_fin'));
        $empresa = trim((string) ($request->validated('empresa') ?? ''));
        $configuracion = $this->resolveThresholdConfig($request);

        return view('gerencia.gerencial', [
            'anioSeleccionado' => $anio,
            'mesInicioSeleccionado' => $mesInicio,
            'mesFinSeleccionado' => $mesFin,
            'empresaSeleccionada' => $empresa,
            'empresas' => $this->empresasDisponibles(),
            'configuracionClasificacion' => $configuracion,
        ]);
    }

    public function data(ConsultarGerencialRequest $request): JsonResponse
    {
        $anio = $this->normalizeYear($request->query('anio'));
        $mesInicio = $this->normalizeMonth($request->query('mes_inicio'));
        $mesFin = $this->normalizeMonth($request->query('mes_fin'));
        $empresa = trim((string) ($request->validated('empresa') ?? ''));
        $configuracion = $this->resolveThresholdConfig($request);

        if ($mesInicio === null || $mesFin === null || $mesInicio === $mesFin) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'anio' => $anio,
                    'mes_inicio' => $mesInicio,
                    'mes_fin' => $mesFin,
                    'empresa' => $empresa,
                    'configuracion' => $configuracion,
                ],
            ]);
        }

        set_time_limit(300);

        $ventasPorAgenciaMes = $this->ventasAgenciasPorMes($anio, $mesInicio, $mesFin, $empresa);
        $clasificadas = $this->clasificarVentasAgencias($ventasPorAgenciaMes, $configuracion['agencia']);
        $resultados = $this->resumenClasificacionesAgencias($clasificadas, $mesInicio, $mesFin);
        $transicionesAgencias = $this->transicionesAgencias($clasificadas, $mesInicio, $mesFin);
        $detalleTransicionesAgencias = $this->detalleTransicionesAgencias($clasificadas, $mesInicio, $mesFin);

        return response()->json([
            'data' => $resultados,
            'transiciones_agencias' => $transicionesAgencias,
            'transiciones_agencias_detalle' => $detalleTransicionesAgencias,
            'meta' => [
                'anio' => $anio,
                'mes_inicio' => $mesInicio,
                'mes_fin' => $mesFin,
                'empresa' => $empresa,
                'configuracion' => $configuracion,
            ],
        ]);
    }

    private function normalizeYear($value): int
    {
        $year = (int) $value;

        if ($year < 2000 || $year > 2100) {
            return (int) now()->year;
        }

        return $year;
    }

    private function normalizeMonth($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $month = (int) $value;
        if ($month < 1 || $month > 12) {
            return null;
        }

        return $month;
    }

    private function ventasAgenciasPorMes(int $anio, int $mesInicio, int $mesFin, string $empresa): Collection
    {
        $inicioDesde = Carbon::create($anio, $mesInicio, 1)->startOfDay();
        $inicioHasta = $inicioDesde->copy()->addMonthNoOverflow();
        $finDesde = Carbon::create($anio, $mesFin, 1)->startOfDay();
        $finHasta = $finDesde->copy()->addMonthNoOverflow();
        $betIndexHint = $this->ventasAgenciaIndexHint(
            'vt_usuarios_bet',
            'idx_vt_bet_fecha_agencia_monto',
            'idx_vt_bet_fecha_agencia'
        );
        $netIndexHint = $this->ventasAgenciaIndexHint(
            'vt_usuarios_net',
            'idx_vt_net_fecha_agencia_monto',
            'idx_vt_net_fecha_agencia'
        );

        $sql = <<<SQL
SELECT agencia_id, mes, SUM(monto) AS total_ventas
FROM (
    SELECT agencia_id, ? AS mes, SUM(monto) AS monto
    FROM vt_usuarios_bet {$betIndexHint}
    WHERE fecha >= ? AND fecha < ?
    GROUP BY agencia_id

    UNION ALL

    SELECT agencia_id, ? AS mes, SUM(monto) AS monto
    FROM vt_usuarios_net {$netIndexHint}
    WHERE fecha >= ? AND fecha < ?
    GROUP BY agencia_id

    UNION ALL

    SELECT agencia_id, ? AS mes, SUM(monto) AS monto
    FROM vt_usuarios_bet {$betIndexHint}
    WHERE fecha >= ? AND fecha < ?
    GROUP BY agencia_id

    UNION ALL

    SELECT agencia_id, ? AS mes, SUM(monto) AS monto
    FROM vt_usuarios_net {$netIndexHint}
    WHERE fecha >= ? AND fecha < ?
    GROUP BY agencia_id
) ventas
GROUP BY agencia_id, mes
SQL;

        $ventas = collect(DB::select($sql, [
            $mesInicio,
            $inicioDesde->toDateTimeString(),
            $inicioHasta->toDateTimeString(),
            $mesInicio,
            $inicioDesde->toDateTimeString(),
            $inicioHasta->toDateTimeString(),
            $mesFin,
            $finDesde->toDateTimeString(),
            $finHasta->toDateTimeString(),
            $mesFin,
            $finDesde->toDateTimeString(),
            $finHasta->toDateTimeString(),
        ]));

        return $this->filtrarVentasPorEmpresa($ventas, $empresa);
    }

    /**
     * @param  Collection<int, object>  $ventas
     * @return Collection<int, object>
     */
    private function filtrarVentasPorEmpresa(Collection $ventas, string $empresa): Collection
    {
        if ($empresa === '') {
            return $ventas;
        }

        $terminales = Agencia::query()
            ->whereNotNull('terminal')
            ->whereRaw('TRIM(empresa) = ?', [$empresa])
            ->pluck('terminal')
            ->map(fn (mixed $terminal): string => $this->normalizarTerminal($terminal))
            ->flip();

        return $ventas
            ->filter(fn (object $venta): bool => $terminales->has(
                $this->normalizarTerminal($venta->agencia_id ?? '')
            ))
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function empresasDisponibles(): Collection
    {
        return Agencia::query()
            ->whereNotNull('empresa')
            ->whereRaw("TRIM(empresa) <> ''")
            ->orderBy('empresa')
            ->pluck('empresa')
            ->map(fn (mixed $empresa): string => trim((string) $empresa))
            ->unique()
            ->values();
    }

    private function normalizarTerminal(mixed $terminal): string
    {
        $normalizada = ltrim(trim((string) $terminal), '0');

        return $normalizada === '' ? '0' : $normalizada;
    }

    private function clasificarVentasAgencias($ventasPorAgenciaMes, array $umbrales)
    {
        return $ventasPorAgenciaMes
            ->map(function ($row) use ($umbrales) {
                $total = (float) ($row->total_ventas ?? 0);

                return [
                    'agencia_id' => (string) ($row->agencia_id ?? ''),
                    'mes' => (int) ($row->mes ?? 0),
                    'total_ventas' => $total,
                    'categoria' => $this->clasificarMontoAgencia($total, $umbrales),
                ];
            })
            ->filter(fn ($row) => $row['agencia_id'] !== '' && $row['mes'] > 0)
            ->values();
    }

    private function resumenClasificacionesAgencias($clasificadas, int $mesInicio, int $mesFin)
    {
        $orden = $this->ordenCategoriasAgencia();

        return collect($orden)
            ->map(function (string $categoria) use ($clasificadas, $mesInicio, $mesFin) {
                $inicio = $clasificadas
                    ->where('mes', $mesInicio)
                    ->where('categoria', $categoria)
                    ->count();
                $fin = $clasificadas
                    ->where('mes', $mesFin)
                    ->where('categoria', $categoria)
                    ->count();
                $crecimiento = $fin - $inicio;

                return [
                    'tipo_conteo' => 'AGENCIA',
                    'clasificacion' => $categoria,
                    'conteo_mes_inicio' => $inicio,
                    'conteo_mes_fin' => $fin,
                    'crecimiento' => $crecimiento,
                    'porc_crecimiento' => $inicio > 0
                        ? round(($crecimiento / $inicio) * 100, 2)
                        : null,
                ];
            })
            ->values();
    }

    private function transicionesAgencias($clasificadas, int $mesInicio, int $mesFin)
    {
        $porAgenciaMes = $clasificadas->groupBy('agencia_id');

        return $porAgenciaMes
            ->map(function ($items) use ($mesInicio, $mesFin) {
                $inicio = $items->firstWhere('mes', $mesInicio);

                if (! $inicio) {
                    return null;
                }

                $fin = $items->firstWhere('mes', $mesFin);

                return [
                    'categoria_inicio' => $inicio['categoria'],
                    'categoria_fin' => $fin['categoria'] ?? 'D',
                ];
            })
            ->filter()
            ->groupBy(fn ($item) => $item['categoria_inicio'].'|'.$item['categoria_fin'])
            ->map(function ($items, $key) {
                [$inicio, $fin] = explode('|', $key);

                return [
                    'categoria_inicio' => $inicio,
                    'categoria_fin' => $fin,
                    'total' => $items->count(),
                ];
            })
            ->sortBy(function ($item) {
                $orden = array_flip($this->ordenCategoriasAgencia());

                return (($orden[$item['categoria_inicio']] ?? 99) * 10) + ($orden[$item['categoria_fin']] ?? 99);
            })
            ->values();
    }

    private function detalleTransicionesAgencias($clasificadas, int $mesInicio, int $mesFin)
    {
        $inicioPorAgencia = $clasificadas
            ->where('mes', $mesInicio)
            ->keyBy('agencia_id');
        $finPorAgencia = $clasificadas
            ->where('mes', $mesFin)
            ->keyBy('agencia_id');
        $agencias = DB::table('agencias')
            ->whereNotNull('terminal')
            ->select('terminal', 'nombre_agencia', 'ciudad')
            ->get()
            ->keyBy(fn ($row): string => $this->normalizarTerminal($row->terminal));
        $orden = array_flip($this->ordenCategoriasAgencia());

        return $inicioPorAgencia
            ->map(function ($inicio, $agenciaId) use ($finPorAgencia, $agencias) {
                $agencia = $agencias->get($this->normalizarTerminal($agenciaId));
                $ciudad = trim((string) ($agencia->ciudad ?? ''));

                return [
                    'codigo_agencia' => (string) $agenciaId,
                    'nombre_agencia' => $agencia && trim((string) $agencia->nombre_agencia) !== ''
                        ? (string) $agencia->nombre_agencia
                        : 'Agencia '.$agenciaId,
                    'ciudad' => $ciudad !== '' ? $ciudad : 'Sin ciudad registrada',
                    'categoria_inicio' => $inicio['categoria'],
                    'categoria_fin' => $finPorAgencia->get($agenciaId)['categoria'] ?? 'D',
                ];
            })
            ->sortBy(function ($item) use ($orden) {
                return (($orden[$item['categoria_inicio']] ?? 99) * 10)
                    + ($orden[$item['categoria_fin']] ?? 99)
                    .'|'.$item['nombre_agencia']
                    .'|'.$item['codigo_agencia'];
            })
            ->values();
    }

    private function ventasAgenciaIndexHint(string $table, string $coveringIndex, string $fallbackIndex): string
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return '';
        }

        static $cache = [];

        foreach ([$coveringIndex, $fallbackIndex] as $indexName) {
            $cacheKey = strtolower($table.':'.$indexName);

            if (! array_key_exists($cacheKey, $cache)) {
                $database = DB::getDatabaseName();
                $row = DB::selectOne(
                    'SELECT COUNT(*) AS total FROM information_schema.statistics WHERE table_schema = ? AND LOWER(table_name) = LOWER(?) AND LOWER(index_name) = LOWER(?)',
                    [$database, $table, $indexName]
                );
                $cache[$cacheKey] = (int) ($row->total ?? 0) > 0;
            }

            if ($cache[$cacheKey]) {
                return "FORCE INDEX (`{$indexName}`)";
            }
        }

        return '';
    }

    private function clasificarMontoAgencia(float $monto, array $umbrales): string
    {
        foreach ($this->ordenCategoriasAgencia() as $categoria) {
            if ($monto >= (float) ($umbrales[$categoria] ?? 0)) {
                return $categoria;
            }
        }

        return 'D';
    }

    private function ordenCategoriasAgencia(): array
    {
        return ['AAA', 'AA', 'A', 'B', 'C', 'D'];
    }

    private function resolveThresholdConfig(Request $request): array
    {
        $default = $this->defaultThresholdConfig();

        $agenciaAAA = $this->normalizeThreshold($request->query('agencia_aaa'), $default['agencia']['AAA']);
        $agenciaAA = $this->normalizeThreshold($request->query('agencia_aa'), $default['agencia']['AA']);
        $agenciaA = $this->normalizeThreshold($request->query('agencia_a'), $default['agencia']['A']);
        $agenciaB = $this->normalizeThreshold($request->query('agencia_b'), $default['agencia']['B']);
        $agenciaC = $this->normalizeThreshold($request->query('agencia_c'), $default['agencia']['C']);
        $agenciaD = $this->normalizeThreshold($request->query('agencia_d'), $default['agencia']['D']);

        $agenciaValida = $agenciaAAA > $agenciaAA
            && $agenciaAA > $agenciaA
            && $agenciaA > $agenciaB
            && $agenciaB > $agenciaC
            && $agenciaC > $agenciaD;

        return [
            'agencia' => $agenciaValida
                ? [
                    'AAA' => $agenciaAAA,
                    'AA' => $agenciaAA,
                    'A' => $agenciaA,
                    'B' => $agenciaB,
                    'C' => $agenciaC,
                    'D' => $agenciaD,
                ]
                : $default['agencia'],
        ];
    }

    private function normalizeThreshold($value, int $fallback): int
    {
        $number = (int) $value;
        if ($number <= 0) {
            return $fallback;
        }

        return $number;
    }

    private function defaultThresholdConfig(): array
    {
        return [
            'agencia' => [
                'AAA' => 500001,
                'AA' => 300001,
                'A' => 150001,
                'B' => 100001,
                'C' => 60001,
                'D' => 60000,
            ],
        ];
    }
}
