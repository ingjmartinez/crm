<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarDesglosePagoCedulaRequest;
use App\Models\IncentivoPeriodo;
use App\Models\IncentivoPeriodoDetalle;
use App\Services\Incentivos\DesglosePagoCedulaReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DesglosePagoCedulaController extends Controller
{
    public function index(
        ConsultarDesglosePagoCedulaRequest $request,
        DesglosePagoCedulaReportService $reportService
    ): View {
        $validated = $request->validated();
        $periodos = IncentivoPeriodo::query()
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->get();
        $periodo = isset($validated['periodo_id'])
            ? $periodos->firstWhere('id', (int) $validated['periodo_id'])
            : $periodos->first();
        $consultado = $request->boolean('consultar');
        $reportes = collect();

        if ($consultado && $periodo !== null) {
            $reportes = IncentivoPeriodoDetalle::query()
                ->with('periodo')
                ->where('incentivo_periodo_id', $periodo->id)
                ->where('cedula', $validated['cedula'])
                ->orderBy('empresa')
                ->get()
                ->map(fn (IncentivoPeriodoDetalle $detalle): array => $reportService->build($detalle));
        }

        return view('incentivos.desglose-pago-cedula', [
            'periodos' => $periodos,
            'periodo' => $periodo,
            'cedula' => $validated['cedula'] ?? '',
            'consultado' => $consultado,
            'reportes' => $reportes,
        ]);
    }

    public function pdf(
        int $detalle,
        DesglosePagoCedulaReportService $reportService
    ): Response {
        $detallePago = IncentivoPeriodoDetalle::query()->with('periodo')->findOrFail($detalle);
        $data = $reportService->build($detallePago);
        $filename = sprintf(
            'desglose-incentivo-%s-%d-%02d.pdf',
            $detallePago->cedula,
            $data['periodo']->anio,
            $data['periodo']->mes
        );

        return Pdf::loadView('incentivos.desglose-pago-cedula-pdf', $data)
            ->setPaper('letter')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ])
            ->download($filename);
    }
}
