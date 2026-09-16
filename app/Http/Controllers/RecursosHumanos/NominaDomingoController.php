<?php

namespace App\Http\Controllers\RecursosHumanos;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecursosHumanos\ActualizarNominaDomingoConfiguracionRequest;
use App\Http\Requests\RecursosHumanos\ConsultarNominaDomingoRequest;
use App\Services\RecursosHumanos\NominaDomingoService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NominaDomingoController extends Controller
{
    public function __construct(private readonly NominaDomingoService $service) {}

    public function index(ConsultarNominaDomingoRequest $request): View
    {
        $validated = $request->validated();
        $fecha = $request->filled('fecha')
            ? Carbon::createFromFormat('Y-m-d', (string) $validated['fecha'])->startOfDay()
            : now()->previous(Carbon::SUNDAY)->startOfDay();

        if (! $fecha->isSunday()) {
            throw ValidationException::withMessages(['fecha' => 'La fecha seleccionada debe ser domingo.']);
        }

        $estatus = (string) ($validated['estatus'] ?? 'todos');
        $filas = $request->boolean('consultar') ? $this->service->generar($fecha) : collect();
        $filasSinEntrada = $filas->whereNull('entrada')->values();
        $totalSinEntrada = $filasSinEntrada->count();
        $totalConEntrada = $filas->count() - $totalSinEntrada;

        if ($estatus === 'cumple') {
            $filas = $filas->where('estatus', 'Cumple')->values();
        } elseif ($estatus === 'no_cumple') {
            $filas = $filas->where('estatus', 'No cumple')->values();
        }

        return view('recursos_humanos.nomina-domingo', [
            'fecha' => $fecha->toDateString(),
            'filas' => $filas,
            'configuracion' => $this->service->configuracion(),
            'archivos' => session('gestion_agencias_archivos', []),
            'estatus' => $estatus,
            'totalConEntrada' => $totalConEntrada,
            'totalSinEntrada' => $totalSinEntrada,
            'filasSinEntrada' => $filasSinEntrada,
        ]);
    }

    public function actualizarConfiguracion(ActualizarNominaDomingoConfiguracionRequest $request): RedirectResponse
    {
        $this->service->guardarConfiguracion($request->validated());

        return back()->with('success', 'Configuración de Nómina Domingo actualizada.');
    }
}
