<?php

namespace App\Http\Controllers\RecursosHumanos;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecursosHumanos\ActualizarNominaDomingoConfiguracionRequest;
use App\Http\Requests\RecursosHumanos\CargarNominaDomingoRequest;
use App\Http\Requests\RecursosHumanos\ConsultarCedulaNominaDomingoRequest;
use App\Http\Requests\RecursosHumanos\ConsultarNominaDomingoRequest;
use App\Http\Requests\RecursosHumanos\DescargarInformeEjecutivoNominaDomingoRequest;
use App\Http\Requests\RecursosHumanos\DescargarPagoNominaDomingoRequest;
use App\Http\Requests\RecursosHumanos\EnviarNominaDomingoTelegramRequest;
use App\Http\Requests\RecursosHumanos\GuardarNominaDomingoTerminalesDobleTurnoRequest;
use App\Http\Requests\RecursosHumanos\GuardarNominaDomingoTerminalesExcluidasRequest;
use App\Http\Requests\RecursosHumanos\ReconocerNominaDomingoTerminalesDobleTurnoRequest;
use App\Http\Requests\RecursosHumanos\ReconocerNominaDomingoTerminalesExcluidasRequest;
use App\Http\Requests\RecursosHumanos\ResolverRecargaPendienteNominaDomingoRequest;
use App\Imports\AgenciasActualizacionMasivaImport;
use App\Models\CentroDeCosto;
use App\Models\Empleado;
use App\Models\NominaDomingoAuditoria;
use App\Services\RecursosHumanos\NominaDomingoService;
use App\Services\RecursosHumanos\NominaDomingoVentasImportService;
use App\Services\TelegramService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $recargasPendientes = $consultar ? $this->service->recargasPendientes($fecha) : ['total' => 0, 'usuarios' => collect(), 'transacciones' => collect()];
        $resumenCumplimientoEmpresas = $this->service->resumenCumplimientoPorEmpresa($filas);
        $filasSinEmpresa = $filas->where('empresa', 'Sin empresa')->values();
        $empresa = trim((string) ($validated['empresa'] ?? ''));

        if ($empresa !== '') {
            $filas = $filas->where('empresa', $empresa)->values();
        }

        $resumenCoordinadores = $this->service->resumenCoordinadores($filas);
        $filasSinEntrada = $filas->whereNull('entrada')->values();
        $totalSinEntrada = $filasSinEntrada->count();
        $totalConEntrada = $filas->count() - $totalSinEntrada;
        $totalIncidencias = $filas->where('estatus', 'Revisar')->count();
        $tendenciaMensual = $consultar
            ? $this->service->tendenciaMensual($fecha, $empresa, $filas)
            : null;

        if ($estatus === 'cumple') {
            $filas = $filas->where('estatus', 'Cumple')->values();
        } elseif ($estatus === 'no_cumple') {
            $filas = $filas->where('estatus', 'No cumple')->values();
        } elseif ($estatus === 'revisar') {
            $filas = $filas->where('estatus', 'Revisar')->values();
        }

        $filasReporte = $this->service->agruparPorCedula($this->agregarCentrosDeCosto($filas));

        return view('recursos_humanos.nomina-domingo', [
            'fecha' => $fecha->toDateString(),
            'filas' => $filas,
            'filasReporte' => $filasReporte,
            'configuracion' => $this->service->configuracion(),
            'archivos' => session('nomina_domingo_archivos', []),
            'estatus' => $estatus,
            'empresa' => $empresa,
            'empresas' => $this->service->empresas(),
            'conciliacionVentas' => $conciliacionVentas,
            'recargasPendientes' => $recargasPendientes,
            'consultar' => $consultar,
            'resumenCoordinadores' => $resumenCoordinadores,
            'resumenCumplimientoEmpresas' => $resumenCumplimientoEmpresas,
            'filasSinEmpresa' => $filasSinEmpresa,
            'totalConEntrada' => $totalConEntrada,
            'totalSinEntrada' => $totalSinEntrada,
            'totalIncidencias' => $totalIncidencias,
            'filasSinEntrada' => $filasSinEntrada,
            'tendenciaMensual' => $tendenciaMensual,
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return Collection<int, array<string, mixed>>
     */
    private function agregarCentrosDeCosto(Collection $filas): Collection
    {
        if ($filas->isEmpty()) {
            return $filas;
        }

        $terminales = $filas->pluck('terminal')->map(fn (mixed $terminal): string => ltrim((string) $terminal, '0'))->unique();
        $centros = CentroDeCosto::query()
            ->where('inactivo', false)
            ->get(['id_viejo', 'company_id', 'id_centro_costo'])
            ->filter(fn (CentroDeCosto $centro): bool => $terminales->contains(ltrim(trim((string) $centro->id_viejo), '0')))
            ->groupBy(fn (CentroDeCosto $centro): string => ltrim(trim((string) $centro->id_viejo), '0'));

        return $filas->map(function (array $fila) use ($centros): array {
            $empresa = mb_strtolower(trim((string) $fila['empresa']));
            $companyId = match (true) {
                str_contains($empresa, 'negosur') => '169',
                str_contains($empresa, 'joselito') => '168',
                default => null,
            };
            $centrosTerminal = $centros->get((string) $fila['terminal'], collect());
            $centro = $companyId === null
                ? ($centrosTerminal->count() === 1 ? $centrosTerminal->first() : null)
                : $centrosTerminal->first(fn (CentroDeCosto $item): bool => trim((string) $item->company_id) === $companyId);
            $fila['centro_costo_id'] = $centro?->id_centro_costo;

            return $fila;
        });
    }

    public function actualizarConfiguracion(ActualizarNominaDomingoConfiguracionRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $datos['horas_requeridas'] = (int) $datos['horas_requeridas_horas'] + ((int) $datos['horas_requeridas_minutos'] / 60);
        DB::transaction(function () use ($request, $datos): void {
            $antes = $this->service->configuracion();
            $this->service->guardarConfiguracion($datos);
            $despues = $this->service->configuracion();
            if ($antes !== $despues) {
                $this->registrarAuditoria($request, 'modificado', 'configuracion', null, $antes, $despues);
            }
        });

        return back()->with('success', 'Configuración de Nómina Domingo actualizada.');
    }

    public function consultarCedula(ConsultarCedulaNominaDomingoRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $fecha = Carbon::createFromFormat('Y-m-d', $validated['fecha'])->startOfDay();

        if (! $fecha->isSunday()) {
            throw ValidationException::withMessages(['fecha' => 'La fecha seleccionada debe ser domingo.']);
        }

        $cedula = (string) preg_replace('/\D+/', '', $validated['cedula']);
        if (strlen($cedula) !== 11) {
            throw ValidationException::withMessages(['cedula' => 'La cédula debe contener 11 dígitos.']);
        }

        $resultado = $this->service->consultarCedula($fecha, $cedula);

        if ($resultado === null) {
            return response()->json(['message' => 'No se encontraron ventas para esta cédula en el domingo seleccionado.'], 404);
        }

        return response()->json($resultado);
    }

    public function resolverRecargaPendiente(ResolverRecargaPendienteNominaDomingoRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->service->resolverRecargaPendiente(
            (int) $validated['venta_id'],
            $validated['terminal'],
            isset($validated['fecha']) ? Carbon::parse($validated['fecha']) : null,
        );

        return response()->json(['message' => 'Recarga asignada al terminal seleccionado.']);
    }

    public function informeEjecutivo(DescargarInformeEjecutivoNominaDomingoRequest $request): Response
    {
        $validated = $request->validated();
        $fecha = Carbon::createFromFormat('Y-m-d', $validated['fecha'])->startOfDay();

        if (! $fecha->isSunday()) {
            throw ValidationException::withMessages(['fecha' => 'La fecha de cierre debe ser domingo.']);
        }

        $informe = $this->service->informeEjecutivoEmpresa($fecha, trim($validated['empresa']));
        $nombre = 'informe_ejecutivo_nomina_domingo_'.Str::slug($validated['empresa'], '_').'_'.$fecha->format('Ymd').'.pdf';

        return Pdf::loadView('recursos_humanos.nomina-domingo-informe-ejecutivo-pdf', compact('informe'))
            ->setPaper('letter', 'landscape')
            ->download($nombre);
    }

    public function pagoTxt(DescargarPagoNominaDomingoRequest $request): StreamedResponse
    {
        $datos = $request->validated();
        $fecha = Carbon::createFromFormat('Y-m-d', $datos['fecha'])->startOfDay();
        if (! $fecha->isSunday()) {
            throw ValidationException::withMessages(['fecha' => 'La fecha seleccionada debe ser domingo.']);
        }

        $filas = $this->service->generar($fecha)
            ->when(filled($datos['empresa'] ?? null), fn (Collection $filas): Collection => $filas->where('empresa', trim($datos['empresa'])));
        $pagos = $filas->filter(fn (array $fila): bool => (float) $fila['monto_pagar'] > 0)
            ->groupBy('cedula')
            ->map(function (Collection $filasCedula): array {
                $fila = $filasCedula->first();
                $fila['monto_pagar'] = $filasCedula->sum('monto_pagar');

                return $fila;
            })
            ->values();

        if ($pagos->isEmpty()) {
            throw ValidationException::withMessages(['fecha' => 'No hay pagos con importe mayor a cero para este domingo.']);
        }

        $empleados = Empleado::query()
            ->whereIn('empleadoid', $pagos->pluck('empleado_id')->filter()->unique()->all())
            ->get(['empleadoid', 'viapago', 'ciudad'])
            ->keyBy('empleadoid');
        $nombre = 'pago_nomina_domingo_'.$fecha->format('Y-m-d').'.txt';

        return response()->streamDownload(function () use ($pagos, $empleados, $fecha): void {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            $escribirLinea = function (array $valores) use ($salida): void {
                $limpios = array_map(fn (mixed $valor): string => str_replace([',', "\t", "\r", "\n"], ' ', trim((string) $valor)), $valores);
                fwrite($salida, implode(',', $limpios)."\r\n");
            };
            $escribirLinea(['IdEmpleado', 'Empresa', 'ViaPago', 'Ciudad', 'IdNovedad', 'Importe', 'Aplicar A', 'IdSucursal', 'Id Doc', 'Comentario', 'IdViejo']);

            foreach ($pagos as $fila) {
                $empleado = $empleados->get($fila['empleado_id']);
                $escribirLinea([
                    $fila['empleado_id'] ?? '',
                    $fila['empresa'],
                    $empleado?->viapago ?? '',
                    $empleado?->ciudad ?? '',
                    'INC',
                    (string) round((float) $fila['monto_pagar']),
                    '',
                    '',
                    '',
                    'pago de nómina domingo '.$fecha->format('d/m/Y'),
                    '',
                ]);
            }

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function cargarVentas(CargarNominaDomingoRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $fecha = Carbon::createFromFormat('Y-m-d', $validated['fecha_nomina'])->startOfDay();

        if (! $fecha->isSunday()) {
            throw ValidationException::withMessages(['fecha_nomina' => 'La fecha seleccionada para la nómina debe ser domingo.']);
        }

        $this->ventasImportService->importar($validated['tradicional'], $validated['no_tradicional'], $fecha, $validated['recargas'] ?? null);

        return redirect()->route('recursos-humanos.nomina-domingo.index', [
            'fecha' => $fecha->toDateString(),
        ])->with('nomina_domingo_archivos', [
            'tradicional' => $validated['tradicional']->getClientOriginalName(),
            'no_tradicional' => $validated['no_tradicional']->getClientOriginalName(),
            'recargas' => isset($validated['recargas']) ? $validated['recargas']->getClientOriginalName() : null,
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

    public function reconocerTerminalesExcluidas(ReconocerNominaDomingoTerminalesExcluidasRequest $request): JsonResponse
    {
        return $this->reconocerTerminales($request);
    }

    public function reconocerTerminalesDobleTurno(ReconocerNominaDomingoTerminalesDobleTurnoRequest $request): JsonResponse
    {
        return $this->reconocerTerminales($request);
    }

    private function reconocerTerminales(Request $request): JsonResponse
    {
        $terminales = collect();
        $totalFilas = 0;

        if ($request->hasFile('file')) {
            $import = new AgenciasActualizacionMasivaImport;
            Excel::import($import, $request->file('file'));
            $rows = $import->rows ?? collect();
            $totalFilas = $rows->count();
            $terminales = $terminales->merge($rows->map(
                fn (mixed $row): mixed => collect($row)->first(fn (mixed $value, mixed $key): bool => strtolower(trim((string) $key)) === 'terminal')
            ));
        }

        $terminales = $terminales->merge($this->extraerTerminales((string) $request->input('terminales_manual', '')))
            ->map(fn (mixed $terminal): string => trim((string) $terminal))->filter()->unique()->values();

        if ($terminales->isEmpty()) {
            throw ValidationException::withMessages(['terminales_manual' => 'No se encontraron terminales. La plantilla debe tener una columna llamada Terminal.']);
        }

        $encontradas = DB::table('agencias')->whereIn(DB::raw('TRIM(CAST(terminal AS CHAR))'), $terminales->all())
            ->selectRaw('TRIM(CAST(terminal AS CHAR)) AS terminal')->pluck('terminal')
            ->map(fn (mixed $terminal): string => trim((string) $terminal))->filter()->unique()->values();
        $noEncontradas = $terminales->diff($encontradas)->values();

        return response()->json([
            'ok' => true,
            'total_filas' => $totalFilas,
            'terminales_leidas' => $terminales->count(),
            'encontradas' => $encontradas->count(),
            'no_encontradas' => $noEncontradas->count(),
            'terminales_encontradas' => $encontradas->all(),
            'terminales_no_encontradas' => $noEncontradas->all(),
        ]);
    }

    public function listarTerminalesExcluidas(): JsonResponse
    {
        $terminales = $this->terminalesExcluidasGuardadas();

        return response()->json(['ok' => true, 'terminales' => $terminales->all(), 'count' => $terminales->count()]);
    }

    public function listarTerminalesDobleTurno(): JsonResponse
    {
        $terminales = $this->terminalesDobleTurnoGuardadas();

        return response()->json(['ok' => true, 'terminales' => $terminales->all(), 'count' => $terminales->count()]);
    }

    public function guardarTerminalesDobleTurno(GuardarNominaDomingoTerminalesDobleTurnoRequest $request): JsonResponse
    {
        if (! Schema::hasTable('nomina_domingo_terminales_doble_turno')) {
            return response()->json(['ok' => false, 'message' => 'Ejecuta las migraciones pendientes para guardar las terminales doble turno.'], 500);
        }

        $terminales = collect($request->validated('terminales'))->map(fn (mixed $terminal): string => trim((string) $terminal))
            ->filter()->unique()->values();
        $userId = auth()->id();
        $anteriores = $this->terminalesDobleTurnoGuardadas();

        DB::transaction(function () use ($request, $terminales, $anteriores, $userId): void {
            DB::table('nomina_domingo_terminales_doble_turno')
                ->when($terminales->isNotEmpty(), fn ($query) => $query->whereNotIn('terminal', $terminales->all()))->delete();
            foreach ($terminales as $terminal) {
                DB::table('nomina_domingo_terminales_doble_turno')->updateOrInsert(
                    ['terminal' => $terminal],
                    ['created_by' => $userId, 'updated_by' => $userId, 'created_at' => now(), 'updated_at' => now()]
                );
            }
            $this->registrarCambiosTerminales($request, 'doble_turno', $anteriores, $terminales);
        });

        $guardadas = $this->terminalesDobleTurnoGuardadas();

        return response()->json(['ok' => true, 'message' => 'Terminales doble turno guardadas correctamente.', 'terminales' => $guardadas->all(), 'count' => $guardadas->count()]);
    }

    /** @return Collection<int, string> */
    private function terminalesDobleTurnoGuardadas(): Collection
    {
        return Schema::hasTable('nomina_domingo_terminales_doble_turno')
            ? DB::table('nomina_domingo_terminales_doble_turno')->orderBy('terminal')->pluck('terminal')
            : collect();
    }

    public function guardarTerminalesExcluidas(GuardarNominaDomingoTerminalesExcluidasRequest $request): JsonResponse
    {
        if (! Schema::hasTable('nomina_domingo_terminales_excluidas')) {
            return response()->json(['ok' => false, 'message' => 'Ejecuta las migraciones pendientes para guardar las terminales excluidas.'], 500);
        }

        $terminales = collect($request->validated('terminales'))->map(fn (mixed $terminal): string => trim((string) $terminal))
            ->filter()->unique()->values();
        $userId = auth()->id();
        $anteriores = $this->terminalesExcluidasGuardadas();

        DB::transaction(function () use ($request, $terminales, $anteriores, $userId): void {
            DB::table('nomina_domingo_terminales_excluidas')
                ->when($terminales->isNotEmpty(), fn ($query) => $query->whereNotIn('terminal', $terminales->all()))->delete();
            foreach ($terminales as $terminal) {
                DB::table('nomina_domingo_terminales_excluidas')->updateOrInsert(
                    ['terminal' => $terminal],
                    ['created_by' => $userId, 'updated_by' => $userId, 'created_at' => now(), 'updated_at' => now()]
                );
            }
            $this->registrarCambiosTerminales($request, 'excluida', $anteriores, $terminales);
        });

        $guardadas = $this->terminalesExcluidasGuardadas();

        return response()->json(['ok' => true, 'message' => 'Terminales excluidas guardadas correctamente.', 'terminales' => $guardadas->all(), 'count' => $guardadas->count()]);
    }

    public function plantillaTerminalesExcluidas(): BinaryFileResponse
    {
        return Excel::download(new class implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\ShouldAutoSize, \Maatwebsite\Excel\Concerns\WithStyles
        {
            public function array(): array
            {
                return [['Terminal']];
            }

            public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
            {
                return [1 => ['font' => ['bold' => true]]];
            }
        }, 'plantilla_terminales_excluidas_nomina_domingo.xlsx');
    }

    /** @return Collection<int, string> */
    private function extraerTerminales(string $texto): Collection
    {
        return collect(preg_split('/[\s,;]+/', $texto) ?: []);
    }

    public function auditoria(Request $request): View
    {
        $buscar = trim((string) $request->query('buscar', ''));
        $auditorias = NominaDomingoAuditoria::query()
            ->when($buscar !== '', fn ($query) => $query->where(function ($query) use ($buscar): void {
                $query->where('usuario_nombre', 'like', '%'.$buscar.'%')
                    ->orWhere('usuario_email', 'like', '%'.$buscar.'%')
                    ->orWhere('terminal', 'like', '%'.$buscar.'%');
            }))
            ->latest()->paginate(50)->withQueryString();

        return view('recursos_humanos.nomina-domingo-auditoria', compact('auditorias', 'buscar'));
    }

    private function registrarCambiosTerminales(Request $request, string $categoria, Collection $anteriores, Collection $terminales): void
    {
        foreach ($terminales->diff($anteriores) as $terminal) {
            $this->registrarAuditoria($request, 'agregado', $categoria, $terminal, null, ['terminal' => $terminal]);
        }
        foreach ($anteriores->diff($terminales) as $terminal) {
            $this->registrarAuditoria($request, 'eliminado', $categoria, $terminal, ['terminal' => $terminal], null);
        }
    }

    private function registrarAuditoria(Request $request, string $accion, string $categoria, ?string $terminal, ?array $antes, ?array $despues): void
    {
        $usuario = $request->user();
        NominaDomingoAuditoria::create([
            'accion' => $accion,
            'categoria' => $categoria,
            'terminal' => $terminal,
            'usuario_id' => $usuario?->id,
            'usuario_nombre' => $usuario?->name ?? 'Sistema',
            'usuario_email' => $usuario?->email ?? 'sistema',
            'antes' => $antes,
            'despues' => $despues,
            'ip' => $request->ip(),
        ]);
    }

    /** @return Collection<int, string> */
    private function terminalesExcluidasGuardadas(): Collection
    {
        return Schema::hasTable('nomina_domingo_terminales_excluidas')
            ? DB::table('nomina_domingo_terminales_excluidas')->orderBy('terminal')->pluck('terminal')
            : collect();
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
