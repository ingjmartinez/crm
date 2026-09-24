<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnviarSolicitudTerminalCorreoRequest;
use App\Http\Requests\PreviewSolicitudTerminalRequest;
use App\Http\Requests\StoreSolicitudTerminalRequest;
use App\Http\Requests\UpdateSolicitudTerminalAprobacionesRequest;
use App\Http\Requests\UpdateSolicitudTerminalDatosRequest;
use App\Mail\SolicitudTerminalMail;
use App\Models\Agencia;
use App\Models\SolicitudTerminal;
use App\Models\ZonaGeografica;
use App\Services\Mantenimiento\SolicitudTerminalExcelService;
use App\Services\Mantenimiento\SolicitudTerminalService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SolicitudTerminalController extends Controller
{
    public function __construct(
        private readonly SolicitudTerminalService $service,
        private readonly SolicitudTerminalExcelService $excelService,
    ) {}

    public function index(): View
    {
        $solicitudes = SolicitudTerminal::query()
            ->with(['solicitante', 'codigos' => fn ($query) => $query->orderBy('codigo')])
            ->withCount([
                'codigos',
                'codigos as aprobados_count' => fn ($query) => $query->where('estado', 'aprobado'),
            ])
            ->latest()
            ->paginate(15);

        return view('mantenimiento.solicitudes-terminales.index', [
            'solicitudes' => $solicitudes,
            'totalTerminales' => Agencia::query()
                ->whereNotNull('terminal')
                ->whereRaw("TRIM(terminal) <> ''")
                ->distinct()
                ->count('terminal'),
            'solicitudesPendientes' => SolicitudTerminal::query()
                ->whereIn('estado', ['pendiente', 'parcial'])
                ->count(),
            'prefijos' => collect(range(0, 99))->map(
                fn (int $prefijo): string => str_pad((string) $prefijo, 2, '0', STR_PAD_LEFT)
            ),
        ]);
    }

    public function preview(PreviewSolicitudTerminalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'codigos' => $this->service->sugerir(
                (string) $validated['prefijo_seleccionado'],
                (int) $validated['cantidad']
            ),
        ]);
    }

    public function store(StoreSolicitudTerminalRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $codigos = array_values($validated['codigos']);
        $solicitudExistente = $this->buscarSolicitudIdentica(
            (int) auth()->id(),
            (string) $validated['prefijo_seleccionado'],
            $codigos
        );

        if ($solicitudExistente) {
            return $this->respuestaSolicitudExistente($solicitudExistente);
        }

        try {
            $solicitud = DB::transaction(function () use ($validated, $codigos): SolicitudTerminal {
                $this->service->asegurarDisponibles($codigos);

                $solicitud = SolicitudTerminal::query()->create([
                    'solicitado_por' => auth()->id(),
                    'prefijo_empresa' => '05',
                    'prefijo_seleccionado' => $validated['prefijo_seleccionado'],
                    'cantidad' => count($codigos),
                    'estado' => 'pendiente',
                ]);

                $solicitud->codigos()->createMany(
                    collect($codigos)->map(fn (string $codigo): array => [
                        'codigo' => $codigo,
                        'estado' => 'pendiente',
                    ])->all()
                );

                return $solicitud;
            }, attempts: 3);
        } catch (ValidationException $exception) {
            $solicitudExistente = $this->buscarSolicitudIdentica(
                (int) auth()->id(),
                (string) $validated['prefijo_seleccionado'],
                $codigos
            );

            if ($solicitudExistente) {
                return $this->respuestaSolicitudExistente($solicitudExistente);
            }

            throw $exception;
        } catch (QueryException $exception) {
            $solicitudExistente = $this->buscarSolicitudIdentica(
                (int) auth()->id(),
                (string) $validated['prefijo_seleccionado'],
                $codigos
            );

            if ($solicitudExistente) {
                return $this->respuestaSolicitudExistente($solicitudExistente);
            }

            if (in_array((string) $exception->getCode(), ['23000', '19'], true)) {
                throw ValidationException::withMessages([
                    'codigos' => 'Uno de los códigos fue reservado por otra solicitud. Genera una sugerencia nueva.',
                ]);
            }

            throw $exception;
        }

        return redirect()
            ->route('mantenimiento.solicitudes-terminales.index')
            ->with('success', "La solicitud {$solicitud->numero} fue creada correctamente.");
    }

    public function updateAprobaciones(
        UpdateSolicitudTerminalAprobacionesRequest $request,
        SolicitudTerminal $solicitudTerminal
    ): RedirectResponse {
        $aprobados = collect($request->validated('codigos_aprobados', []))
            ->map(fn (mixed $id): int => (int) $id)
            ->flip();

        DB::transaction(function () use ($solicitudTerminal, $aprobados): void {
            $codigos = $solicitudTerminal->codigos()->lockForUpdate()->get();

            if ($aprobados->keys()->diff($codigos->pluck('id'))->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'codigos_aprobados' => 'Solo puedes aprobar códigos pertenecientes a esta solicitud.',
                ]);
            }

            foreach ($codigos as $codigo) {
                $debeEstarAprobado = $aprobados->has($codigo->id);

                if ($debeEstarAprobado && $codigo->estado !== 'aprobado') {
                    $codigo->update([
                        'estado' => 'aprobado',
                        'aprobado_por' => auth()->id(),
                        'aprobado_at' => now(),
                    ]);
                }

                if (! $debeEstarAprobado && $codigo->estado === 'aprobado') {
                    $codigo->update([
                        'estado' => 'pendiente',
                        'aprobado_por' => null,
                        'aprobado_at' => null,
                    ]);
                }
            }

            $cantidadAprobada = $aprobados->count();
            $estado = match (true) {
                $cantidadAprobada === 0 => 'pendiente',
                $cantidadAprobada === $codigos->count() => 'aprobada',
                default => 'parcial',
            };

            $solicitudTerminal->update(['estado' => $estado]);
        }, attempts: 3);

        $this->excelService->generar($solicitudTerminal->fresh());

        return redirect()
            ->route('mantenimiento.solicitudes-terminales.index', ['page' => $request->integer('page', 1)])
            ->with('success', "Las aprobaciones de {$solicitudTerminal->numero} fueron actualizadas y el Excel fue generado.")
            ->with('excelSolicitud', route('mantenimiento.solicitudes-terminales.excel', $solicitudTerminal));
    }

    public function excel(SolicitudTerminal $solicitudTerminal): StreamedResponse
    {
        $ruta = 'solicitudes-terminales/Solicitud de agencia loteka #'.$solicitudTerminal->id.'.xlsx';

        if (! Storage::disk('local')->exists($ruta)) {
            $this->excelService->generar($solicitudTerminal);
        }

        return Storage::disk('local')->download($ruta, 'Solicitud de agencia loteka #'.$solicitudTerminal->id.'.xlsx');
    }

    public function datos(SolicitudTerminal $solicitudTerminal): JsonResponse
    {
        $solicitudTerminal->load(['codigos' => fn ($query) => $query->orderBy('codigo')]);

        return response()->json([
            'numero' => $solicitudTerminal->numero,
            'regiones' => ZonaGeografica::query()->distinct()->orderBy('region')->pluck('region'),
            'codigos' => $solicitudTerminal->codigos->map(fn ($codigo): array => [
                'id' => $codigo->id,
                'codigo' => $codigo->codigo,
                'estado' => $codigo->estado,
                'nombre_banca' => $codigo->nombre_banca,
                'region' => $codigo->region,
                'provincia' => $codigo->provincia,
                'municipio' => $codigo->municipio,
                'ciudad' => $codigo->ciudad,
                'sector' => $codigo->sector,
                'calle' => $codigo->calle,
                'direccion_local' => $codigo->direccion_local,
                'latitud' => $codigo->latitud,
                'longitud' => $codigo->longitud,
                'rja' => $codigo->rja,
            ]),
        ]);
    }

    public function updateDatos(
        UpdateSolicitudTerminalDatosRequest $request,
        SolicitudTerminal $solicitudTerminal
    ): RedirectResponse {
        $datos = collect($request->validated('codigos'))->keyBy('id');

        DB::transaction(function () use ($solicitudTerminal, $datos): void {
            $codigos = $solicitudTerminal->codigos()->lockForUpdate()->get();

            if ($datos->keys()->diff($codigos->pluck('id'))->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'codigos' => 'Solo puedes actualizar terminales pertenecientes a esta solicitud.',
                ]);
            }

            foreach ($codigos as $codigo) {
                $fila = $datos->get($codigo->id);

                if ($fila === null) {
                    continue;
                }

                $codigo->update(collect($fila)
                    ->except('id')
                    ->map(fn (mixed $valor): mixed => $valor === '' ? null : $valor)
                    ->all());
            }
        }, attempts: 3);

        $this->excelService->generar($solicitudTerminal->fresh());

        return redirect()
            ->route('mantenimiento.solicitudes-terminales.index', ['page' => $request->integer('page', 1)])
            ->with('success', "Los datos de {$solicitudTerminal->numero} fueron actualizados correctamente.");
    }

    public function pdf(SolicitudTerminal $solicitudTerminal): Response
    {
        $solicitudTerminal->load(['solicitante', 'codigos' => fn ($query) => $query->orderBy('codigo')]);

        return Pdf::loadView('mantenimiento.solicitudes-terminales.pdf', [
            'solicitud' => $solicitudTerminal,
        ])->setPaper('a4')->download(strtolower($solicitudTerminal->numero).'-terminales.pdf');
    }

    public function enviarCorreo(
        EnviarSolicitudTerminalCorreoRequest $request,
        SolicitudTerminal $solicitudTerminal
    ): RedirectResponse {
        $solicitudTerminal->load(['solicitante', 'codigos' => fn ($query) => $query->orderBy('codigo')]);
        $destinatarios = $request->validated('destinatarios');
        $pdf = Pdf::loadView('mantenimiento.solicitudes-terminales.pdf', [
            'solicitud' => $solicitudTerminal,
        ])->setPaper('a4')->output();

        try {
            Mail::to($destinatarios)->send(new SolicitudTerminalMail($solicitudTerminal, $pdf));
        } catch (Throwable $exception) {
            Log::error('No se pudo enviar la solicitud de terminales por correo.', [
                'solicitud_id' => $solicitudTerminal->id,
                'destinatarios' => $destinatarios,
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['correos' => 'No se pudo enviar el correo. Verifica la configuración SMTP e intenta nuevamente.']);
        }

        return redirect()
            ->route('mantenimiento.solicitudes-terminales.index')
            ->with('success', "La solicitud {$solicitudTerminal->numero} fue enviada a ".count($destinatarios).' correo(s).');
    }

    /** @param list<string> $codigos */
    private function buscarSolicitudIdentica(int $usuarioId, string $prefijo, array $codigos): ?SolicitudTerminal
    {
        return SolicitudTerminal::query()
            ->where('solicitado_por', $usuarioId)
            ->where('prefijo_seleccionado', $prefijo)
            ->where('cantidad', count($codigos))
            ->whereHas(
                'codigos',
                fn ($query) => $query->whereIn('codigo', $codigos),
                '=',
                count($codigos)
            )
            ->latest()
            ->first();
    }

    private function respuestaSolicitudExistente(SolicitudTerminal $solicitud): RedirectResponse
    {
        return redirect()
            ->route('mantenimiento.solicitudes-terminales.index')
            ->with('success', "La solicitud {$solicitud->numero} ya había sido creada correctamente.");
    }
}
