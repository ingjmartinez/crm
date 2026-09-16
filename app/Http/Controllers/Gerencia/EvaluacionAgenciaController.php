<?php

namespace App\Http\Controllers\Gerencia;

use App\Exports\Gerencia\EvaluacionAgenciaExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gerencia\ConsultarEvaluacionAgenciaRequest;
use App\Services\Gerencia\EvaluacionAgenciaService;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EvaluacionAgenciaController extends Controller
{
    public function __construct(private readonly EvaluacionAgenciaService $service) {}

    public function index(ConsultarEvaluacionAgenciaRequest $request): View
    {
        $validated = $request->validated();
        $meses = (int) ($validated['meses'] ?? 6);
        $meta = (float) ($validated['meta'] ?? 100000);
        $empresa = (string) ($validated['empresa'] ?? '');
        $productos = $validated['productos'] ?? ['tradicional', 'no_tradicional', 'recargas'];
        $resultado = $request->boolean('analizar')
            ? $this->service->evaluar($meses, $meta, $empresa, $productos)
            : ['meses' => collect(), 'filas' => collect()];

        return view('gerencia.evaluacion-agencia', [
            'cantidadMeses' => $meses,
            'meta' => $meta,
            'meses' => $resultado['meses'],
            'filas' => $resultado['filas'],
            'analizado' => $request->boolean('analizar'),
            'empresaSeleccionada' => $empresa,
            'productosSeleccionados' => $productos,
            'empresas' => $this->service->empresasDisponibles(),
            'terminalesCumplen' => $resultado['filas']->where('cumple', true)->count(),
            'terminalesNoCumplen' => $resultado['filas']->where('cumple', false)->count(),
        ]);
    }

    public function exportExcel(ConsultarEvaluacionAgenciaRequest $request): BinaryFileResponse
    {
        $validated = $request->validated();
        $meses = (int) ($validated['meses'] ?? 6);
        $meta = (float) ($validated['meta'] ?? 100000);
        $empresa = (string) ($validated['empresa'] ?? '');
        $productos = $validated['productos'] ?? ['tradicional', 'no_tradicional', 'recargas'];
        $resultado = $this->service->evaluar($meses, $meta, $empresa, $productos);

        return Excel::download(
            new EvaluacionAgenciaExport($resultado['meses'], $resultado['filas'], $meta, $empresa, $productos),
            'evaluacion_agencias_'.now()->format('Ymd_His').'.xlsx'
        );
    }
}
