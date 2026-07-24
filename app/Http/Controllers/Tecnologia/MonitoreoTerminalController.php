<?php

namespace App\Http\Controllers\Tecnologia;

use App\Exceptions\LotobetTokenRequiredException;
use App\Exports\MonitoreoTerminalEstadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExportarMonitoreoTerminalRequest;
use App\Http\Requests\GenerarMonitoreoTerminalRequest;
use App\Http\Requests\GuardarMonitoreoTerminalComentarioRequest;
use App\Models\Agencia;
use App\Models\MonitoreoTerminalComentario;
use App\Services\AsistenciaTerminalEndpointService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MonitoreoTerminalController extends Controller
{
    public function __construct(private AsistenciaTerminalEndpointService $asistenciaTerminalEndpointService) {}

    public function index(): View
    {
        $registros = collect();
        $fechaActual = today()->toDateString();
        $horariosMonitoreo = $this->horariosMonitoreo();

        return view('tecnologia.monitoreo-terminales', compact('registros', 'fechaActual', 'horariosMonitoreo'));
    }

    public function generar(GenerarMonitoreoTerminalRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $fechaInicio = Carbon::parse($validated['fecha_inicio'])->startOfDay();
        $fechaFin = Carbon::parse($validated['fecha_fin'])->startOfDay();
        $horaMonitoreo = Carbon::createFromFormat('H:i', $validated['hora_monitoreo']);

        $agencias = Agencia::query()
            ->select('id', 'agencia', 'terminal', 'nombre_agencia', 'coordinador', 'horario_am', 'horario_pm')
            ->with([
                'coordinadoresOperadores' => function ($query): void {
                    $query->where('puesto', 'coordinador');
                },
                'horarios:id,agencia_id,dia_semana,horario_am,horario_pm',
            ])
            ->whereNotNull('terminal')
            ->whereRaw('UPPER(TRIM(sistema)) = ?', ['LOTOBET'])
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
                    $horaApertura = $this->extraerHoraInicio($horarioAm ?: $horarioPm);

                    if ($horaApertura === null) {
                        continue;
                    }

                    $terminal = $this->normalizarTerminal((string) $agencia->terminal);
                    $asistencia = $terminalesConPonche[$terminal] ?? null;
                    $fechaApertura = Carbon::createFromFormat('Y-m-d g:i A', $fecha->toDateString().' '.$horaApertura);
                    $fechaEvaluada = Carbon::createFromFormat(
                        'Y-m-d H:i',
                        $fecha->toDateString().' '.$horaMonitoreo->format('H:i')
                    );
                    $fechaPonche = $asistencia
                        ? $this->fechaPonche($fecha->toDateString(), $asistencia['entrada'])
                        : null;
                    $estado = $this->estadoAsistencia($fechaApertura, $fechaEvaluada, $fechaPonche);
                    $fechaPoncheEvaluada = $fechaPonche?->lessThanOrEqualTo($fechaEvaluada) ? $fechaPonche : null;
                    $minutosTardanza = $fechaPoncheEvaluada
                        ? max(0, (int) $fechaApertura->diffInMinutes($fechaPoncheEvaluada, false))
                        : null;
                    $comentario = $comentarios->get($agencia->id.'|'.$fecha->toDateString());

                    $registros->push([
                        'agencia_id' => $agencia->id,
                        'terminal' => (string) $agencia->terminal,
                        'agencia' => $this->nombreAgencia($agencia) ?: 'Sin identificar',
                        'coordinador' => $this->nombreCoordinador($agencia),
                        'comentario' => (string) ($comentario?->comentario ?? ''),
                        'fecha' => $fecha->format('d/m/Y'),
                        'fecha_iso' => $fecha->toDateString(),
                        'hora_apertura' => $fechaApertura->format('h:i A'),
                        'hora_ponche' => $fechaPoncheEvaluada?->format('h:i A'),
                        'hora_monitoreo' => $horaMonitoreo->format('h:i A'),
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
            'llamadas' => $registros->where('estado', 'REQUIERE LLAMADA')->count(),
            'pendientes' => $registros->where('estado', 'PENDIENTE')->count(),
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

    public function exportar(ExportarMonitoreoTerminalRequest $request): Response
    {
        $validated = $request->validated();
        $estado = $validated['estado'];
        $registros = collect($validated['registros'])
            ->where('estado', $estado)
            ->values();

        abort_if($registros->isEmpty(), 422, 'No hay terminales del estado seleccionado para exportar.');

        $nombreEstado = $estado === 'AVISO' ? 'aviso' : 'requiere_llamada';
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
            ->whereRaw('UPPER(TRIM(sistema)) = ?', ['LOTOBET'])
            ->get()
            ->flatMap(function (Agencia $agencia): Collection {
                return collect([$agencia->horario_am, $agencia->horario_pm])
                    ->merge($agencia->horarios->flatMap(
                        fn ($horario): array => [$horario->horario_am, $horario->horario_pm]
                    ));
            })
            ->map(fn ($horario): ?string => $this->normalizarHoraHorario($horario))
            ->filter()
            ->reject(fn (string $hora): bool => $hora === '14:00')
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $hora): array => [
                $hora => Carbon::createFromFormat('H:i', $hora)->format('h:i A'),
            ])
            ->all();

        $horariosSolicitados = [
            '07:30' => '7:30 AM - Horario Am',
            '14:29' => '2:29 PM - Horario Am',
            '14:30' => '2:30 PM - Horario Pm',
            '21:30' => '9:30 PM - Horario Pm',
        ];

        $horariosMonitoreo = array_replace($horariosConfigurados, $horariosSolicitados);
        ksort($horariosMonitoreo);

        return $horariosMonitoreo;
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

    private function estadoAsistencia(Carbon $fechaApertura, Carbon $fechaEvaluada, ?Carbon $fechaPonche): string
    {
        if ($fechaPonche !== null && $fechaPonche->lessThanOrEqualTo($fechaEvaluada)) {
            $minutosTardanza = max(0, (int) $fechaApertura->diffInMinutes($fechaPonche, false));

            if ($minutosTardanza <= 5) {
                return 'CUMPLE';
            }

            if ($minutosTardanza <= 10) {
                return 'AVISO';
            }

            return 'FALTA';
        }

        return $fechaEvaluada->lessThan($fechaApertura->copy()->addMinutes(15))
            ? 'PENDIENTE'
            : 'REQUIERE LLAMADA';
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
