<?php

namespace App\Http\Controllers\Gerencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gerencia\ConsultarVentasEnVivoRequest;
use App\Services\Gerencia\VentasEnVivoService;
use Carbon\Carbon;
use Illuminate\View\View;

class VentasEnVivoController extends Controller
{
    public function __construct(private readonly VentasEnVivoService $service) {}

    public function index(ConsultarVentasEnVivoRequest $request): View
    {
        $fecha = (string) $request->query('fecha', now()->toDateString());
        $debeConsultar = $request->boolean('consultar');
        $filtros = [
            'sistema' => (string) $request->query('sistema', 'todos'),
            'empresa' => $request->query('empresa'),
            'ciudad' => $request->query('ciudad'),
            'ruta' => $request->query('ruta'),
            'tipo_producto' => (string) $request->query('tipo_producto', 'todos'),
            'buscar' => trim((string) $request->query('buscar')),
        ];

        $reporte = $debeConsultar
            ? $this->service->generar(Carbon::parse($fecha), $filtros)
            : [];

        return view('gerencia.ventas-en-vivo', [
            ...$reporte,
            'debeConsultar' => $debeConsultar,
            'fechaSeleccionada' => $fecha,
            'filtros' => $filtros,
            'opciones' => $this->service->opcionesFiltros(),
        ]);
    }
}
