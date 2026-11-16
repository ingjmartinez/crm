<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarMovimientoTerminalRequest;
use App\Models\Agencia;
use App\Services\RecursosHumanos\NominaDomingoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class MovimientoTerminalReporteController extends Controller
{
    public function __construct(private readonly NominaDomingoService $nominaDomingo) {}

    public function index(): View
    {
        return view('reportes.movimiento-terminal', ['empresas' => $this->nominaDomingo->empresas()]);
    }

    public function data(ConsultarMovimientoTerminalRequest $request): JsonResponse
    {
        $datos = $request->validated();

        return response()->json($this->nominaDomingo->movimientosTerminal(
            Carbon::parse($datos['fecha_inicio'])->startOfDay(),
            Carbon::parse($datos['fecha_fin'])->startOfDay(),
            $datos['empresa'],
            $datos['terminal'],
            $datos['periodo'],
        ));
    }

    public function agencia(ConsultarMovimientoTerminalRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $terminal = ltrim($datos['terminal'], '0');
        $agencia = Agencia::query()->where('empresa', $datos['empresa'])
            ->get(['agencia', 'nombre_agencia', 'terminal'])
            ->first(fn (Agencia $agencia): bool => ltrim(trim((string) $agencia->terminal), '0') === $terminal);

        return response()->json($agencia);
    }
}
