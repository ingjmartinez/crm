<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InicioController extends Controller
{
    public function index(Request $request)
    {
        [$fechaInicio, $fechaFin, $fechaSeleccionada] = $this->resolverRangoFechas($request);

        return view('inicio', [
            'fechaInicioVentas' => $fechaInicio->toDateString(),
            'fechaFinVentas' => $fechaFin->toDateString(),
            'fechaSeleccionadaVentas' => $fechaSeleccionada->toDateString(),
            'ventasInicio' => $this->getResumenVentas($fechaInicio, $fechaFin),
        ]);
    }

    public function ventasData(Request $request)
    {
        [$fechaInicio, $fechaFin, $fechaSeleccionada] = $this->resolverRangoFechas($request);

        return response()->json([
            'fecha_inicio' => $fechaInicio->toDateString(),
            'fecha_fin' => $fechaFin->toDateString(),
            'fecha' => $fechaSeleccionada->toDateString(),
            'ventas' => $this->getResumenVentas($fechaInicio, $fechaFin),
        ]);
    }

    private function resolverRangoFechas(Request $request): array
    {
        $fechaInput = $request->query('fecha');
        $fechaInicioInput = $request->query('fecha_inicio');
        $fechaFinInput = $request->query('fecha_fin');

        $fechaDefault = now()->subDay()->startOfDay();

        if (is_string($fechaInput) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInput)) {
            $fechaSeleccionada = $this->parseFechaOrDefault($fechaInput, $fechaDefault);

            return [
                $fechaSeleccionada->copy()->startOfDay(),
                $fechaSeleccionada->copy()->endOfDay(),
                $fechaSeleccionada->copy()->startOfDay(),
            ];
        }

        $fechaInicio = $this->parseFechaOrDefault($fechaInicioInput, $fechaDefault);
        $fechaFin = $this->parseFechaOrDefault($fechaFinInput, $fechaDefault);

        if ($fechaInicio->greaterThan($fechaFin)) {
            [$fechaInicio, $fechaFin] = [$fechaFin, $fechaInicio];
        }

        return [
            $fechaInicio->startOfDay(),
            $fechaFin->endOfDay(),
            $fechaFin->copy()->startOfDay(),
        ];
    }

    private function parseFechaOrDefault($fecha, Carbon $default): Carbon
    {
        if (!is_string($fecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return $default->copy();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $fecha);
        } catch (\Throwable $e) {
            return $default->copy();
        }
    }

    private function getResumenVentas(Carbon $fechaInicio, Carbon $fechaFin): array
    {
        $cacheKey = 'inicio_resumen_ventas:' . sha1($fechaInicio->toDateString() . '|' . $fechaFin->toDateString());

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($fechaInicio, $fechaFin) {
            return $this->calcularResumenVentas($fechaInicio, $fechaFin);
        });
    }

    private function calcularResumenVentas(Carbon $fechaInicio, Carbon $fechaFin): array
    {
        $resumen = $this->emptyResumenVentas();
        $resumenLotobet = $this->getResumenVentasPorTabla('vt_usuarios_bet', $fechaInicio, $fechaFin);
        $resumenLotonet = $this->getResumenVentasPorTabla('vt_usuarios_net', $fechaInicio, $fechaFin);

        $resumen['sistemas']['Lotobet'] = $resumenLotobet;
        $resumen['sistemas']['Lotonet'] = $resumenLotonet;

        foreach (['tradicional', 'no_tradicional', 'recargas', 'otros'] as $tipo) {
            $resumen['tipos'][$tipo]['total'] =
                (float) ($resumenLotobet['tipos'][$tipo]['total'] ?? 0) +
                (float) ($resumenLotonet['tipos'][$tipo]['total'] ?? 0);
            $resumen['tipos'][$tipo]['registros'] =
                (int) ($resumenLotobet['tipos'][$tipo]['registros'] ?? 0) +
                (int) ($resumenLotonet['tipos'][$tipo]['registros'] ?? 0);
            $resumen['tipos'][$tipo]['agencias'] =
                (int) ($resumenLotobet['tipos'][$tipo]['agencias'] ?? 0) +
                (int) ($resumenLotonet['tipos'][$tipo]['agencias'] ?? 0);
        }

        $resumen['total_general'] =
            (float) ($resumenLotobet['total_general'] ?? 0) +
            (float) ($resumenLotonet['total_general'] ?? 0);
        $resumen['registros'] =
            (int) ($resumenLotobet['registros'] ?? 0) +
            (int) ($resumenLotonet['registros'] ?? 0);
        $resumen['agencias_con_venta'] = $this->getAgenciasConVentaCombinadas($fechaInicio, $fechaFin);

        return $resumen;
    }

    private function getResumenVentasPorTabla(string $tabla, Carbon $fechaInicio, Carbon $fechaFin): array
    {
        $rows = DB::table($tabla)
            ->selectRaw('tipo')
            ->selectRaw('SUM(COALESCE(monto, 0)) AS total')
            ->selectRaw('COUNT(*) AS registros')
            ->whereBetween('fecha', [$fechaInicio->toDateString(), $fechaFin->toDateString()])
            ->groupBy('tipo')
            ->get();

        $resumen = [
            'total_general' => 0,
            'registros' => 0,
            'tipos' => [
                'tradicional' => ['label' => 'Tradicional', 'total' => 0, 'registros' => 0, 'agencias' => 0],
                'no_tradicional' => ['label' => 'No Tradicional', 'total' => 0, 'registros' => 0, 'agencias' => 0],
                'recargas' => ['label' => 'Recargas', 'total' => 0, 'registros' => 0, 'agencias' => 0],
                'otros' => ['label' => 'Otros', 'total' => 0, 'registros' => 0, 'agencias' => 0],
            ],
        ];

        foreach ($rows as $row) {
            $tipoKey = $this->normalizeTipo((string) ($row->tipo ?? ''));
            $total = (float) ($row->total ?? 0);
            $registros = (int) ($row->registros ?? 0);
            $agencias = 0;

            $resumen['tipos'][$tipoKey]['total'] += $total;
            $resumen['tipos'][$tipoKey]['registros'] += $registros;
            $resumen['tipos'][$tipoKey]['agencias'] += $agencias;
            $resumen['total_general'] += $total;
            $resumen['registros'] += $registros;
        }

        return $resumen;
    }

    private function getAgenciasConVentaCombinadas(Carbon $fechaInicio, Carbon $fechaFin): int
    {
        $sub = DB::table('vt_usuarios_bet')
            ->select('agencia_id')
            ->whereNotNull('agencia_id')
            ->whereBetween('fecha', [$fechaInicio->toDateString(), $fechaFin->toDateString()])
            ->union(
                DB::table('vt_usuarios_net')
                    ->select('agencia_id')
                    ->whereNotNull('agencia_id')
                    ->whereBetween('fecha', [$fechaInicio->toDateString(), $fechaFin->toDateString()])
            );

        $row = DB::query()
            ->fromSub($sub, 'a')
            ->selectRaw('COUNT(DISTINCT agencia_id) AS total')
            ->first();

        return (int) ($row->total ?? 0);
    }

    private function normalizeTipo(string $tipo): string
    {
        $tipo = strtolower(trim($tipo));

        if ($tipo === 'tradicional') {
            return 'tradicional';
        }

        if ($tipo === 'no tradicional' || $tipo === 'no_tradicional') {
            return 'no_tradicional';
        }

        if ($tipo === 'recarga' || $tipo === 'recargas') {
            return 'recargas';
        }

        return 'otros';
    }

    private function emptyResumenVentas(): array
    {
        $tipos = [
            'tradicional' => ['label' => 'Tradicional', 'total' => 0, 'registros' => 0, 'agencias' => 0],
            'no_tradicional' => ['label' => 'No Tradicional', 'total' => 0, 'registros' => 0, 'agencias' => 0],
            'recargas' => ['label' => 'Recargas', 'total' => 0, 'registros' => 0, 'agencias' => 0],
            'otros' => ['label' => 'Otros', 'total' => 0, 'registros' => 0, 'agencias' => 0],
        ];

        return [
            'total_general' => 0,
            'registros' => 0,
            'agencias_con_venta' => 0,
            'tipos' => $tipos,
            'sistemas' => [
                'Lotobet' => [
                    'total_general' => 0,
                    'registros' => 0,
                    'tipos' => $tipos,
                ],
                'Lotonet' => [
                    'total_general' => 0,
                    'registros' => 0,
                    'tipos' => $tipos,
                ],
            ],
        ];
    }
}
