<?php

namespace App\Http\Controllers;

use App\Http\Requests\Contabilidad\ConsultarDistribucionGastoRutaRequest;
use App\Http\Requests\Operaciones\ConsultarDistribucionGastoRutaSubgruposRequest;
use App\Http\Requests\Operaciones\EliminarDistribucionGastoRutaRequest;
use App\Http\Requests\Operaciones\GenerarDistribucionGastoRutaPdfRequest;
use App\Http\Requests\Operaciones\GuardarDistribucionGastoRutaMapeoRequest;
use App\Models\DistribucionGastoRutaMapeo;
use App\Services\Contabilidad\DistribucionGastoRutaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ContabilidadDistribucionGastoRutaController extends Controller
{
    public function __construct(private readonly DistribucionGastoRutaService $distribucionService) {}

    public function index(): View
    {
        $rutasDisponibles = $this->distribucionService->rutasDisponibles();
        $mapeos = DistribucionGastoRutaMapeo::query()
            ->orderBy('ruta_nombre')
            ->orderBy('nombre_socio')
            ->get();
        $terminalesPorMapeo = $this->distribucionService->terminalesPorMapeo($mapeos);
        $rutasConfiguradas = $mapeos->pluck('ruta_key')->unique();
        $rutasConfigurables = $rutasDisponibles
            ->reject(fn (object $ruta): bool => $rutasConfiguradas->contains($ruta->ruta_key))
            ->values();
        $mapeosAgrupados = $mapeos
            ->groupBy(fn (DistribucionGastoRutaMapeo $mapeo): string => $mapeo->ruta_key)
            ->map(function (Collection $relaciones) use ($terminalesPorMapeo): array {
                /** @var DistribucionGastoRutaMapeo $primeraRelacion */
                $primeraRelacion = $relaciones->first();

                return [
                    'ruta_key' => $primeraRelacion->ruta_key,
                    'ruta_nombre' => $primeraRelacion->ruta_nombre,
                    'company_ids' => $relaciones->pluck('company_id')->unique()->values()->all(),
                    'terminales' => $relaciones
                        ->flatMap(fn (DistribucionGastoRutaMapeo $mapeo): array => $terminalesPorMapeo->get($mapeo->id, []))
                        ->unique()
                        ->count(),
                    'socios' => $relaciones->map(fn (DistribucionGastoRutaMapeo $mapeo): array => [
                        'id' => $mapeo->id,
                        'company_id' => $mapeo->company_id,
                        'id_grupo' => $mapeo->id_grupo,
                        'nombre_grupo' => $mapeo->nombre_grupo,
                        'id_sub_grupo' => $mapeo->id_sub_grupo,
                        'nombre_socio' => $mapeo->nombre_socio,
                        'terminales' => count($terminalesPorMapeo->get($mapeo->id, [])),
                    ])->values()->all(),
                ];
            })
            ->values();

        return view('contabilidad.reportes.distribucion-gastos-ruta', compact('rutasDisponibles', 'rutasConfigurables', 'mapeosAgrupados'));
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
        $resultado = $this->distribucionService->guardarMapeos(
            $request->validated(),
            $request->user()?->getAuthIdentifier(),
        );

        return response()->json([
            'message' => 'Relaciones de ruta y socios guardadas correctamente.',
            'mapeo' => $resultado['mapeo'],
            'mapeos' => $resultado['mapeos'],
            'terminales' => $resultado['terminales'],
        ]);
    }

    public function subgrupos(ConsultarDistribucionGastoRutaSubgruposRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'subgrupos' => $this->distribucionService->subgruposDisponibles(
                $validated['id_grupo'],
                $validated['company_id'],
            ),
        ]);
    }

    public function destroyMapeo(DistribucionGastoRutaMapeo $mapeo): JsonResponse
    {
        $mapeo->delete();

        return response()->json(['message' => 'Relacion eliminada correctamente.']);
    }

    public function destroyRuta(EliminarDistribucionGastoRutaRequest $request): JsonResponse
    {
        $rutaKey = $request->validated('ruta_key');
        $eliminadas = DB::transaction(fn (): int => DistribucionGastoRutaMapeo::query()
            ->where('ruta_key', $rutaKey)
            ->delete());

        return response()->json([
            'message' => 'Ruta eliminada de la lista correctamente.',
            'relaciones_eliminadas' => $eliminadas,
        ]);
    }
}
