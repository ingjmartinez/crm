<?php

namespace App\Http\Controllers;

use App\Http\Requests\AplicarValidadorAgenciaRequest;
use App\Http\Requests\ProcesarValidadorAgenciaRequest;
use App\Models\CentroCostoHistorialCambio;
use App\Models\CentroDeCosto;
use App\Models\ValidadorAgenciaCarga;
use App\Models\ValidadorAgenciaDetalle;
use App\Services\Contabilidad\ValidadorAgenciaCsvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ContabilidadValidadorAgenciaController extends Controller
{
    public function __construct(
        private readonly ValidadorAgenciaCsvService $csvService
    ) {}

    public function index(Request $request): View
    {
        $cargaId = $request->integer('carga');
        $carga = ValidadorAgenciaCarga::query()
            ->with(['detalles' => fn ($query) => $query->orderBy('terminal_normalizada')])
            ->when(
                $cargaId > 0,
                fn ($query) => $query->whereKey($cargaId),
                fn ($query) => $query->latest('id')
            )
            ->first();

        return view('contabilidad.validador-agencia', [
            'carga' => $carga,
            'detalles' => $carga?->detalles ?? collect(),
        ]);
    }

    public function procesar(ProcesarValidadorAgenciaRequest $request): RedirectResponse
    {
        /** @var UploadedFile $archivo */
        $archivo = $request->validated('archivo_csv');
        $resultado = $this->csvService->procesar($archivo);
        $filasResultado = collect($resultado['filas']);
        $estados = $filasResultado->countBy('estado');
        $path = $archivo->getRealPath();

        $carga = DB::transaction(function () use (
            $archivo,
            $resultado,
            $filasResultado,
            $estados,
            $request,
            $path
        ): ValidadorAgenciaCarga {
            $carga = ValidadorAgenciaCarga::query()->create([
                'nombre_archivo' => $archivo->getClientOriginalName(),
                'hash_archivo' => $path !== false ? hash_file('sha256', $path) : null,
                'filas_leidas' => $resultado['control']['filas_leidas'],
                'filas_validas' => $resultado['control']['filas_validas'],
                'correctas' => $estados->get('correcto', 0),
                'nuevas' => $estados->get('nuevo', 0),
                'nombres_diferentes' => $filasResultado
                    ->filter(fn (array $fila): bool => str_contains($fila['estado'], 'nombre_'))
                    ->count(),
                'rutas_diferentes' => $filasResultado
                    ->filter(fn (array $fila): bool => str_contains($fila['estado'], 'ruta_'))
                    ->count(),
                'sociedades_diferentes' => $filasResultado
                    ->filter(fn (array $fila): bool => str_contains($fila['estado'], 'sociedad_'))
                    ->count(),
                'conflictos' => $estados->get('conflicto_archivo', 0) + $estados->get('conflicto_centro', 0),
                'usuario_id' => $request->user()?->getAuthIdentifier(),
            ]);

            foreach (array_chunk($resultado['filas'], 500) as $filas) {
                $carga->detalles()->createMany($filas);
            }

            return $carga;
        });

        return redirect()
            ->route('contabilidad.validador-agencia', ['carga' => $carga->id])
            ->with('success', 'El archivo fue analizado. Revisa los resultados antes de aplicar cambios.');
    }

    public function aplicar(
        AplicarValidadorAgenciaRequest $request,
        ValidadorAgenciaDetalle $validadorAgenciaDetalle
    ): JsonResponse {
        $observacion = $request->validated('observacion');

        $resultado = DB::transaction(function () use ($request, $validadorAgenciaDetalle, $observacion): array {
            $detalle = ValidadorAgenciaDetalle::query()
                ->with('carga')
                ->lockForUpdate()
                ->findOrFail($validadorAgenciaDetalle->id);

            if ($detalle->estado !== 'nuevo' && ! str_contains($detalle->estado, 'diferente')) {
                throw ValidationException::withMessages([
                    'detalle' => 'Esta fila ya no admite cambios.',
                ]);
            }

            $usuarioId = $request->user()?->getAuthIdentifier();
            $actor = $request->user()?->name ?? 'Validador de Agencia';
            $accion = $detalle->estado === 'nuevo' ? 'creacion' : 'actualizacion';
            $actualizaNombre = $detalle->estado === 'nuevo' || str_contains($detalle->estado, 'nombre_');
            $actualizaRuta = $detalle->estado === 'nuevo' || str_contains($detalle->estado, 'ruta_');
            $actualizaSociedad = $detalle->estado === 'nuevo' || str_contains($detalle->estado, 'sociedad_');

            if ($detalle->estado === 'nuevo') {
                $centroCosto = $this->crearCentroCosto($detalle, $actor);
            } else {
                $centroCosto = CentroDeCosto::query()
                    ->lockForUpdate()
                    ->findOrFail($detalle->centro_costo_id);
                $nombreActual = trim((string) $centroCosto->descripcion);
                $nombreAlValidar = trim((string) $detalle->nombre_centro_costo);

                if (
                    $actualizaNombre
                    && $nombreActual !== $nombreAlValidar
                    && $nombreActual !== trim($detalle->nombre_agencia)
                ) {
                    throw ValidationException::withMessages([
                        'detalle' => 'El nombre cambió después de cargar el archivo. Vuelve a analizar el CSV antes de actualizar.',
                    ]);
                }

                $rutaActual = trim((string) $centroCosto->id_grupo);
                $rutaAlValidar = trim((string) $detalle->ruta_centro_costo);
                if (
                    $actualizaRuta
                    && $rutaActual !== $rutaAlValidar
                    && $rutaActual !== trim($detalle->ruta)
                ) {
                    throw ValidationException::withMessages([
                        'detalle' => 'La ruta cambió después de cargar el archivo. Vuelve a analizar el CSV antes de actualizar.',
                    ]);
                }

                $sociedadActual = trim((string) $centroCosto->id_sociedad);
                $sociedadAlValidar = trim((string) $detalle->sociedad_centro_costo);
                if (
                    $actualizaSociedad
                    && $sociedadActual !== $sociedadAlValidar
                    && $sociedadActual !== trim($detalle->sociedad)
                ) {
                    throw ValidationException::withMessages([
                        'detalle' => 'La sociedad cambió después de cargar el archivo. Vuelve a analizar el CSV antes de actualizar.',
                    ]);
                }

                $centroCosto->update([
                    ...($actualizaNombre ? ['descripcion' => $detalle->nombre_agencia] : []),
                    ...($actualizaRuta ? ['id_grupo' => $detalle->ruta] : []),
                    ...($actualizaSociedad ? ['id_sociedad' => $detalle->sociedad] : []),
                    'modificado_por' => $actor,
                    'fecha_modificado' => now(),
                ]);
            }

            if ($actualizaNombre) {
                $this->registrarHistorial(
                    $detalle,
                    $centroCosto,
                    $accion,
                    'descripcion',
                    $detalle->nombre_centro_costo,
                    $detalle->nombre_agencia,
                    $observacion,
                    $usuarioId
                );
            }

            if ($actualizaRuta) {
                $this->registrarHistorial(
                    $detalle,
                    $centroCosto,
                    $accion,
                    'id_grupo',
                    $detalle->ruta_centro_costo,
                    $detalle->ruta,
                    $observacion,
                    $usuarioId
                );
            }

            if ($actualizaSociedad) {
                $this->registrarHistorial(
                    $detalle,
                    $centroCosto,
                    $accion,
                    'id_sociedad',
                    $detalle->sociedad_centro_costo,
                    $detalle->sociedad,
                    $observacion,
                    $usuarioId
                );
            }

            $detalle->update([
                'centro_costo_id' => $centroCosto->id,
                'nombre_centro_costo' => $detalle->nombre_agencia,
                'ruta_centro_costo' => $detalle->ruta,
                'sociedad_centro_costo' => $detalle->sociedad,
                'estado' => 'correcto',
                'observacion' => $accion === 'creacion'
                    ? 'Centro de Costo creado desde el validador.'
                    : 'Datos actualizados desde el validador.',
                'aplicado_en' => now(),
                'aplicado_por' => $usuarioId,
            ]);

            return [
                'accion' => $accion,
                'terminal' => $detalle->terminal,
                'nombre' => $detalle->nombre_agencia,
            ];
        });

        return response()->json([
            'ok' => true,
            'message' => $resultado['accion'] === 'creacion'
                ? "Se creó la terminal {$resultado['terminal']}."
                : "Se actualizaron los datos de la terminal {$resultado['terminal']}.",
            'data' => $resultado,
        ]);
    }

    public function historial(Request $request, string $companyId, string $terminal): JsonResponse
    {
        validator(
            ['company_id' => $companyId],
            ['company_id' => ['required', Rule::in(CentroDeCosto::EMPRESAS_VALIDAS)]]
        )->validate();

        $terminalNormalizada = $this->csvService->normalizarTerminal($terminal);
        $historial = CentroCostoHistorialCambio::query()
            ->with('usuario:id,name')
            ->where('company_id', $companyId)
            ->where('terminal_normalizada', $terminalNormalizada)
            ->latest('created_at')
            ->get()
            ->map(fn (CentroCostoHistorialCambio $cambio): array => [
                'id' => $cambio->id,
                'accion' => $cambio->accion,
                'campo' => $cambio->campo,
                'valor_anterior' => $cambio->valor_anterior,
                'valor_nuevo' => $cambio->valor_nuevo,
                'archivo_origen' => $cambio->archivo_origen,
                'observacion' => $cambio->observacion,
                'usuario' => $cambio->usuario?->name ?? 'Sistema',
                'fecha' => $cambio->created_at?->format('d/m/Y h:i A'),
            ]);

        return response()->json([
            'terminal' => $terminal,
            'company_id' => $companyId,
            'total' => $historial->count(),
            'data' => $historial,
        ]);
    }

    private function crearCentroCosto(ValidadorAgenciaDetalle $detalle, string $actor): CentroDeCosto
    {
        $centrosEmpresa = CentroDeCosto::query()
            ->where('company_id', 'like', $detalle->company_id.'%')
            ->lockForUpdate()
            ->get(['id_viejo']);
        $yaExiste = $centrosEmpresa->contains(
            fn (CentroDeCosto $centro): bool => $this->csvService->normalizarTerminal($centro->id_viejo)
                === $detalle->terminal_normalizada
        );

        if ($yaExiste) {
            throw ValidationException::withMessages([
                'detalle' => 'La terminal fue creada después de cargar el archivo. Vuelve a analizar el CSV.',
            ]);
        }

        $ultimoCentro = CentroDeCosto::query()
            ->where('company_id', 'like', $detalle->company_id.'%')
            ->orderByDesc('id_centro_costo')
            ->lockForUpdate()
            ->first(['id_centro_costo']);
        $idCentroCosto = ((int) $ultimoCentro?->id_centro_costo) + 1;

        return CentroDeCosto::query()->create([
            'id_centro_costo' => $idCentroCosto,
            'company_id' => $detalle->company_id,
            'descripcion' => $detalle->nombre_agencia,
            'id_grupo' => $detalle->ruta,
            'id_sociedad' => $detalle->sociedad,
            'id_viejo' => $detalle->terminal,
            'creado_por' => $actor,
            'fecha_grabado' => now(),
            'atributos' => [
                'ruta' => $detalle->ruta,
                'sociedad' => $detalle->sociedad,
                'origen' => 'validador_agencia',
            ],
        ]);
    }

    private function registrarHistorial(
        ValidadorAgenciaDetalle $detalle,
        CentroDeCosto $centroCosto,
        string $accion,
        string $campo,
        ?string $valorAnterior,
        string $valorNuevo,
        ?string $observacion,
        int|string|null $usuarioId
    ): void {
        CentroCostoHistorialCambio::query()->create([
            'centro_costo_id' => $centroCosto->id,
            'carga_id' => $detalle->carga_id,
            'detalle_id' => $detalle->id,
            'terminal' => $detalle->terminal,
            'terminal_normalizada' => $detalle->terminal_normalizada,
            'company_id' => $detalle->company_id,
            'accion' => $accion,
            'campo' => $campo,
            'valor_anterior' => $valorAnterior,
            'valor_nuevo' => $valorNuevo,
            'archivo_origen' => $detalle->carga->nombre_archivo,
            'observacion' => $observacion,
            'usuario_id' => $usuarioId,
        ]);
    }
}
