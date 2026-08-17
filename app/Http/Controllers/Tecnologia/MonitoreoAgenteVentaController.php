<?php

namespace App\Http\Controllers\Tecnologia;

use App\Exceptions\LotobetTokenRequiredException;
use App\Exports\MonitoreoAgenteVentaExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExportarMonitoreoAgenteVentaRequest;
use App\Http\Requests\GenerarMonitoreoAgenteVentaRequest;
use App\Models\Agencia;
use App\Models\Empleado;
use App\Models\MonitoreoTerminalAgenciaPlaza;
use App\Services\AsistenciaAgenteVentaEndpointService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MonitoreoAgenteVentaController extends Controller
{
    public function __construct(private AsistenciaAgenteVentaEndpointService $asistenciaService) {}

    public function index(): View
    {
        return view('tecnologia.monitoreo-agentes-ventas', [
            'fechaActual' => today()->toDateString(),
            'agenciasPlazaCount' => MonitoreoTerminalAgenciaPlaza::query()->count(),
        ]);
    }

    public function generar(GenerarMonitoreoAgenteVentaRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $registrosApi = collect();

        try {
            $this->asistenciaService->prepararAcceso($validated['sistema']);

            foreach (CarbonPeriod::create($validated['fecha_inicio'], $validated['fecha_fin']) as $fecha) {
                $registrosApi->push(...$this->asistenciaService->consultar(
                    $fecha->toDateString(),
                    $validated['sistema']
                ));
            }

            $registros = $this->construirRegistros($registrosApi);
        } catch (LotobetTokenRequiredException $exception) {
            return response()->json([
                'code' => 'LOTOBET_TOKEN_REQUIRED',
                'message' => $exception->getMessage(),
            ], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'No se pudieron consultar las asistencias. Verifique la conexión con los servicios externos.',
            ], 422);
        }

        return response()->json([
            'data' => $registros,
            'total' => $registros->count(),
            'completos' => $registros->whereIn('estado', ['COMPLETO', 'SALIDA POR INACTIVIDAD'])->count(),
            'sin_entrada' => $registros->whereIn('estado', ['SIN ENTRADA', 'SIN ENTRADA Y SALIDA'])->count(),
            'sin_salida' => $registros->whereIn('estado', [
                'SIN SALIDA',
                'SIN ENTRADA Y SALIDA',
                'REINICIO VALIDADO',
                'PENDIENTE DE VALIDACIÓN',
            ])->count(),
            'agencias_plaza_count' => MonitoreoTerminalAgenciaPlaza::query()->count(),
        ]);
    }

    public function exportar(ExportarMonitoreoAgenteVentaRequest $request): Response|BinaryFileResponse
    {
        $validated = $request->validated();
        $registros = array_values($validated['registros']);
        $nombreBase = 'monitoreo_agentes_ventas_'.now()->format('Ymd_His');

        if ($validated['formato'] === 'excel') {
            return Excel::download(new MonitoreoAgenteVentaExport($registros), $nombreBase.'.xlsx');
        }

        return Pdf::loadView('tecnologia.monitoreo-agentes-ventas-pdf', [
            'registros' => $registros,
            'generadoEn' => now(),
        ])
            ->setPaper('letter', 'landscape')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ])
            ->download($nombreBase.'.pdf');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $registrosApi
     * @return Collection<int, array<string, mixed>>
     */
    private function construirRegistros(Collection $registrosApi): Collection
    {
        $agencias = Agencia::query()
            ->select('id', 'agencia', 'terminal', 'nombre_agencia', 'empresa', 'coordinador', 'sistema')
            ->with(['coordinadoresOperadores' => function ($query): void {
                $query->where('puesto', 'coordinador');
            }])
            ->whereNotNull('terminal')
            ->get()
            ->groupBy(fn (Agencia $agencia): string => $this->claveAgencia($this->sistemaAgencia($agencia), (string) $agencia->terminal));

        $empleados = $this->empleadosPorCedula($registrosApi->pluck('cedula')->filter()->unique()->values());
        $movimientosVentas = $this->movimientosVentas($registrosApi);
        $agenciasPlazaIds = MonitoreoTerminalAgenciaPlaza::query()
            ->pluck('agencia_id')
            ->mapWithKeys(fn (int $agenciaId): array => [$agenciaId => true]);

        return $registrosApi->map(function (array $registro) use ($agencias, $empleados, $movimientosVentas, $agenciasPlazaIds): array {
            /** @var Agencia|null $agencia */
            $agencia = $agencias->get($this->claveAgencia($registro['sistema'], $registro['terminal']))?->first();
            $empresa = trim((string) ($agencia?->empresa ?? '')) ?: 'Sin empresa';
            $companyId = $this->companyIdEmpresa($empresa);
            $cedula = $this->normalizarCedula($registro['cedula'] ?? '');
            /** @var Collection<int, Empleado> $empleadosCedula */
            $empleadosCedula = $empleados->get($cedula, collect());
            $empleado = $empleadosCedula->first(
                fn (Empleado $empleado): bool => (int) $empleado->companyid === $companyId
            );

            if (! $empleado && $empleadosCedula->count() === 1) {
                $empleado = $empleadosCedula->first();
            }
            $nombreApi = trim((string) ($registro['nombre'] ?? ''));
            $nombreEmpleado = $empleado
                ? trim(preg_replace('/\s+/', ' ', $empleado->nombres.' '.$empleado->apellidos))
                : '';
            $entrada = $this->formatearPonche($registro['entrada'] ?? null, $registro['fecha']);
            $validacionPonches = $this->validarSalidaConMovimientos($registro, $movimientosVentas);

            return [
                'fecha' => Carbon::parse($registro['fecha'])->format('d/m/Y'),
                'fecha_iso' => $registro['fecha'],
                'sistema' => $registro['sistema'],
                'cedula' => $cedula,
                'agente' => $nombreEmpleado ?: ($nombreApi ?: 'Sin identificar'),
                'entrada' => $entrada,
                'salida' => $validacionPonches['salida'],
                'marca_validar' => $validacionPonches['marca_validar'],
                'ultima_venta' => $validacionPonches['ultima_venta'],
                'observacion' => $validacionPonches['observacion'],
                'terminal' => (string) ($agencia?->terminal ?? $registro['terminal']),
                'agencia_id' => $agencia?->id,
                'es_agencia_plaza' => $agencia !== null && $agenciasPlazaIds->has($agencia->id),
                'agencia' => $agencia ? $this->nombreAgencia($agencia) : 'Terminal sin identificar',
                'empresa' => $empresa,
                'coordinador' => $agencia ? $this->nombreCoordinador($agencia) : 'Sin coordinador',
                'estado' => $validacionPonches['estado'],
            ];
        })->sortBy([
            ['fecha_iso', 'asc'],
            ['sistema', 'asc'],
            ['terminal', 'asc'],
            ['agente', 'asc'],
        ])->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $registrosApi
     * @return array{fechas: Collection<string, bool>, hasta_por_fecha: Collection<string, Carbon>, por_terminal: Collection<string, Collection<int, Carbon>>}
     */
    private function movimientosVentas(Collection $registrosApi): array
    {
        if (! Schema::hasTable('gestion_agencias_ventas')) {
            return ['fechas' => collect(), 'hasta_por_fecha' => collect(), 'por_terminal' => collect()];
        }

        $fechas = $registrosApi->pluck('fecha')->filter()->unique()->sort()->values();

        if ($fechas->isEmpty()) {
            return ['fechas' => collect(), 'hasta_por_fecha' => collect(), 'por_terminal' => collect()];
        }

        $movimientos = DB::table('gestion_agencias_ventas')
            ->whereNotNull('fecha_transaccion')
            ->whereBetween('fecha_transaccion', [
                Carbon::parse($fechas->first())->startOfDay(),
                Carbon::parse($fechas->last())->endOfDay(),
            ])
            ->get(['terminal_clave', 'fecha_transaccion']);

        return [
            'fechas' => $movimientos
                ->mapWithKeys(fn (object $movimiento): array => [Carbon::parse($movimiento->fecha_transaccion)->toDateString() => true]),
            'hasta_por_fecha' => $movimientos
                ->groupBy(fn (object $movimiento): string => Carbon::parse($movimiento->fecha_transaccion)->toDateString())
                ->map(fn (Collection $grupo): Carbon => $grupo
                    ->map(fn (object $movimiento): Carbon => Carbon::parse($movimiento->fecha_transaccion))
                    ->max()),
            'por_terminal' => $movimientos
                ->filter(fn (object $movimiento): bool => trim((string) $movimiento->terminal_clave) !== '')
                ->groupBy(fn (object $movimiento): string => Carbon::parse($movimiento->fecha_transaccion)->toDateString().'|'.$this->normalizarTerminal((string) $movimiento->terminal_clave))
                ->map(fn (Collection $grupo): Collection => $grupo
                    ->map(fn (object $movimiento): Carbon => Carbon::parse($movimiento->fecha_transaccion))
                    ->sort()
                    ->values()),
        ];
    }

    /**
     * @param  array<string, mixed>  $registro
     * @param  array{fechas: Collection<string, bool>, hasta_por_fecha: Collection<string, Carbon>, por_terminal: Collection<string, Collection<int, Carbon>>}  $movimientosVentas
     * @return array{salida: ?string, marca_validar: ?string, ultima_venta: ?string, observacion: string, estado: string}
     */
    private function validarSalidaConMovimientos(array $registro, array $movimientosVentas): array
    {
        $fecha = (string) $registro['fecha'];
        $entrada = $this->instantePonche($registro['entrada'] ?? null, $fecha);
        $ultimoLogout = $this->instantePonche($registro['salida'] ?? null, $fecha);
        $ultimoLogin = $this->instantePonche($registro['ultimo_login'] ?? null, $fecha);
        $marcaCandidata = $ultimoLogout ?? $ultimoLogin;
        $esMarcaPosterior = $marcaCandidata !== null
            && $entrada !== null
            && $marcaCandidata->greaterThan($entrada);

        if (! $esMarcaPosterior) {
            return [
                'salida' => null,
                'marca_validar' => null,
                'ultima_venta' => null,
                'observacion' => '',
                'estado' => $this->estadoPonche($this->formatearPonche($registro['entrada'] ?? null, $fecha), null),
            ];
        }

        $clave = $fecha.'|'.$this->normalizarTerminal((string) $registro['terminal']);
        /** @var Collection<int, Carbon> $movimientosTerminal */
        $movimientosTerminal = $movimientosVentas['por_terminal']->get($clave, collect());
        $ultimaVentaPosterior = $movimientosTerminal
            ->filter(fn (Carbon $movimiento): bool => $movimiento->greaterThan($marcaCandidata))
            ->last();
        $marcaValidar = $marcaCandidata->format('h:i A');

        if ($ultimaVentaPosterior instanceof Carbon) {
            return [
                'salida' => null,
                'marca_validar' => $marcaValidar,
                'ultima_venta' => $ultimaVentaPosterior->format('h:i A'),
                'observacion' => 'Se detectaron ventas posteriores a la marca; el agente continuó operando.',
                'estado' => 'REINICIO VALIDADO',
            ];
        }

        if (! $movimientosVentas['fechas']->has($fecha)) {
            return [
                'salida' => null,
                'marca_validar' => $marcaValidar,
                'ultima_venta' => null,
                'observacion' => 'No hay movimientos cargados para esta fecha; debe validarse el terminal.',
                'estado' => 'PENDIENTE DE VALIDACIÓN',
            ];
        }

        if (now()->lessThan($marcaCandidata->copy()->addHour())) {
            return [
                'salida' => null,
                'marca_validar' => $marcaValidar,
                'ultima_venta' => null,
                'observacion' => 'Aún no ha transcurrido una hora sin ventas desde la marca.',
                'estado' => 'PENDIENTE DE VALIDACIÓN',
            ];
        }

        $finVentanaValidacion = $marcaCandidata->copy()->addHour();
        $coberturaDocumento = $movimientosVentas['hasta_por_fecha']->get($fecha);

        if (! $coberturaDocumento instanceof Carbon || $coberturaDocumento->lessThan($finVentanaValidacion)) {
            return [
                'salida' => null,
                'marca_validar' => $marcaValidar,
                'ultima_venta' => null,
                'observacion' => 'El documento de movimientos aún no cubre una hora completa desde la marca.',
                'estado' => 'PENDIENTE DE VALIDACIÓN',
            ];
        }

        return [
            'salida' => $marcaValidar,
            'marca_validar' => $marcaValidar,
            'ultima_venta' => null,
            'observacion' => 'Sin ventas posteriores durante al menos una hora; salida inferida por inactividad.',
            'estado' => 'SALIDA POR INACTIVIDAD',
        ];
    }

    private function instantePonche(mixed $ponche, string $fecha): ?Carbon
    {
        $ponche = trim((string) $ponche);

        if ($ponche === '') {
            return null;
        }

        try {
            return Carbon::parse(str_contains($ponche, '-') || str_contains($ponche, '/') ? $ponche : "{$fecha} {$ponche}");
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  Collection<int, string>  $cedulas
     * @return Collection<string, Collection<int, Empleado>>
     */
    private function empleadosPorCedula(Collection $cedulas): Collection
    {
        if ($cedulas->isEmpty()) {
            return collect();
        }

        $cedulasNormalizadas = $cedulas
            ->map(fn (string $cedula): string => $this->normalizarCedula($cedula))
            ->filter()
            ->flip();

        return Empleado::query()
            ->select('id', 'companyid', 'cedula', 'nombres', 'apellidos')
            ->whereNotNull('cedula')
            ->cursor()
            ->filter(fn (Empleado $empleado): bool => $cedulasNormalizadas->has(
                $this->normalizarCedula($empleado->cedula)
            ))
            ->collect()
            ->groupBy(fn (Empleado $empleado): string => $this->normalizarCedula($empleado->cedula));
    }

    private function claveAgencia(string $sistema, string $terminal): string
    {
        return strtoupper($sistema).'|'.$this->normalizarTerminal($terminal);
    }

    private function sistemaAgencia(Agencia $agencia): string
    {
        $sistema = strtoupper(trim((string) $agencia->sistema));

        return str_contains($sistema, 'NET') ? 'LOTONET' : 'LOTOBET';
    }

    private function companyIdEmpresa(string $empresa): int
    {
        $empresa = mb_strtolower($empresa);

        if (str_contains($empresa, 'joselito')) {
            return 168;
        }

        return str_contains($empresa, 'negosur') ? 169 : 0;
    }

    private function formatearPonche(mixed $ponche, string $fecha): ?string
    {
        $ponche = trim((string) $ponche);

        if ($ponche === '') {
            return null;
        }

        try {
            $valor = str_contains($ponche, '-') || str_contains($ponche, '/') ? $ponche : "{$fecha} {$ponche}";

            return Carbon::parse($valor)->format('h:i A');
        } catch (Throwable) {
            return $ponche;
        }
    }

    private function estadoPonche(?string $entrada, ?string $salida): string
    {
        if ($entrada !== null && $salida !== null) {
            return 'COMPLETO';
        }

        if ($entrada === null && $salida === null) {
            return 'SIN ENTRADA Y SALIDA';
        }

        return $entrada === null ? 'SIN ENTRADA' : 'SIN SALIDA';
    }

    private function nombreAgencia(Agencia $agencia): string
    {
        return collect([(string) $agencia->terminal, trim((string) $agencia->nombre_agencia)])
            ->filter()
            ->unique()
            ->implode(' - ');
    }

    private function nombreCoordinador(Agencia $agencia): string
    {
        $coordinadores = $agencia->coordinadoresOperadores
            ->map(fn ($coordinador): string => trim($coordinador->nombre.' '.$coordinador->apellido))
            ->filter()
            ->unique()
            ->implode(', ');

        return $coordinadores !== '' ? $coordinadores : (trim((string) $agencia->coordinador) ?: 'Sin coordinador');
    }

    private function normalizarCedula(mixed $cedula): string
    {
        $digitos = preg_replace('/\D/', '', (string) $cedula) ?? '';

        return $digitos !== '' && strlen($digitos) <= 11 ? str_pad($digitos, 11, '0', STR_PAD_LEFT) : $digitos;
    }

    private function normalizarTerminal(string $terminal): string
    {
        $terminal = ltrim(trim($terminal), '0');

        return $terminal === '' ? '0' : $terminal;
    }
}
