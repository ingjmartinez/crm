<?php

namespace App\Http\Controllers\Gerencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultarRentabilidadAgenciaV6Request;
use App\Models\RentabilidadAgenciaV5;
use App\Services\RentabilidadAgenciaV6Service;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class RentabilidadAgenciaV6Controller extends Controller
{
    public function __construct(private readonly RentabilidadAgenciaV6Service $reporte) {}

    public function index(ConsultarRentabilidadAgenciaV6Request $request): View
    {
        $filtros = [...['anio' => now()->year, 'mes_inicio' => 1, 'mes_fin' => now()->month, 'empresa' => '', 'alcance' => 'todas', 'terminal' => ''], ...$request->validated()];
        $productos = $this->reporte->productos($filtros);
        $consultar = $request->filled('anio');
        $resultado = $consultar ? $this->reporte->reporte($filtros) : null;
        if ($resultado !== null) {
            $this->reporte->guardarResumenEjecutivo($filtros, (string) $request->user()->getAuthIdentifier(), $resultado);
        }
        $empresas = RentabilidadAgenciaV5::query()->activas()->whereNotNull('empresa')->whereRaw("TRIM(empresa) <> ''")
            ->selectRaw('TRIM(empresa) AS empresa')->distinct()->orderBy('empresa')->pluck('empresa');
        $datosTabla = $resultado === null ? [] : $resultado['filas']->map(fn (array $fila): array => array_diff_key($fila, ['periodos' => true]))->values()->all();

        return view('gerencia.rentabilidad-agencia-v6', compact('filtros', 'productos', 'consultar', 'resultado', 'empresas', 'datosTabla'));
    }

    public function resumen(ConsultarRentabilidadAgenciaV6Request $request): JsonResponse
    {
        $resultado = $this->reporte->reporte([...$request->validated(), 'alcance' => 'terminal']);
        $fila = $resultado['filas']->first();
        abort_if($fila === null, 404, 'No se encontro la terminal solicitada.');

        return response()->json([...$fila, 'meses' => $fila['periodos'], 'desde' => $resultado['desde']->format('Y-m'), 'hasta' => $resultado['hasta']->format('Y-m')]);
    }

    public function detalle(ConsultarRentabilidadAgenciaV6Request $request): View
    {
        $resultado = $this->reporte->reporte([...$request->validated(), 'alcance' => 'terminal'], true);
        $fila = $resultado['filas']->first();
        abort_if($fila === null, 404, 'No se encontro la terminal solicitada.');

        return view('gerencia.rentabilidad-agencia-v6-detalle', compact('fila', 'resultado'));
    }

    public function cuentas(ConsultarRentabilidadAgenciaV6Request $request): JsonResponse
    {
        return response()->json($this->reporte->cuentas($request->validated()));
    }

    public function informeEjecutivo(ConsultarRentabilidadAgenciaV6Request $request): Response
    {
        abort_unless($request->filled('anio'), 422, 'Selecciona un rango.');
        $filtros = $request->validated();
        $productos = $this->reporte->productos($filtros);
        $resultado = $this->reporte->resumenEjecutivo($filtros, (string) $request->user()->getAuthIdentifier());

        $pdf = Pdf::loadView('gerencia.rentabilidad-agencia-v6-pdf', compact('resultado', 'filtros', 'productos'))->setPaper('a4', 'landscape');
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $dompdf->getCanvas()->page_text(660, 575, 'Página {PAGE_NUM} de {PAGE_COUNT}', $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 8, [0.4, 0.45, 0.5]);

        return $pdf->download('rentabilidad_agencia_v6.pdf');
    }
}
