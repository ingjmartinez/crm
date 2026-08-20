<?php

namespace App\Http\Controllers\Tecnologia;

use App\Exceptions\LotobetTokenRequiredException;
use App\Exports\AgenciasPlazaMonitoreoTerminalPlantillaExport;
use App\Exports\MonitoreoTerminalEstadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfigurarMonitoreoTerminalHorarioRequest;
use App\Http\Requests\ExportarMonitoreoTerminalRequest;
use App\Http\Requests\GenerarMonitoreoTerminalRequest;
use App\Http\Requests\GuardarMonitoreoTerminalAgenciasPlazaRequest;
use App\Http\Requests\GuardarMonitoreoTerminalComentarioRequest;
use App\Http\Requests\ReconocerMonitoreoTerminalAgenciasPlazaRequest;
use App\Models\Agencia;
use App\Models\MonitoreoTerminalAgenciaPlaza;
use App\Models\MonitoreoTerminalComentario;
use App\Models\MonitoreoTerminalHorario;
use App\Services\AsistenciaTerminalEndpointService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MonitoreoTerminalController extends Controller
{
    private const MINUTOS_ANTICIPACION_TURNO_PM = 60;

    public function __construct(private AsistenciaTerminalEndpointService $asistenciaTerminalEndpointService) {}

    public function index(): View
    {
        $registros = collect();
        $fechaActual = today()->toDateString();
        $horariosMonitoreo = $this->horariosMonitoreo();
        $agenciasPlazaCount = MonitoreoTerminalAgenciaPlaza::query()->count();

        return view('tecnologia.monitoreo-terminales', compact(
            'registros',
            'fechaActual',
            'horariosMonitoreo',
            'agenciasPlazaCount'
        ));
    }

    public function generar(GenerarMonitoreoTerminalRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $fechaInicio = Carbon::parse($validated['fecha_inicio'])->startOfDay();
        $fechaFin = Carbon::parse($validated['fecha_fin'])->startOfDay();
        $horaMonitoreo = Carbon::createFromFormat('H:i', $validated['hora_monitoreo']);
        $alcanceAgencias = $validated['alcance_agencias'] ?? 'todas';
        $agenciasPlazaIds = MonitoreoTerminalAgenciaPlaza::query()->pluck('agencia_id');

        if ($alcanceAgencias === 'plaza' && $agenciasPlazaIds->isEmpty()) {
            return response()->json([
                'message' => 'Debe agregar al menos una agencia en plaza antes de usar este alcance.',
            ], 422);
        }

        $agencias = Agencia::query()
            ->select('id', 'agencia', 'terminal', 'nombre_agencia', 'empresa', 'ciudad', 'ruta', 'coordinador', 'horario_am', 'horario_pm')
            ->with([
                'coordinadoresOperadores' => function ($query): void {
                    $query->where('puesto', 'coordinador');
                },
                'horarios:id,agencia_id,dia_semana,horario_am,horario_pm',
            ])
            ->whereNotNull('terminal')
            ->lotobet()
            ->when(
                $alcanceAgencias === 'plaza',
                fn ($query) => $query->whereIn('id', $agenciasPlazaIds)
            )
            ->where(function ($query): void {
                $query->whereNotNull('horario_am')
                    ->orWhereNotNull('horario_pm')
                    ->orWhereHas('horarios', function ($horarios): void {
                        $horarios->whereNotNull('horario_am')
                            ->orWhereNotNull('horario_pm');
                    });
            })
            ->orderBy('terminal')
            ->get();

        $comentarios = MonitoreoTerminalComentario::query()
            ->whereBetween('fecha', [$fechaInicio->toDateString(), $fechaFin->toDateString()])
            ->get(['agencia_id', 'comentario', 'fecha'])
            ->keyBy(fn (MonitoreoTerminalComentario $comentario): string => $comentario->agencia_id.'|'.$comentario->fecha->toDateString());

        $registros = collect();

        try {
            foreach (CarbonPeriod::create($fechaInicio, $fechaFin) as $fecha) {
                $terminalesConPonche = $this->asistenciaTerminalEndpointService->terminalesConPonche($fecha->toDateString());

                foreach ($agencias as $agencia) {
                    $horarioDia = $agencia->horarios->firstWhere('dia_semana', $fecha->dayOfWeekIso);
                    $horarioAm = $horarioDia?->horario_am ?? $agencia->horario_am;
                    $horarioPm = $horarioDia?->horario_pm ?? $agencia->horario_pm;
                    $horarioSeleccionado = $validated['tipo_horario'] === 'AM' ? $horarioAm : $horarioPm;
                    $horaApertura = $this->extraerHoraInicio($horarioSeleccionado);

                    if ($horaApertura === null) {
                        continue;
                    }

                    $terminal = $this->normalizarTerminal((string) $agencia->terminal);
                    $asistencia = $terminalesConPonche[$terminal] ?? null;
                    $fechaApertura = Carbon::createFromFormat('Y-m-d g:i A', $fecha->toDateString().' '.$horaApertura);
                    $fechaPonche = $asistencia
                        ? $this->fechaPoncheTurno(
                            $fecha->toDateString(),
                            $asistencia,
                            $validated['tipo_horario'],
                            $fechaApertura
                        )
                        : null;
                    $estado = $this->estadoAsistencia($fechaApertura, $fechaPonche);
                    $minutosTardanza = $fechaPonche
                        ? max(0, (int) $fechaApertura->diffInMinutes($fechaPonche, false))
                        : null;
                    $comentario = $comentarios->get($agencia->id.'|'.$fecha->toDateString());

                    $registros->push([
                        'agencia_id' => $agencia->id,
                        'terminal' => (string) $agencia->terminal,
                        'agencia' => $this->nombreAgencia($agencia) ?: 'Sin identificar',
                        'empresa' => trim((string) $agencia->empresa) ?: 'Sin empresa',
                        'ciudad' => trim((string) $agencia->ciudad) ?: 'Sin ciudad',
                        'ruta' => trim((string) $agencia->ruta) ?: 'Sin ruta',
                        'coordinador' => $this->nombreCoordinador($agencia),
                        'comentario' => (string) ($comentario?->comentario ?? ''),
                        'fecha' => $fecha->format('d/m/Y'),
                        'fecha_iso' => $fecha->toDateString(),
                        'hora_apertura' => $fechaApertura->format('h:i A'),
                        'hora_ponche' => $fechaPonche?->format('h:i A'),
                        'hora_monitoreo' => $horaMonitoreo->format('h:i A'),
                        'tipo_horario' => $validated['tipo_horario'],
                        'minutos_tardanza' => $minutosTardanza,
                        'estado' => $estado,
                    ]);
                }
            }
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
            'data' => $registros->values(),
            'total' => $registros->count(),
            'faltas' => $registros->where('estado', 'FALTA')->count(),
            'cumplen' => $registros->where('estado', 'CUMPLE')->count(),
            'avisos' => $registros->where('estado', 'AVISO')->count(),
            'sin_agente' => $registros->where('estado', 'SIN AGENTE DE VENTA')->count(),
            'alcance_agencias' => $alcanceAgencias,
            'alcance_label' => $alcanceAgencias === 'plaza' ? 'Solo agencias en plaza' : 'Todas las agencias',
        ]);
    }

    public function guardarComentario(GuardarMonitoreoTerminalComentarioRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $fecha = $validated['fecha'];

        MonitoreoTerminalComentario::query()->upsert(
            [[
                'agencia_id' => $validated['agencia_id'],
                'fecha' => $fecha,
                'usuario_id' => auth()->id(),
                'comentario' => $validated['comentario'] ?? null,
            ]],
            ['agencia_id', 'fecha'],
            ['usuario_id', 'comentario', 'updated_at']
        );

        $registro = MonitoreoTerminalComentario::query()
            ->where('agencia_id', $validated['agencia_id'])
            ->whereDate('fecha', $fecha)
            ->firstOrFail();

        return response()->json([
            'message' => 'Comentario guardado correctamente.',
            'data' => [
                'agencia_id' => $registro->agencia_id,
                'comentario' => $registro->comentario,
                'fecha' => $registro->fecha->format('d/m/Y'),
                'fecha_iso' => $registro->fecha->toDateString(),
            ],
        ]);
    }

    public function guardarHorario(ConfigurarMonitoreoTerminalHorarioRequest $request): JsonResponse
    {
        MonitoreoTerminalHorario::query()->updateOrCreate(
            [
                'hora' => $request->validated('hora'),
                'tipo_horario' => $request->validated('tipo_horario'),
            ],
            ['activo' => true]
        );

        return response()->json([
            'message' => 'Horario agregado correctamente.',
            'data' => $this->horariosMonitoreo(),
        ]);
    }

    public function eliminarHorario(ConfigurarMonitoreoTerminalHorarioRequest $request): JsonResponse
    {
        MonitoreoTerminalHorario::query()->updateOrCreate(
            [
                'hora' => $request->validated('hora'),
                'tipo_horario' => $request->validated('tipo_horario'),
            ],
            ['activo' => false]
        );

        return response()->json([
            'message' => 'Horario eliminado correctamente.',
            'data' => $this->horariosMonitoreo(),
        ]);
    }

    public function listarAgenciasPlaza(): JsonResponse
    {
        $agencias = $this->agenciasPlazaConfiguradas();

        return response()->json([
            'data' => $agencias,
            'count' => $agencias->count(),
            'aplica_filtro' => $agencias->isNotEmpty(),
        ]);
    }

    public function guardarAgenciasPlaza(GuardarMonitoreoTerminalAgenciasPlazaRequest $request): JsonResponse
    {
        $agenciaIds = collect($request->validated('agencias'))
            ->map(fn (mixed $agenciaId): int => (int) $agenciaId)
            ->unique()
            ->values();
        $usuarioId = auth()->id();

        DB::transaction(function () use ($agenciaIds, $usuarioId): void {
            MonitoreoTerminalAgenciaPlaza::query()
                ->when(
                    $agenciaIds->isNotEmpty(),
                    fn ($query) => $query->whereNotIn('agencia_id', $agenciaIds)
                )
                ->delete();

            foreach ($agenciaIds as $agenciaId) {
                MonitoreoTerminalAgenciaPlaza::query()->updateOrCreate(
                    ['agencia_id' => $agenciaId],
                    ['usuario_id' => $usuarioId]
                );
            }
        });

        $agencias = $this->agenciasPlazaConfiguradas();

        return response()->json([
            'message' => $agencias->isEmpty()
                ? 'La selección fue limpiada. El monitoreo analizará todas las agencias.'
                : 'Agencias en plaza guardadas correctamente.',
            'data' => $agencias,
            'count' => $agencias->count(),
            'aplica_filtro' => $agencias->isNotEmpty(),
        ]);
    }

    public function reconocerAgenciasPlaza(
        ReconocerMonitoreoTerminalAgenciasPlazaRequest $request
    ): JsonResponse {
        $terminales = collect();
        $totalFilas = 0;

        if ($request->hasFile('archivo')) {
            $filas = Excel::toCollection(null, $request->file('archivo'))->first() ?? collect();
            $totalFilas = $filas->count();

            $terminales = $terminales->merge(
                $filas->map(fn (mixed $fila): mixed => collect($fila)->first())
            );
        }

        $terminales = $terminales
            ->merge(preg_split('/[\s,;]+/', (string) $request->input('terminales_manual', '')) ?: [])
            ->map(fn (mixed $terminal): ?string => $this->normalizarTerminalPlaza($terminal))
            ->filter()
            ->reject(fn (string $terminal): bool => $terminal === 'TERMINAL')
            ->unique()
            ->values();

        $agenciasPorTerminal = Agencia::query()
            ->select('id', 'agencia', 'terminal', 'nombre_agencia')
            ->whereNotNull('terminal')
            ->lotobet()
            ->get()
            ->groupBy(fn (Agencia $agencia): string => $this->normalizarTerminal((string) $agencia->terminal));

        $agenciasEncontradas = $terminales
            ->flatMap(fn (string $terminal): Collection => $agenciasPorTerminal->get($terminal, collect()))
            ->unique('id')
            ->sortBy(fn (Agencia $agencia): string => $this->normalizarTerminal((string) $agencia->terminal))
            ->map(fn (Agencia $agencia): array => $this->datosAgenciaPlaza($agencia))
            ->values();

        $terminalesEncontradas = $agenciasEncontradas
            ->pluck('terminal_normalizada')
            ->unique();

        return response()->json([
            'data' => $agenciasEncontradas,
            'no_encontradas' => $terminales->diff($terminalesEncontradas)->values(),
            'total_filas' => $totalFilas,
            'terminales_leidas' => $terminales->count(),
            'encontradas' => $agenciasEncontradas->count(),
        ]);
    }

    public function plantillaAgenciasPlaza(): BinaryFileResponse
    {
        return Excel::download(
            new AgenciasPlazaMonitoreoTerminalPlantillaExport,
            'plantilla_agencias_en_plaza.xlsx'
        );
    }

    public function exportar(ExportarMonitoreoTerminalRequest $request): Response
    {
        $validated = $request->validated();
        $estado = $validated['estado'];
        $registros = collect($validated['registros'])
            ->when($estado !== 'TODOS', fn (Collection $items) => $items->where('estado', $estado))
            ->values();

        abort_if($registros->isEmpty(), 422, 'No hay terminales para exportar.');

        $nombreEstado = match ($estado) {
            'TODOS' => 'completo',
            'AVISO' => 'aviso',
            default => 'sin_agente_venta',
        };
        $nombreBase = 'monitoreo_'.$nombreEstado.'_'.now()->format('Ymd_His');

        if ($validated['formato'] === 'excel') {
            return Excel::download(
                new MonitoreoTerminalEstadoExport($registros->all()),
                $nombreBase.'.xlsx'
            );
        }

        return Pdf::loadView('tecnologia.monitoreo-terminales-estado-pdf', [
            'estado' => $estado,
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

    private function extraerHoraInicio(?string $horario): ?string
    {
        if (blank($horario)) {
            return null;
        }

        $hora = trim(explode('/', $horario, 2)[0]);
        $fecha = Carbon::createFromFormat('g:i A', strtoupper($hora));

        return $fecha->format('g:i A');
    }

    /** @return array<string, string> */
    private function horariosMonitoreo(): array
    {
        $horariosConfigurados = Agencia::query()
            ->select('id', 'horario_am', 'horario_pm')
            ->with('horarios:id,agencia_id,horario_am,horario_pm')
            ->whereNotNull('terminal')
            ->lotobet()
            ->get()
            ->flatMap(function (Agencia $agencia): Collection {
                return collect([
                    ['horario' => $agencia->horario_am, 'tipo_horario' => 'AM'],
                    ['horario' => $agencia->horario_pm, 'tipo_horario' => 'PM'],
                ])
                    ->merge($agencia->horarios->flatMap(
                        fn ($horario): array => [
                            ['horario' => $horario->horario_am, 'tipo_horario' => 'AM'],
                            ['horario' => $horario->horario_pm, 'tipo_horario' => 'PM'],
                        ]
                    ));
            })
            ->mapWithKeys(function (array $configuracion): array {
                $hora = $this->normalizarHoraHorario($configuracion['horario']);

                if ($hora === null) {
                    return [];
                }

                $tipoHorario = $configuracion['tipo_horario'];

                return [
                    $this->claveHorario($hora, $tipoHorario) => $this->etiquetaHorario($hora, $tipoHorario),
                ];
            })
            ->sortKeys()
            ->all();

        $horariosSolicitados = [
            '07:30|AM' => '07:30 AM - Horario AM',
            '14:29|AM' => '02:29 PM - Horario AM',
            '14:30|PM' => '02:30 PM - Horario PM',
            '21:30|PM' => '09:30 PM - Horario PM',
        ];

        $horariosMonitoreo = array_replace($horariosConfigurados, $horariosSolicitados);

        MonitoreoTerminalHorario::query()
            ->orderBy('hora')
            ->orderBy('tipo_horario')
            ->get(['hora', 'tipo_horario', 'activo'])
            ->each(function (MonitoreoTerminalHorario $configuracion) use (&$horariosMonitoreo): void {
                $clave = $this->claveHorario($configuracion->hora, $configuracion->tipo_horario);

                if (! $configuracion->activo) {
                    unset($horariosMonitoreo[$clave]);

                    return;
                }

                $horariosMonitoreo[$clave] = $this->etiquetaHorario(
                    $configuracion->hora,
                    $configuracion->tipo_horario
                );
            });

        uksort($horariosMonitoreo, function (string $primeraClave, string $segundaClave): int {
            [$primeraHora, $primerTipo] = explode('|', $primeraClave, 2);
            [$segundaHora, $segundoTipo] = explode('|', $segundaClave, 2);

            return [
                $primerTipo === 'AM' ? 0 : 1,
                $primeraHora,
            ] <=> [
                $segundoTipo === 'AM' ? 0 : 1,
                $segundaHora,
            ];
        });

        return $horariosMonitoreo;
    }

    private function claveHorario(string $hora, string $tipoHorario): string
    {
        return $hora.'|'.$tipoHorario;
    }

    private function etiquetaHorario(string $hora, string $tipoHorario): string
    {
        return Carbon::createFromFormat('H:i', $hora)->format('h:i A').' - Horario '.$tipoHorario;
    }

    /** @return Collection<int, array{id: int, terminal: string, terminal_normalizada: string, agencia: string}> */
    private function agenciasPlazaConfiguradas(): Collection
    {
        return MonitoreoTerminalAgenciaPlaza::query()
            ->with('agencia:id,agencia,terminal,nombre_agencia')
            ->get()
            ->pluck('agencia')
            ->filter()
            ->sortBy(fn (Agencia $agencia): string => $this->normalizarTerminal((string) $agencia->terminal))
            ->map(fn (Agencia $agencia): array => $this->datosAgenciaPlaza($agencia))
            ->values();
    }

    /** @return array{id: int, terminal: string, terminal_normalizada: string, agencia: string} */
    private function datosAgenciaPlaza(Agencia $agencia): array
    {
        return [
            'id' => (int) $agencia->id,
            'terminal' => (string) $agencia->terminal,
            'terminal_normalizada' => $this->normalizarTerminal((string) $agencia->terminal),
            'agencia' => $this->nombreAgencia($agencia) ?: 'Sin identificar',
        ];
    }

    private function normalizarTerminalPlaza(mixed $terminal): ?string
    {
        if (! is_scalar($terminal)) {
            return null;
        }

        $terminal = strtoupper(trim((string) $terminal));

        if ($terminal === '') {
            return null;
        }

        return ctype_digit($terminal) ? $this->normalizarTerminal($terminal) : $terminal;
    }

    private function normalizarHoraHorario(mixed $horario): ?string
    {
        if (! is_string($horario) || blank($horario)) {
            return null;
        }

        try {
            $hora = trim(explode('/', $horario, 2)[0]);

            return Carbon::createFromFormat('g:i A', strtoupper($hora))->format('H:i');
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizarTerminal(string $terminal): string
    {
        $normalizada = ltrim(trim($terminal), '0');

        return $normalizada === '' ? '0' : $normalizada;
    }

    private function estadoAsistencia(Carbon $fechaApertura, ?Carbon $fechaPonche): string
    {
        if ($fechaPonche !== null) {
            $minutosTardanza = max(0, (int) $fechaApertura->diffInMinutes($fechaPonche, false));

            if ($minutosTardanza <= 5) {
                return 'CUMPLE';
            }

            if ($minutosTardanza <= 10) {
                return 'AVISO';
            }

            return 'FALTA';
        }

        return 'SIN AGENTE DE VENTA';
    }

    private function fechaPonche(string $fecha, string $entrada): ?Carbon
    {
        try {
            $ponche = Carbon::parse($entrada);

            if (! str_contains($entrada, '-') && ! str_contains($entrada, '/')) {
                $ponche = Carbon::parse($fecha.' '.$entrada);
            }

            return $ponche;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array{entrada?: string, entradas?: array<int, string>}  $asistencia
     */
    private function fechaPoncheTurno(
        string $fecha,
        array $asistencia,
        string $tipoHorario,
        Carbon $fechaApertura
    ): ?Carbon {
        $entradas = $asistencia['entradas'] ?? [$asistencia['entrada'] ?? null];
        $fechaInicioTurno = $tipoHorario === 'PM'
            ? $fechaApertura->copy()->subMinutes(self::MINUTOS_ANTICIPACION_TURNO_PM)
            : Carbon::parse($fecha)->startOfDay();

        return collect($entradas)
            ->filter(fn (mixed $entrada): bool => is_string($entrada) && filled($entrada))
            ->map(fn (string $entrada): ?Carbon => $this->fechaPonche($fecha, $entrada))
            ->filter(fn (?Carbon $ponche): bool => $ponche !== null && $ponche->greaterThanOrEqualTo($fechaInicioTurno))
            ->sortBy(fn (Carbon $ponche): int => $ponche->getTimestamp())
            ->first();
    }

    private function nombreAgencia(Agencia $agencia): string
    {
        return Collection::make([
            trim((string) ($agencia->terminal ?: $agencia->agencia)),
            trim((string) $agencia->nombre_agencia),
        ])->filter()->unique()->implode(' - ');
    }

    private function nombreCoordinador(Agencia $agencia): string
    {
        $coordinadores = $agencia->coordinadoresOperadores
            ->map(fn ($coordinador) => trim($coordinador->nombre.' '.$coordinador->apellido))
            ->filter()
            ->unique()
            ->implode(', ');

        return $coordinadores !== ''
            ? $coordinadores
            : (trim((string) $agencia->coordinador) ?: 'Sin coordinador');
    }
}
