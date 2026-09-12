<?php

namespace App\Http\Controllers;

use App\Http\Requests\Gerencia\ConsultarVentasEnVivoRequest;
use App\Services\Gerencia\VentasEnVivoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

use function Illuminate\Support\defer;

class InicioV2Controller extends Controller
{
    public function __construct(
        private readonly InicioController $inicioController,
        private readonly VentasEnVivoService $ventasEnVivoService,
    ) {}

    public function index(Request $request): View
    {
        if (! $request->query->has('fecha') && ! $request->query->has('fecha_inicio')) {
            $request->query->set('fecha', now()->toDateString());
        }

        if (! $request->query->has('cargar')) {
            $request->query->set('cargar', '1');
        }

        $vistaBase = $this->inicioController->index($request);

        return view('inicio', [...$vistaBase->getData(), 'esTableroV2' => true]);
    }

    public function ventasEnVivo(ConsultarVentasEnVivoRequest $request): JsonResponse
    {
        $fecha = now()->startOfDay();
        $empresa = trim((string) $request->query('empresa'));
        $empresa = $empresa !== '' && mb_strtolower($empresa) !== 'todos' ? $empresa : null;
        $cacheKey = 'inicio_v2:ventas_en_vivo:'.sha1($fecha->toDateString().'|'.mb_strtolower((string) $empresa));
        $respaldoKey = $cacheKey.':respaldo';

        $datosActuales = Cache::get($cacheKey);
        if (is_array($datosActuales)) {
            return response()->json($datosActuales);
        }

        $actualizacionKey = $cacheKey.':actualizando';
        if (Cache::add($actualizacionKey, true, now()->addMinutes(3))) {
            defer(function () use ($actualizacionKey, $cacheKey, $respaldoKey, $fecha, $empresa): void {
                try {
                    $resultado = $this->ventasEnVivoService->generarTotales($fecha, [
                        'sistema' => 'todos', 'empresa' => $empresa, 'ciudad' => null, 'ruta' => null,
                        'tipo_producto' => 'todos', 'buscar' => '',
                    ]);
                    $respuesta = $this->formatearPuntoActual($fecha, $resultado);
                    Cache::put($cacheKey, $respuesta, now()->addSeconds(90));
                    Cache::put($respaldoKey, $respuesta, now()->addMinutes(15));
                } catch (\Throwable $exception) {
                    report($exception);
                } finally {
                    Cache::forget($actualizacionKey);
                }
            }, 'inicio-v2-ventas-'.sha1($cacheKey));
        }

        $respaldo = Cache::get($respaldoKey);
        if (is_array($respaldo)) {
            return response()->json([...$respaldo, 'desactualizado' => true, 'pendiente' => true]);
        }

        return response()->json([
            'fecha' => $fecha->toDateString(),
            'pendiente' => true,
            'message' => 'Las ventas en vivo se estan actualizando.',
        ], 202);
    }

    /** @param array<string, mixed> $resultado */
    private function formatearPuntoActual(Carbon $fecha, array $resultado): array
    {
        $resumen = is_array($resultado['resumen'] ?? null) ? $resultado['resumen'] : [];

        return [
            'fecha' => $fecha->toDateString(),
            'tradicional' => round((float) ($resumen['total_tradicional'] ?? 0), 2),
            'no_tradicional' => round((float) ($resumen['total_no_tradicional'] ?? 0), 2),
            'actualizado_en' => now()->toIso8601String(),
            'desactualizado' => false,
        ];
    }
}
