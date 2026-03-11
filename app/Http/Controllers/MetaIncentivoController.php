<?php

namespace App\Http\Controllers;

use App\Exports\MetaIncentivoExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class MetaIncentivoController extends Controller
{
    public function index(Request $request)
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        [$anio, $mes, $sistema, $coordinador, $cumplimiento, $fechaInicio, $fechaFin] = $this->resolveFiltros($request);
        $filtrosAplicados = $request->boolean('aplicar')
            || $request->hasAny(['anio', 'mes', 'sistema', 'coordinador', 'cumplimiento']);

        $reporte = $filtrosAplicados
            ? $this->buildReporte($fechaInicio, $fechaFin, $sistema, $coordinador, $cumplimiento)
            : new Collection();

        $sistemas = DB::table('agencias')
            ->select('sistema')
            ->whereNotNull('sistema')
            ->whereRaw("TRIM(sistema) <> ''")
            ->distinct()
            ->orderBy('sistema')
            ->pluck('sistema')
            ->values();

        $coordinadores = DB::table('agencias')
            ->select('coordinador')
            ->whereNotNull('coordinador')
            ->whereRaw("TRIM(coordinador) <> ''")
            ->distinct()
            ->orderBy('coordinador')
            ->pluck('coordinador')
            ->values();

        return view('comercial.meta_incentivo', [
            'reporte' => $reporte,
            'anio' => $anio,
            'mes' => $mes,
            'sistema' => $sistema,
            'coordinador' => $coordinador,
            'cumplimiento' => $cumplimiento,
            'filtrosAplicados' => $filtrosAplicados,
            'sistemas' => $sistemas,
            'coordinadores' => $coordinadores,
            'fechaInicio' => $fechaInicio->toDateString(),
            'fechaFin' => $fechaFin->toDateString(),
        ]);
    }

    public function export(Request $request)
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        [$anio, $mes, $sistema, $coordinador, $cumplimiento, $fechaInicio, $fechaFin] = $this->resolveFiltros($request);
        $reporte = $this->buildReporte($fechaInicio, $fechaFin, $sistema, $coordinador, $cumplimiento);

        $fileName = sprintf(
            'meta_incentivo_%d_%02d%s%s%s.xlsx',
            $anio,
            $mes,
            $sistema !== '' ? '_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $sistema) : '',
            $coordinador !== '' ? '_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $coordinador) : '',
            $cumplimiento !== '' ? '_' . $cumplimiento : ''
        );

        return Excel::download(new MetaIncentivoExport($reporte), $fileName);
    }

    private function resolveFiltros(Request $request): array
    {
        $anio = (int) $request->query('anio', now()->year);
        $mes = (int) $request->query('mes', now()->month);
        $sistema = trim((string) $request->query('sistema', ''));
        $coordinador = trim((string) $request->query('coordinador', ''));
        $cumplimiento = trim((string) $request->query('cumplimiento', ''));

        $anio = $anio >= 2000 && $anio <= 2100 ? $anio : (int) now()->year;
        $mes = $mes >= 1 && $mes <= 12 ? $mes : (int) now()->month;

        $fechaCorte = Carbon::create($anio, $mes, 1);
        $fechaInicio = $fechaCorte->copy()->subMonths(2)->startOfMonth();
        $fechaFin = $fechaCorte->copy()->endOfMonth();

        if (!in_array($cumplimiento, ['', 'cumple', 'no-cumple'], true)) {
            $cumplimiento = '';
        }

        return [$anio, $mes, $sistema, $coordinador, $cumplimiento, $fechaInicio, $fechaFin];
    }

    private function buildReporte(Carbon $fechaInicio, Carbon $fechaFin, string $sistema, string $coordinador, string $cumplimiento = '')
    {
        $fechaPosteriorInicio = $fechaFin->copy()->addDay()->startOfMonth();
        $fechaPosteriorFin = $fechaPosteriorInicio->copy()->endOfMonth();

        $ventasUnion = DB::table('vt_usuarios_net')
            ->selectRaw('TRIM(CAST(agencia_id AS CHAR)) AS agencia_id')
            ->selectRaw('TRIM(CAST(producto_id AS CHAR)) AS producto_id')
            ->selectRaw('COALESCE(monto, 0) AS monto')
            ->selectRaw('fecha')
            ->unionAll(
                DB::table('vt_usuarios_bet')
                    ->selectRaw('TRIM(CAST(agencia_id AS CHAR)) AS agencia_id')
                    ->selectRaw('TRIM(CAST(producto_id AS CHAR)) AS producto_id')
                    ->selectRaw('COALESCE(monto, 0) AS monto')
                    ->selectRaw('fecha')
            );

        $fechaMin = $fechaInicio->toDateString();
        $fechaBaseMax = $fechaFin->toDateString();
        $fechaPosteriorMax = $fechaPosteriorFin->toDateString();

        $baseQuery = DB::table('agencias as a')
            ->joinSub($ventasUnion, 'v', function ($join) {
                $join->whereRaw('TRIM(a.terminal) COLLATE utf8mb4_unicode_ci = TRIM(v.agencia_id) COLLATE utf8mb4_unicode_ci');
            })
            ->leftJoin('catalogo_juegos as cj', function ($join) {
                $join->whereRaw('TRIM(cj.producto_id) COLLATE utf8mb4_unicode_ci = TRIM(v.producto_id) COLLATE utf8mb4_unicode_ci');
            })
            ->selectRaw('a.terminal AS agencia_id')
            ->selectRaw('a.nombre_agencia')
            ->selectRaw('a.coordinador')
            ->selectRaw("COALESCE(NULLIF(TRIM(cj.tipo), ''), 'sin tipo') AS tipo")
            ->selectRaw("SUM(CASE WHEN v.fecha BETWEEN '{$fechaMin}' AND '{$fechaBaseMax}' THEN v.monto ELSE 0 END) AS ventas_3_meses")
            ->selectRaw("(SUM(CASE WHEN v.fecha BETWEEN '{$fechaMin}' AND '{$fechaBaseMax}' THEN v.monto ELSE 0 END) / 3) AS promedio_3_meses")
            ->selectRaw("SUM(CASE WHEN v.fecha BETWEEN '{$fechaPosteriorInicio->toDateString()}' AND '{$fechaPosteriorMax}' THEN v.monto ELSE 0 END) AS total_venta_mes_posterior")
            ->whereNotNull('v.fecha')
            ->whereBetween('v.fecha', [$fechaMin, $fechaPosteriorMax])
            ->when($sistema !== '', function ($query) use ($sistema) {
                $query->where('a.sistema', $sistema);
            })
            ->when($coordinador !== '', function ($query) use ($coordinador) {
                $query->where('a.coordinador', $coordinador);
            })
            ->groupBy('a.terminal', 'a.nombre_agencia', 'a.coordinador', DB::raw("COALESCE(NULLIF(TRIM(cj.tipo), ''), 'sin tipo')"))
            ->havingRaw("SUM(CASE WHEN v.fecha BETWEEN '{$fechaMin}' AND '{$fechaBaseMax}' THEN v.monto ELSE 0 END) > 0");

        $reporte = DB::query()
            ->fromSub($baseQuery, 'r')
            ->leftJoin('niveles as n', function ($join) {
                $join->on('n.tipo_producto', '=', 'r.tipo')
                    ->whereRaw('r.promedio_3_meses BETWEEN n.rango_min AND n.rango_max');
            })
            ->selectRaw('r.*')
            ->selectRaw('COALESCE(n.nivel, "") AS nivel')
            ->selectRaw('IFNULL(IFNULL(n.incremento_fijo, (r.promedio_3_meses * n.incremento_porcentaje)), 0) AS incremetal')
            ->selectRaw('(r.promedio_3_meses + IFNULL(IFNULL(n.incremento_fijo, (r.promedio_3_meses * n.incremento_porcentaje)), 0)) AS meta_incremental')
            ->orderByDesc('r.ventas_3_meses')
            ->get();

        if ($cumplimiento === 'cumple') {
            return $reporte->filter(function ($row) {
                $meta = (float) ($row->meta_incremental ?? 0);
                $venta = (float) ($row->total_venta_mes_posterior ?? 0);
                return $meta <= 0 || $venta >= $meta;
            })->values();
        }

        if ($cumplimiento === 'no-cumple') {
            return $reporte->filter(function ($row) {
                $meta = (float) ($row->meta_incremental ?? 0);
                $venta = (float) ($row->total_venta_mes_posterior ?? 0);
                return $meta > 0 && $venta < $meta;
            })->values();
        }

        return $reporte;
    }
}
