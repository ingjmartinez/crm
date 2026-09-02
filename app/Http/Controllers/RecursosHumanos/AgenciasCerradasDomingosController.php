<?php

namespace App\Http\Controllers\RecursosHumanos;

use App\Exports\AgenciasCerradasDomingosExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultarAgenciasCerradasDomingosRequest;
use App\Http\Requests\ExportarAgenciasCerradasDomingosRequest;
use App\Services\RecursosHumanos\AgenciasCerradasDomingosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AgenciasCerradasDomingosController extends Controller
{
    public function __construct(private readonly AgenciasCerradasDomingosService $service) {}

    public function index(ConsultarAgenciasCerradasDomingosRequest $request): View
    {
        $fechaSeleccionada = (string) ($request->validated('fecha') ?: $this->ultimoDomingo());
        $reporte = $request->boolean('consultar')
            ? $this->service->generar(Carbon::createFromFormat('Y-m-d', $fechaSeleccionada)->startOfDay())
            : null;

        return view('recursos_humanos.agencias-cerradas-domingos', compact('fechaSeleccionada', 'reporte'));
    }

    public function exportar(ExportarAgenciasCerradasDomingosRequest $request): BinaryFileResponse|RedirectResponse
    {
        $fecha = (string) $request->validated('fecha');
        $reporte = $this->service->generar(Carbon::createFromFormat('Y-m-d', $fecha)->startOfDay());

        if (! $reporte['datos_disponibles']) {
            return redirect()
                ->route('recursos-humanos.agencias-cerradas-domingos.index', ['fecha' => $fecha, 'consultar' => 1])
                ->withErrors(['fecha' => $reporte['mensaje_datos']]);
        }

        return Excel::download(
            new AgenciasCerradasDomingosExport($reporte['filas']),
            'agencias-cerradas-domingo-'.str_replace('-', '', $fecha).'.xlsx'
        );
    }

    private function ultimoDomingo(): string
    {
        $hoy = today();

        return $hoy->copy()->subDays($hoy->dayOfWeek)->toDateString();
    }
}
