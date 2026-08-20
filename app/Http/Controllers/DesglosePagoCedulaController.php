<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarDesglosePagoCedulaRequest;
use App\Http\Requests\DescargarDesglosePagoCedulasRequest;
use App\Models\IncentivoPeriodo;
use App\Models\IncentivoPeriodoDetalle;
use App\Services\Incentivos\CedulaListInputService;
use App\Services\Incentivos\DesglosePagoCedulaReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DesglosePagoCedulaController extends Controller
{
    public function index(
        ConsultarDesglosePagoCedulaRequest $request,
        CedulaListInputService $cedulaListInputService,
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
        $cedulas = collect();
        $cedulasNoEncontradas = collect();

        if ($consultado && $periodo !== null) {
            $resultado = $cedulaListInputService->extract(
                $validated['cedula'] ?? null,
                $validated['cedulas_manual'] ?? null,
                $request->file('archivo_cedulas')
            );

            if ($resultado['invalidas'] !== []) {
                throw ValidationException::withMessages([
                    'cedulas_manual' => 'Cédulas inválidas: '.collect($resultado['invalidas'])->take(10)->implode(', '),
                ]);
            }

            $cedulas = collect($resultado['cedulas']);

            if ($cedulas->isEmpty()) {
                throw ValidationException::withMessages([
                    'cedulas_manual' => 'El listado no contiene cédulas válidas de 11 dígitos.',
                ]);
            }

            $reportes = $this->reportsFor($periodo, $cedulas, $reportService);
            $cedulasEncontradas = $reportes->pluck('detalle.cedula')->unique();
            $cedulasNoEncontradas = $cedulas->diff($cedulasEncontradas)->values();
        }

        return view('incentivos.desglose-pago-cedula', [
            'periodos' => $periodos,
            'periodo' => $periodo,
            'cedula' => $validated['cedula'] ?? '',
            'cedulasManual' => $validated['cedulas_manual'] ?? '',
            'cedulas' => $cedulas,
            'cedulasNoEncontradas' => $cedulasNoEncontradas,
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

    public function pdfListado(
        DescargarDesglosePagoCedulasRequest $request,
        DesglosePagoCedulaReportService $reportService
    ): Response {
        $periodo = IncentivoPeriodo::query()->findOrFail($request->validated('periodo_id'));
        $cedulas = collect($request->validated('cedulas'));
        $reportes = $this->reportsFor($periodo, $cedulas, $reportService);

        abort_if($reportes->isEmpty(), 422, 'No hay desgloses disponibles para generar el PDF.');

        $filename = sprintf('desglose-incentivos-listado-%d-%02d.pdf', $periodo->anio, $periodo->mes);

        return Pdf::loadView('incentivos.desglose-pago-cedula-pdf', [
            'reportes' => $reportes,
        ])
            ->setPaper('letter')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ])
            ->download($filename);
    }

    /**
     * @param  Collection<int, string>  $cedulas
     * @return Collection<int, array<string, mixed>>
     */
    private function reportsFor(
        IncentivoPeriodo $periodo,
        Collection $cedulas,
        DesglosePagoCedulaReportService $reportService
    ): Collection {
        $orden = $cedulas->flip();

        return IncentivoPeriodoDetalle::query()
            ->with('periodo')
            ->where('incentivo_periodo_id', $periodo->id)
            ->whereIn('cedula', $cedulas)
            ->get()
            ->sortBy(fn (IncentivoPeriodoDetalle $detalle): string => sprintf(
                '%08d|%s',
                $orden->get($detalle->cedula, PHP_INT_MAX),
                $detalle->empresa
            ))
            ->map(fn (IncentivoPeriodoDetalle $detalle): array => $reportService->build($detalle))
            ->values();
    }
}
