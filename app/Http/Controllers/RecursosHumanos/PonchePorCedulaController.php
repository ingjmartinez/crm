<?php

namespace App\Http\Controllers\RecursosHumanos;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecursosHumanos\CargarPonchePorCedulaLoteRequest;
use App\Http\Requests\RecursosHumanos\CargarPonchePorCedulaPoolRequest;
use App\Http\Requests\RecursosHumanos\CargarPonchePorCedulaVentasRequest;
use App\Http\Requests\RecursosHumanos\ConsultarPonchePorCedulaRequest;
use App\Http\Requests\RecursosHumanos\FinalizarPonchePorCedulaLoteRequest;
use App\Http\Requests\RecursosHumanos\SubirPonchePorCedulaFragmentoRequest;
use App\Models\Asistencia;
use App\Models\AsistenciaNet;
use App\Models\Empleado;
use App\Services\RecursosHumanos\ConciliacionHorarioService;
use App\Services\RecursosHumanos\NominaDomingoVentasImportService;
use App\Services\RecursosHumanos\PonchePorCedulaCargaTemporal;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PonchePorCedulaController extends Controller
{
    public function __construct(
        private readonly ConciliacionHorarioService $conciliacion,
        private readonly NominaDomingoVentasImportService $ventasImport,
    ) {}

    public function index(): View
    {
        return view('recursos_humanos.ponche_por_cedula');
    }

    public function cargarVentasIndex(): View
    {
        return view('mantenimiento.ponche_por_cedula_ventas');
    }

    public function cargarVentas(CargarPonchePorCedulaVentasRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->ventasImport->importarPonchePorCedula(
            $request->file('tradicional'),
            $request->file('no_tradicional'),
            $request->file('recargas'),
            Carbon::parse($data['fecha'])
        );

        return redirect()->route('mantenimiento.ponche-por-cedula-ventas.index')
            ->with('success', 'Ventas de '.$data['fecha'].' cargadas correctamente para Ponche por Cédula.');
    }

    public function cargarVentasLote(CargarPonchePorCedulaLoteRequest $request): RedirectResponse
    {
        $lotes = $request->validated()['lotes'];
        $this->importarLotes($lotes);

        return redirect()->route('mantenimiento.ponche-por-cedula-ventas.index')
            ->with('success', count($lotes).' fechas cargadas correctamente para Ponche por Cédula.');
    }

    public function cargarVentasPool(CargarPonchePorCedulaPoolRequest $request): RedirectResponse|JsonResponse
    {
        $lotes = $request->lotes();
        $this->importarLotes($lotes, $request->validated()['progress_token'] ?? null);
        $message = count($lotes).' fechas cargadas correctamente para Ponche por Cédula.';

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : redirect()->route('mantenimiento.ponche-por-cedula-ventas.index')->with('success', $message);
    }

    public function subirFragmento(SubirPonchePorCedulaFragmentoRequest $request, PonchePorCedulaCargaTemporal $cargaTemporal): JsonResponse
    {
        $cargaTemporal->guardarFragmento($request->validated());

        return response()->json(['guardado' => true]);
    }

    public function finalizarLote(FinalizarPonchePorCedulaLoteRequest $request, PonchePorCedulaCargaTemporal $cargaTemporal): JsonResponse
    {
        $datos = $request->validated();

        try {
            $archivos = $cargaTemporal->archivos($datos['token'], $datos['documentos']);
            $reglas = new CargarPonchePorCedulaPoolRequest;
            Validator::make(['archivos' => $archivos], $reglas->rules(), $reglas->messages())->validate();
            $lotes = CargarPonchePorCedulaPoolRequest::agruparArchivos($archivos);
            $this->importarLotes($lotes, $datos['token']);

            return response()->json(['message' => count($lotes).' '.(count($lotes) === 1 ? 'fecha cargada' : 'fechas cargadas').' correctamente para Ponche por Cédula.']);
        } finally {
            $cargaTemporal->limpiar($datos['token']);
        }
    }

    public function progresoCargaVentas(string $token): JsonResponse
    {
        abort_unless(Str::isUuid($token), 404);
        $progreso = Cache::store('file')->get($this->claveProgreso($token));
        abort_if($progreso === null, 404);

        return response()->json($progreso);
    }

    /** @param array<int, array<string, mixed>> $lotes */
    private function importarLotes(array $lotes, ?string $token = null): void
    {
        $total = count($lotes);
        $this->guardarProgreso($token, 'procesando', 0, $total, $lotes[0]['fecha'], 'tradicional', 0);

        try {
            DB::transaction(function () use ($lotes, $token, $total): void {
                foreach ($lotes as $indice => $lote) {
                    $this->guardarProgreso($token, 'procesando', $indice, $total, $lote['fecha'], 'tradicional', $indice * 3);
                    try {
                        $this->ventasImport->importarPonchePorCedula(
                            $lote['tradicional'],
                            $lote['no_tradicional'],
                            $lote['recargas'],
                            Carbon::parse($lote['fecha']),
                            function (string $categoria) use ($token, $indice, $total, $lote): void {
                                $posicion = match ($categoria) {
                                    'tradicional' => 0,
                                    'no_tradicional' => 1,
                                    'recargas' => 2,
                                };
                                $this->guardarProgreso($token, 'procesando', $indice, $total, $lote['fecha'], $categoria, $indice * 3 + $posicion);
                            }
                        );
                    } catch (ValidationException $exception) {
                        $errores = [];
                        foreach ($exception->errors() as $campo => $mensajes) {
                            $errores['lotes.'.$indice.'.'.$campo] = array_map(
                                fn (string $mensaje): string => 'Fecha '.$lote['fecha'].': '.$mensaje,
                                $mensajes
                            );
                        }

                        throw ValidationException::withMessages($errores);
                    }
                    $this->guardarProgreso($token, 'procesando', $indice + 1, $total, $lotes[$indice + 1]['fecha'] ?? null, isset($lotes[$indice + 1]) ? 'tradicional' : null, ($indice + 1) * 3);
                }
            });
        } catch (Throwable $exception) {
            $this->guardarProgreso($token, 'error', 0, $total, null, null, 0);

            throw $exception;
        }

        $this->guardarProgreso($token, 'completo', $total, $total, null, null, $total * 3);

    }

    private function guardarProgreso(?string $token, string $estado, int $completadas, int $total, ?string $fecha, ?string $categoria, int $pasosCompletados): void
    {
        if ($token === null) {
            return;
        }

        Cache::store('file')->put($this->claveProgreso($token), [
            'estado' => $estado,
            'completadas' => $completadas,
            'total' => $total,
            'fecha' => $fecha,
            'categoria' => $categoria,
            'pasos_completados' => $pasosCompletados,
        ], now()->addMinutes(30));
    }

    private function claveProgreso(string $token): string
    {
        return 'ponche_por_cedula_carga:'.session()->getId().':'.$token;
    }

    public function list(ConsultarPonchePorCedulaRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $cedula = $filters['cedula'];
        $rows = collect();

        if ($filters['sistema'] !== 'lotonet') {
            $rows = $rows->concat(Asistencia::query()
                ->whereBetween('fecha', [$filters['fecha_inicio'], $filters['fecha_fin']])
                ->whereRaw("REPLACE(REPLACE(cedula, '-', ''), ' ', '') = ?", [$cedula])
                ->get(['fecha', 'agencia_id', 'primer_login', 'ultimo_login'])
                ->map(fn (Asistencia $ponche): array => [
                    'fecha' => (string) $ponche->fecha,
                    'sistema' => 'Lotobet',
                    'terminal' => $this->normalizarClave($ponche->agencia_id),
                    'primer_login' => $ponche->primer_login,
                    'ultimo_login' => $ponche->ultimo_login,
                ]));
        }

        if ($filters['sistema'] !== 'lotobet') {
            $rows = $rows->concat(AsistenciaNet::query()
                ->whereBetween('entrada', [$filters['fecha_inicio'].' 00:00:00', $filters['fecha_fin'].' 23:59:59'])
                ->whereRaw("REPLACE(REPLACE(identificacion, '-', ''), ' ', '') = ?", [$cedula])
                ->get(['terminal', 'entrada', 'salida'])
                ->map(fn (AsistenciaNet $ponche): array => [
                    'fecha' => substr((string) $ponche->entrada, 0, 10),
                    'sistema' => 'Lotonet',
                    'terminal' => $this->normalizarClave($ponche->terminal),
                    'primer_login' => $ponche->entrada,
                    'ultimo_login' => $ponche->salida,
                ]));
        }

        $empleado = $rows->isNotEmpty()
            ? Empleado::query()
                ->whereRaw("REPLACE(REPLACE(cedula, '-', ''), ' ', '') = ?", [$cedula])
                ->orderByRaw('CASE WHEN fechasalida IS NULL THEN 0 ELSE 1 END')
                ->first(['nombres', 'apellidos'])
            : null;
        $nombre = trim(($empleado?->nombres ?? '').' '.($empleado?->apellidos ?? '')) ?: 'No identificado';
        $conciliacion = $this->conciliacion;
        $actividad = $conciliacion->actividad($filters['fecha_inicio'], $filters['fecha_fin']);

        $resultados = $rows->groupBy(fn (array $row): string => $row['fecha'].'|'.$row['sistema'])
            ->map(function ($ponches) use ($cedula, $nombre, $conciliacion, $actividad): array {
                $primero = $ponches->pluck('primer_login')->filter()->min();
                $ultimo = $ponches->pluck('ultimo_login')->filter()->max();
                $fecha = $ponches->first()['fecha'];
                $calculo = $conciliacion->conciliar($ponches->map(fn (array $ponche): array => [...$ponche, 'cedula' => $cedula]), $actividad);

                return [
                    'cedula' => $cedula,
                    'nombre' => $nombre,
                    'fecha' => $fecha,
                    'sistema' => $ponches->first()['sistema'],
                    'primer_login' => $primero,
                    'ultimo_login' => $ultimo,
                    ...$calculo,
                    'total_horas' => $calculo['horas_sistema'],
                ];
            })
            ->sortBy([['fecha', 'asc'], ['sistema', 'asc']])
            ->values();

        return response()->json($resultados);
    }

    private function normalizarClave(mixed $valor): string
    {
        $digitos = (string) preg_replace('/\D+/', '', trim((string) $valor));

        return $digitos === '' ? '' : (ltrim($digitos, '0') ?: '0');
    }
}
