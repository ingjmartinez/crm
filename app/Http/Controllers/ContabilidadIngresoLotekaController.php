<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarIngresoLotekaRequest;
use App\Services\Contabilidad\IngresoLotekaReportService;
use Illuminate\View\View;

class ContabilidadIngresoLotekaController extends Controller
{
    public function index(
        ConsultarIngresoLotekaRequest $request,
        IngresoLotekaReportService $reportService
    ): View {
        $validated = $request->validated();
        $consultado = $request->boolean('consultar');
        $fechaInicio = $validated['fecha_inicio'] ?? today()->startOfMonth()->toDateString();
        $fechaFin = $validated['fecha_fin'] ?? today()->toDateString();
        $empresa = $validated['empresa'] ?? 'todas';
        $montoLoteka = (float) ($validated['monto_loteka'] ?? 0);
        $registros = $consultado
            ? $reportService->generate($fechaInicio, $fechaFin, $empresa)
            : collect();
        $totalMonto = round((float) $registros->sum('monto'), 2);
        $registros = $reportService->distribute($registros, $montoLoteka);
        $resumenEmpresas = collect([
            '168' => 'Grupo Joselito',
            '169' => 'Negosur',
        ])->map(function (string $nombre, string $empresaId) use ($registros, $totalMonto): array {
            $registrosEmpresa = $registros->where('empresa_id', $empresaId);
            $ventasEmpresa = round((float) $registrosEmpresa->sum('monto'), 2);

            return [
                'empresa_id' => $empresaId,
                'nombre' => $nombre,
                'terminales' => $registrosEmpresa->count(),
                'ventas' => $ventasEmpresa,
                'participacion' => $totalMonto > 0
                    ? round(($ventasEmpresa / $totalMonto) * 100, 4)
                    : 0.0,
                'monto_loteka' => round((float) $registrosEmpresa->sum('monto_distribuido'), 2),
            ];
        });

        return view('contabilidad.reportes.ingresos_loteka', [
            'consultado' => $consultado,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'empresa' => $empresa,
            'montoLoteka' => $montoLoteka,
            'registros' => $registros,
            'registrosPorEmpresa' => $registros->groupBy('empresa_id'),
            'resumenEmpresas' => $resumenEmpresas,
            'totalMonto' => $totalMonto,
            'totalDistribuido' => round((float) $registros->sum('monto_distribuido'), 2),
            'sinCentroCosto' => $registros->where('centro_costo_encontrado', false)->count(),
        ]);
    }
}
