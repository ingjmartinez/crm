<?php

namespace App\Http\Controllers\Gerencia;

use App\Http\Controllers\Controller;
use App\Services\Ventas\PromedioHistoricoVentasOnlineService;
use Illuminate\Http\JsonResponse;

class PromedioHistoricoVentasOnlineController extends Controller
{
    public function __construct(private readonly PromedioHistoricoVentasOnlineService $service) {}

    /**
     * Devuelve el ultimo promedio guardado, sin recalcularlo.
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'promedios' => $this->service->paraJson($this->service->obtenerGuardado()),
        ]);
    }

    /**
     * Dispara el calculo del promedio de los ultimos 3 meses y lo guarda.
     */
    public function calcular(): JsonResponse
    {
        $registros = $this->service->calcularYGuardar();

        return response()->json([
            'promedios' => $this->service->paraJson($registros),
        ]);
    }
}
