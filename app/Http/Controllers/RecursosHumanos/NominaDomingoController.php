<?php

namespace App\Http\Controllers\RecursosHumanos;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecursosHumanos\ActualizarNominaDomingoConfiguracionRequest;
use App\Http\Requests\RecursosHumanos\CargarNominaDomingoRequest;
use App\Http\Requests\RecursosHumanos\ConsultarNominaDomingoRequest;
use App\Http\Requests\RecursosHumanos\EnviarNominaDomingoTelegramRequest;
use App\Services\RecursosHumanos\NominaDomingoService;
use App\Services\RecursosHumanos\NominaDomingoVentasImportService;
use App\Services\TelegramService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NominaDomingoController extends Controller
{
    public function __construct(
        private readonly NominaDomingoService $service,
        private readonly NominaDomingoVentasImportService $ventasImportService,
    ) {}

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
        $consultar = $request->boolean('consultar');
        $filas = $consultar ? $this->service->generar($fecha) : collect();
        $conciliacionVentas = $consultar ? $this->service->conciliacionVentas($fecha) : null;
        $empresa = trim((string) ($validated['empresa'] ?? ''));

        if ($empresa !== '') {
            $filas = $filas->where('empresa', $empresa)->values();
        }

        $resumenCoordinadores = $this->service->resumenCoordinadores($filas);
        $filasSinEntrada = $filas->whereNull('entrada')->values();
        $totalSinEntrada = $filasSinEntrada->count();
        $totalConEntrada = $filas->count() - $totalSinEntrada;
        $totalIncidencias = $filas->where('estatus', 'Revisar')->count();

        if ($estatus === 'cumple') {
            $filas = $filas->where('estatus', 'Cumple')->values();
        } elseif ($estatus === 'no_cumple') {
            $filas = $filas->where('estatus', 'No cumple')->values();
        } elseif ($estatus === 'revisar') {
            $filas = $filas->where('estatus', 'Revisar')->values();
        }

        return view('recursos_humanos.nomina-domingo', [
            'fecha' => $fecha->toDateString(),
            'filas' => $filas,
            'configuracion' => $this->service->configuracion(),
            'archivos' => session('nomina_domingo_archivos', []),
            'estatus' => $estatus,
            'empresa' => $empresa,
            'empresas' => $this->service->empresas(),
            'conciliacionVentas' => $conciliacionVentas,
            'consultar' => $consultar,
            'resumenCoordinadores' => $resumenCoordinadores,
            'totalConEntrada' => $totalConEntrada,
            'totalSinEntrada' => $totalSinEntrada,
            'totalIncidencias' => $totalIncidencias,
            'filasSinEntrada' => $filasSinEntrada,
        ]);
    }

    public function actualizarConfiguracion(ActualizarNominaDomingoConfiguracionRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $datos['horas_requeridas'] = (int) $datos['horas_requeridas_horas'] + ((int) $datos['horas_requeridas_minutos'] / 60);
        $this->service->guardarConfiguracion($datos);

        return back()->with('success', 'Configuración de Nómina Domingo actualizada.');
    }

    public function cargarVentas(CargarNominaDomingoRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $fecha = Carbon::createFromFormat('Y-m-d', $validated['fecha_nomina'])->startOfDay();

        if (! $fecha->isSunday()) {
            throw ValidationException::withMessages(['fecha_nomina' => 'La fecha seleccionada para la nómina debe ser domingo.']);
        }

        $this->ventasImportService->importar($validated['tradicional'], $validated['no_tradicional'], $fecha);

        return redirect()->route('recursos-humanos.nomina-domingo.index', [
            'fecha' => $fecha->toDateString(),
        ])->with('nomina_domingo_archivos', [
            'tradicional' => $validated['tradicional']->getClientOriginalName(),
            'no_tradicional' => $validated['no_tradicional']->getClientOriginalName(),
        ])->with('success', 'Ventas guardadas para el domingo '.$fecha->format('d/m/Y').'. Pulsa Generar reporte cuando quieras consultarlo.');
    }

    public function enviarTelegram(EnviarNominaDomingoTelegramRequest $request, TelegramService $telegram): JsonResponse
    {
        $validated = $request->validated();
        $fecha = Carbon::createFromFormat('Y-m-d', $validated['fecha'])->startOfDay();

        if (! $fecha->isSunday()) {
            throw ValidationException::withMessages(['fecha' => 'La fecha seleccionada debe ser domingo.']);
        }

        $filas = $this->service->generar($fecha);
        $empresa = trim((string) ($validated['empresa'] ?? ''));
        if ($empresa !== '') {
            $filas = $filas->where('empresa', $empresa)->values();
        }

        $coordinador = $this->service->resumenCoordinadores($filas)
            ->firstWhere('coordinador', $validated['coordinador']);

        if ($coordinador === null) {
            throw ValidationException::withMessages(['coordinador' => 'No se encontraron datos para el coordinador seleccionado.']);
        }

        $documentos = collect([
            $this->documentoTelegram($coordinador, $fecha, true),
            $this->documentoTelegram($coordinador, $fecha, false),
        ]);
        $resultados = $documentos->map(fn (array $documento): bool => $telegram->sendDocument(
            $validated['chat_id'],
            $documento['contenido'],
            $documento['nombre'],
            $documento['caption'],
        ));
        $enviado = $resultados->every(fn (bool $resultado): bool => $resultado);

        return response()->json([
            'enviado' => $enviado,
            'documentos' => $documentos->count(),
            'message' => $enviado ? 'Reporte enviado correctamente.' : 'No se pudo completar el envío por Telegram.',
        ], $enviado ? 200 : 502);
    }

    /**
     * @param  array<string, mixed>  $coordinador
     * @return array{contenido: string, nombre: string, caption: string}
     */
    private function documentoTelegram(array $coordinador, Carbon $fecha, bool $cumplieron): array
    {
        $estado = $cumplieron ? 'Cumplieron' : 'No cumplieron o requieren revisión';
        $clave = $cumplieron ? 'detalle_empleados_cumplieron' : 'detalle_empleados_no_cumplieron';
        $nombreCoordinador = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $coordinador['coordinador']) ?: 'coordinador';
        $contenido = Pdf::loadView('recursos_humanos.nomina-domingo-telegram-pdf', [
            'coordinador' => $coordinador['coordinador'],
            'fecha' => $fecha,
            'estado' => $estado,
            'cumplieron' => $cumplieron,
            'empleados' => collect($coordinador[$clave]),
        ])->setPaper('letter', 'landscape')->output();

        return [
            'contenido' => $contenido,
            'nombre' => "nomina_domingo_{$nombreCoordinador}_".($cumplieron ? 'cumplieron' : 'no_cumplieron')."_{$fecha->toDateString()}.pdf",
            'caption' => "Nómina Domingo - {$estado} - {$coordinador['coordinador']} - {$fecha->format('d/m/Y')}",
        ];
    }
}
