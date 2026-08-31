<?php

namespace App\Http\Controllers\Gerencia;

use App\Exports\SeguimientoAgenciaExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gerencia\ConsultarDetalleSeguimientoAgenciaRequest;
use App\Http\Requests\Gerencia\ConsultarSeguimientoAgenciaRequest;
use App\Services\Gerencia\SeguimientoAgenciaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SeguimientoAgenciaController extends Controller
{
    public function __construct(private readonly SeguimientoAgenciaService $service) {}

    public function index(ConsultarSeguimientoAgenciaRequest $request): View
    {
        $parametros = $this->parametros($request);
        $debeConsultar = $request->boolean('consultar');
        $reporte = $debeConsultar
            ? $this->service->generar($parametros['fechaInicio'], $parametros['fechaFin'], $parametros['filtros'], $parametros['metas'])
            : [];

        if ($debeConsultar) {
            $reporte['totalFilasDetalle'] = $reporte['filas']->count();
            $reporte['filasVisibles'] = $reporte['filas']
                ->sortBy('cumplimiento')
                ->take(500)
                ->values();
        }

        return view('gerencia.seguimiento-agencia', [
            ...$reporte,
            ...$parametros,
            'debeConsultar' => $debeConsultar,
            'opciones' => $this->service->opcionesFiltros(),
        ]);
    }

    public function exportExcel(ConsultarSeguimientoAgenciaRequest $request): BinaryFileResponse
    {
        $parametros = $this->parametros($request);
        $reporte = $this->service->generar($parametros['fechaInicio'], $parametros['fechaFin'], $parametros['filtros'], $parametros['metas']);

        return Excel::download(
            new SeguimientoAgenciaExport($reporte['filas']),
            'seguimiento_agencia_'.$parametros['fechaInicio']->format('Ymd').'_'.$parametros['fechaFin']->format('Ymd').'.xlsx'
        );
    }

    public function exportPdf(ConsultarSeguimientoAgenciaRequest $request)
    {
        $parametros = $this->parametros($request);
        $reporte = $this->service->generar($parametros['fechaInicio'], $parametros['fechaFin'], $parametros['filtros'], $parametros['metas']);

        return Pdf::loadView('gerencia.seguimiento-agencia-pdf', [
            ...$reporte,
            ...$parametros,
        ])->setPaper('a4', 'landscape')->download(
            'seguimiento_agencia_'.$parametros['fechaInicio']->format('Ymd').'_'.$parametros['fechaFin']->format('Ymd').'.pdf'
        );
    }

    public function detalle(ConsultarDetalleSeguimientoAgenciaRequest $request): JsonResponse
    {
        [$fechaInicio, $fechaFin] = $this->periodoMes((string) $request->query('mes'));

        return response()->json($this->service->detalleDiario(
            (string) $request->query('terminal'),
            (string) $request->query('sistema'),
            $fechaInicio,
            $fechaFin,
            [
                'tradicional' => (float) $request->query('meta_tradicional'),
                'no_tradicional' => (float) $request->query('meta_no_tradicional'),
                'recargas' => (float) $request->query('meta_recargas'),
            ]
        ));
    }

    /** @return array<string, mixed> */
    private function parametros(ConsultarSeguimientoAgenciaRequest $request): array
    {
        $mes = (string) $request->query('mes', now()->format('Y-m'));
        [$fechaInicio, $fechaFin] = $this->periodoMes($mes);

        return [
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'mesSeleccionado' => $fechaInicio->format('Y-m'),
            'filtros' => [
                'sistema' => (string) $request->query('sistema', 'lotobet'),
                'empresa' => $request->query('empresa'),
                'ciudad' => $request->query('ciudad'),
                'coordinador' => $request->query('coordinador'),
                'ruta' => $request->query('ruta'),
                'agencia' => $request->query('agencia'),
                'buscar' => trim((string) $request->query('buscar')) ?: null,
            ],
            'metas' => [
                'tradicional' => (float) $request->query('meta_tradicional', 7000),
                'no_tradicional' => (float) $request->query('meta_no_tradicional', 1500),
                'recargas' => (float) $request->query('meta_recargas', 700),
            ],
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periodoMes(string $mes): array
    {
        $ayer = now()->subDay()->startOfDay();
        $mesSeleccionado = Carbon::createFromFormat('Y-m', $mes)->startOfMonth();
        $inicioMesActual = now()->startOfMonth();

        if ($mesSeleccionado->greaterThan($inicioMesActual)) {
            $mesSeleccionado = $inicioMesActual;
        }

        if ($mesSeleccionado->isSameMonth($inicioMesActual) && now()->day === 1) {
            $mesSeleccionado->subMonthNoOverflow();
        }

        $fechaInicio = $mesSeleccionado->copy()->startOfMonth();
        $fechaFin = $mesSeleccionado->isSameMonth($inicioMesActual)
            ? $ayer
            : $mesSeleccionado->copy()->endOfMonth();

        return [$fechaInicio, $fechaFin];
    }
}
