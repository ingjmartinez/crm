<?php

namespace App\Http\Controllers\Gerencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultarRentabilidadAgenciaV7Request;
use App\Models\RentabilidadAgenciaV5;
use App\Services\RentabilidadAgenciaV7Service;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class RentabilidadAgenciaV7Controller extends Controller
{
    public function __construct(private readonly RentabilidadAgenciaV7Service $reporte) {}

    public function index(ConsultarRentabilidadAgenciaV7Request $request): View
    {
        $filtros = [...['anio' => now()->year, 'mes_inicio' => 1, 'mes_fin' => now()->month, 'empresa' => '', 'ruta' => '', 'alcance' => 'todas', 'terminal' => ''], ...$request->validated()];
        $productos = $this->reporte->productos($filtros);
        $consultar = $request->filled('anio');
        $resultado = $consultar ? $this->reporte->reporte($filtros) : null;
        $informeId = $resultado !== null
            ? $this->reporte->guardarResumenEjecutivo($filtros, (string) $request->user()->getAuthIdentifier(), $resultado)
            : null;
        $empresas = RentabilidadAgenciaV5::query()->activas()->whereNotNull('empresa')->whereRaw("TRIM(empresa) <> ''")
            ->selectRaw('TRIM(empresa) AS empresa')->distinct()->orderBy('empresa')->pluck('empresa');
        $datosTabla = $resultado === null ? [] : $resultado['filas']->map(fn (array $fila): array => array_diff_key($fila, ['periodos' => true]))->values()->all();
        $rutasDisponibles = RentabilidadAgenciaV5::query()->activas()->whereNotNull('ruta')->whereRaw("TRIM(ruta) <> ''")
            ->selectRaw("COALESCE(TRIM(empresa), '') AS empresa, TRIM(ruta) AS ruta")->distinct()->orderBy('ruta')->get();
        $empresaSeleccionada = trim((string) $filtros['empresa']);
        $rutas = $rutasDisponibles
            ->when($empresaSeleccionada !== '', fn (Collection $filas): Collection => $filas->where('empresa', $empresaSeleccionada))
            ->pluck('ruta')->unique()->values();

        return view('gerencia.rentabilidad-agencia-v7', compact('filtros', 'productos', 'consultar', 'resultado', 'empresas', 'datosTabla', 'rutas', 'rutasDisponibles', 'informeId'));
    }

    public function resumen(ConsultarRentabilidadAgenciaV7Request $request): JsonResponse
    {
        $resultado = $this->reporte->reporte([...$request->validated(), 'alcance' => 'terminal']);
        $fila = $resultado['filas']->first();
        abort_if($fila === null, 404, 'No se encontro la terminal solicitada.');

        return response()->json([...$fila, 'meses' => $fila['periodos'], 'desde' => $resultado['desde']->format('Y-m'), 'hasta' => $resultado['hasta']->format('Y-m')]);
    }

    public function detalle(ConsultarRentabilidadAgenciaV7Request $request): View
    {
        $resultado = $this->reporte->reporte([...$request->validated(), 'alcance' => 'terminal'], true);
        $fila = $resultado['filas']->first();
        abort_if($fila === null, 404, 'No se encontro la terminal solicitada.');

        return view('gerencia.rentabilidad-agencia-v7-detalle', compact('fila', 'resultado'));
    }

    public function cuentas(ConsultarRentabilidadAgenciaV7Request $request): JsonResponse
    {
        return response()->json($this->reporte->cuentas($request->validated()));
    }

    public function informeEjecutivo(ConsultarRentabilidadAgenciaV7Request $request): Response
    {
        abort_unless($request->filled('anio'), 422, 'Selecciona un rango.');
        $filtros = $request->validated();
        $productos = $this->reporte->productos($filtros);
        $resultado = $this->reporte->resumenEjecutivo($filtros, (string) $request->user()->getAuthIdentifier());
        $contenido = 'resumen';
        $esImpresion = $request->validated('salida') === 'imprimir';
        if ($request->boolean('personalizado')) {
            $ruta = trim((string) $request->validated('ruta_impresion', ''));
            $resultado = $this->reporte->personalizarInforme($resultado, $ruta);
            $filtros['ruta'] = $ruta !== '' ? $ruta : ($filtros['ruta'] ?? '');
            $contenido = 'terminales';
        }
        $datos = compact('resultado', 'filtros', 'productos', 'contenido', 'esImpresion');
        if ($esImpresion) {
            return response()->view('gerencia.rentabilidad-agencia-v7-pdf', $datos)->header('Cache-Control', 'private, no-store');
        }

        $pdf = Pdf::loadView('gerencia.rentabilidad-agencia-v7-pdf', $datos)->setPaper('a4', 'landscape');
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $dompdf->getCanvas()->page_text(660, 575, 'Página {PAGE_NUM} de {PAGE_COUNT}', $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 8, [0.4, 0.45, 0.5]);

        return $pdf->download('rentabilidad_agencia_v7.pdf')->header('Cache-Control', 'private, no-store');
    }
}
