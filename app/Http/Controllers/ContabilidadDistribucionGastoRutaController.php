<?php

namespace App\Http\Controllers;

use App\Http\Requests\Contabilidad\ConsultarDistribucionGastoRutaRequest;
use App\Http\Requests\Operaciones\GenerarDistribucionGastoRutaPdfRequest;
use App\Http\Requests\Operaciones\GuardarDistribucionGastoRutaMapeoRequest;
use App\Models\DistribucionGastoRutaMapeo;
use App\Models\MovimientoRutaV2Gasto;
use App\Services\Contabilidad\DistribucionGastoRutaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ContabilidadDistribucionGastoRutaController extends Controller
{
    public function __construct(private readonly DistribucionGastoRutaService $distribucionService) {}

    public function index(): View
    {
        $rutasDisponibles = MovimientoRutaV2Gasto::query()
            ->where('estado', 'aplicado')
            ->orderBy('ruta')
            ->get(['ruta_key', 'ruta'])
            ->unique('ruta_key')
            ->values();
        $mapeos = DistribucionGastoRutaMapeo::query()
            ->orderBy('ruta_nombre')
            ->orderBy('nombre_socio')
            ->get();

        return view('contabilidad.reportes.distribucion-gastos-ruta', compact('rutasDisponibles', 'mapeos'));
    }

    public function data(ConsultarDistribucionGastoRutaRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json($this->distribucionService->generar(
            $validated['fecha_ini'],
            $validated['fecha_fin'],
            ($validated['empresa'] ?? 'todas') === 'todas' ? null : $validated['empresa'],
        ));
    }

    public function pdf(GenerarDistribucionGastoRutaPdfRequest $request): Response
    {
        $validated = $request->validated();
        $resultado = $this->distribucionService->generar(
            $validated['fecha_ini'],
            $validated['fecha_fin'],
            ($validated['empresa'] ?? 'todas') === 'todas' ? null : $validated['empresa'],
            $validated['ruta_key'],
        );
        $ruta = collect($resultado['rutas'])->first();

        abort_if($ruta === null, 404, 'La ruta no tiene gastos para el periodo y empresa seleccionados.');

        $documento = Pdf::loadView('contabilidad.reportes.distribucion-gastos-ruta-pdf', [
            'meta' => $resultado['meta'],
            'ruta' => $ruta,
            'socios' => $resultado['data'],
            'detalle' => $resultado['detalle'],
            'incidencias' => $resultado['incidencias'],
        ])->setPaper('letter', 'landscape');

        $nombreRuta = Str::slug((string) $ruta['ruta'], '_');

        return $documento->download("distribucion_gastos_{$nombreRuta}_{$validated['fecha_ini']}_{$validated['fecha_fin']}.pdf");
    }

    public function storeMapeo(GuardarDistribucionGastoRutaMapeoRequest $request): JsonResponse
    {
        $resultado = $this->distribucionService->guardarMapeo(
            $request->validated(),
            $request->user()?->getAuthIdentifier(),
        );

        return response()->json([
            'message' => 'Relacion de ruta y socio guardada correctamente.',
            'mapeo' => $resultado['mapeo'],
            'terminales' => $resultado['terminales'],
        ]);
    }

    public function destroyMapeo(DistribucionGastoRutaMapeo $mapeo): JsonResponse
    {
        $mapeo->delete();

        return response()->json(['message' => 'Relacion eliminada correctamente.']);
    }
}
