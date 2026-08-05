<?php

namespace App\Http\Controllers;

use App\Http\Requests\Operaciones\GuardarMovimientoRutaV2DepositoRequest;
use App\Http\Requests\Operaciones\GuardarMovimientoRutaV2GastoRequest;
use App\Http\Requests\Operaciones\ProcesarMovimientosRutasV2Request;
use App\Http\Requests\Operaciones\ReporteMovimientoRutaV2PdfRequest;
use App\Models\BancoOperacion;
use App\Models\MovimientoRutaV2Deposito;
use App\Models\MovimientoRutaV2Gasto;
use App\Models\MovimientoRutaV2Importacion;
use App\Models\MovimientoRutaV2Transaccion;
use App\Services\Operaciones\MovimientosRutasV2ImportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class OperacionesMovimientosRutasV2Controller extends Controller
{
    public function __construct(private readonly MovimientosRutasV2ImportService $importService) {}

    public function index(Request $request): View
    {
        $fechasDisponibles = $this->fechasDisponibles();
        $fecha = $this->fechaSeleccionada($request, $fechasDisponibles);
        $rutas = $fecha !== null ? $this->resumenPorRutas($fecha) : collect();
        $netoEsperado = (float) $rutas->sum('neto_esperado');
        $depositadoBanco = (float) $rutas->sum('depositado_banco');

        return view('operaciones.movimientos-rutas-v2', [
            'fecha' => $fecha,
            'fechasDisponibles' => $fechasDisponibles,
            'rutas' => $rutas,
            'resumen' => [
                'rutas' => $rutas->count(),
                'transacciones' => $rutas->sum('transacciones'),
                'neto_esperado' => $netoEsperado,
                'depositado_banco' => $depositadoBanco,
                'gastos_ruta' => $rutas->sum('gastos_ruta'),
                'pendiente' => $rutas->sum('pendiente'),
                'cumplimiento_depositos' => $netoEsperado > 0 ? ($depositadoBanco / $netoEsperado) * 100 : 0,
            ],
            'bancos' => BancoOperacion::query()->orderBy('nombre')->get(),
            'depositosPorBanco' => $this->depositosPorBanco($fecha),
            'importaciones' => $this->importacionesPorFecha($fecha),
        ]);
    }

    public function procesar(ProcesarMovimientosRutasV2Request $request): RedirectResponse
    {
        $validated = $request->validated();
        /** @var UploadedFile $archivo */
        $archivo = $validated['archivo_csv'];
        $resultado = $this->importService->importar($archivo, $request->user()?->id, $validated['fecha_reporte']);
        $fecha = $resultado['fechas'][array_key_last($resultado['fechas'])];

        return redirect()->route('operaciones.movimientos-rutas-v2', ['fecha' => $fecha])
            ->with('success', sprintf(
                'Importación completada. Se reemplazaron %d fecha(s), con %d filas aceptadas y %d descartadas.',
                count($resultado['fechas']),
                $resultado['control']['filas_aceptadas'],
                $resultado['control']['filas_descartadas'],
            ));
    }

    public function guardarDeposito(GuardarMovimientoRutaV2DepositoRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $movimiento = MovimientoRutaV2Transaccion::query()
            ->where('fecha', $validated['fecha'])
            ->where('ruta_key', $validated['ruta_key'])
            ->first();

        if ($movimiento === null) {
            throw ValidationException::withMessages([
                'ruta' => 'La ruta no pertenece al reporte de la fecha seleccionada.',
            ]);
        }

        if (! empty($validated['referencia'])) {
            $duplicado = MovimientoRutaV2Deposito::query()
                ->where('fecha', $validated['fecha'])
                ->where('banco', $validated['banco'])
                ->where('referencia', $validated['referencia'])
                ->exists();

            if ($duplicado) {
                throw ValidationException::withMessages([
                    'referencia' => 'Ya existe un depósito de ese banco con la misma referencia para esta fecha.',
                ]);
            }
        }

        $comprobantePath = $request->file('comprobante')?->store(
            'operaciones/movimientos-rutas-v2/comprobantes',
            'local',
        );

        MovimientoRutaV2Deposito::query()->create([
            'fecha' => $validated['fecha'],
            'ruta_key' => $movimiento->ruta_key,
            'ruta' => $movimiento->ruta,
            'monto' => $validated['monto'],
            'banco' => trim($validated['banco']),
            'referencia' => filled($validated['referencia'] ?? null) ? trim($validated['referencia']) : null,
            'comprobante_path' => $comprobantePath,
            'observacion' => $validated['observacion'] ?? null,
            'estado' => 'aplicado',
            'user_id' => $request->user()?->id,
        ]);

        return redirect()->route('operaciones.movimientos-rutas-v2', ['fecha' => $validated['fecha']])
            ->with('success', 'Depósito aplicado correctamente a la ruta '.$movimiento->ruta.'.');
    }

    public function guardarGasto(GuardarMovimientoRutaV2GastoRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $movimiento = MovimientoRutaV2Transaccion::query()
            ->where('fecha', $validated['fecha'])
            ->where('ruta_key', $validated['ruta_key'])
            ->first();

        if ($movimiento === null) {
            throw ValidationException::withMessages([
                'ruta' => 'La ruta no pertenece al reporte de la fecha seleccionada.',
            ]);
        }

        $comprobantePath = $request->file('comprobante')?->store(
            'operaciones/movimientos-rutas-v2/gastos',
            'local',
        );

        MovimientoRutaV2Gasto::query()->create([
            'fecha' => $validated['fecha'],
            'ruta_key' => $movimiento->ruta_key,
            'ruta' => $movimiento->ruta,
            'monto' => $validated['monto'],
            'concepto' => trim($validated['concepto']),
            'comprobante_path' => $comprobantePath,
            'observacion' => $validated['observacion'] ?? null,
            'estado' => 'aplicado',
            'user_id' => $request->user()?->id,
        ]);

        return redirect()->route('operaciones.movimientos-rutas-v2', ['fecha' => $validated['fecha']])
            ->with('success', 'Gasto aplicado correctamente a la ruta '.$movimiento->ruta.'.');
    }

    public function eliminarDeposito(MovimientoRutaV2Deposito $deposito): JsonResponse
    {
        $rutaComprobante = $this->resolverRutaComprobante($deposito->comprobante_path);
        $deposito->delete();

        if ($rutaComprobante !== null) {
            File::delete($rutaComprobante);
        }

        return response()->json([
            'message' => 'Depósito eliminado correctamente. Ya puedes cargar el registro nuevamente.',
        ]);
    }

    public function eliminarGasto(MovimientoRutaV2Gasto $gasto): JsonResponse
    {
        $rutaComprobante = $this->resolverRutaComprobante($gasto->comprobante_path);
        $gasto->delete();

        if ($rutaComprobante !== null) {
            File::delete($rutaComprobante);
        }

        return response()->json([
            'message' => 'Gasto eliminado correctamente. Ya puedes cargar el registro nuevamente.',
        ]);
    }

    public function detalle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha' => ['required', 'date'],
            'ruta_key' => ['required', 'string', 'max:180'],
        ]);

        $transacciones = MovimientoRutaV2Transaccion::query()
            ->where('fecha', $validated['fecha'])
            ->where('ruta_key', $validated['ruta_key'])
            ->orderBy('id_trans')
            ->get(['id_trans', 'terminal', 'nombre_agencia', 'tipo', 'tipo_etiqueta', 'monto_original']);
        $depositos = MovimientoRutaV2Deposito::query()
            ->with('usuario:id,name')
            ->where('fecha', $validated['fecha'])
            ->where('ruta_key', $validated['ruta_key'])
            ->latest()
            ->get()
            ->map(fn (MovimientoRutaV2Deposito $deposito): array => [
                'id' => $deposito->id,
                'fecha_registro' => $deposito->created_at?->format('d/m/Y h:i A'),
                'monto' => (float) $deposito->monto,
                'banco' => $deposito->banco,
                'referencia' => $deposito->referencia,
                'observacion' => $deposito->observacion,
                'estado' => $deposito->estado,
                'usuario' => $deposito->usuario?->name ?? 'Sistema',
                'comprobante_url' => $this->resolverRutaComprobante($deposito->comprobante_path) !== null
                    ? route('operaciones.movimientos-rutas-v2.depositos.comprobante', $deposito)
                    : null,
                'eliminar_url' => route('operaciones.movimientos-rutas-v2.depositos.eliminar', $deposito),
            ]);

        $gastos = MovimientoRutaV2Gasto::query()
            ->with('usuario:id,name')
            ->where('fecha', $validated['fecha'])
            ->where('ruta_key', $validated['ruta_key'])
            ->latest()
            ->get()
            ->map(fn (MovimientoRutaV2Gasto $gasto): array => [
                'id' => $gasto->id,
                'fecha_registro' => $gasto->created_at?->format('d/m/Y h:i A'),
                'monto' => (float) $gasto->monto,
                'concepto' => $gasto->concepto,
                'observacion' => $gasto->observacion,
                'estado' => $gasto->estado,
                'usuario' => $gasto->usuario?->name ?? 'Sistema',
                'comprobante_url' => $this->resolverRutaComprobante($gasto->comprobante_path) !== null
                    ? route('operaciones.movimientos-rutas-v2.gastos.comprobante', $gasto)
                    : null,
                'eliminar_url' => route('operaciones.movimientos-rutas-v2.gastos.eliminar', $gasto),
            ]);

        return response()->json(['transacciones' => $transacciones, 'depositos' => $depositos, 'gastos' => $gastos]);
    }

    public function pdf(ReporteMovimientoRutaV2PdfRequest $request): Response
    {
        $validated = $request->validated();
        $resumenRuta = $this->resumenPorRutas($validated['fecha'])
            ->firstWhere('ruta_key', $validated['ruta_key']);

        abort_if($resumenRuta === null, 404, 'No se encontró la ruta para la fecha seleccionada.');

        $transacciones = MovimientoRutaV2Transaccion::query()
            ->where('fecha', $validated['fecha'])
            ->where('ruta_key', $validated['ruta_key'])
            ->orderBy('id_trans')
            ->get();
        $depositos = MovimientoRutaV2Deposito::query()
            ->with('usuario:id,name')
            ->where('fecha', $validated['fecha'])
            ->where('ruta_key', $validated['ruta_key'])
            ->latest()
            ->get();
        $gastos = MovimientoRutaV2Gasto::query()
            ->with('usuario:id,name')
            ->where('fecha', $validated['fecha'])
            ->where('ruta_key', $validated['ruta_key'])
            ->latest()
            ->get();
        $documento = Pdf::loadView('operaciones.movimientos-rutas-v2-pdf', [
            'fecha' => $validated['fecha'],
            'resumenRuta' => $resumenRuta,
            'transacciones' => $transacciones,
            'depositos' => $depositos,
            'gastos' => $gastos,
        ])->setPaper('letter', 'landscape');
        $nombreRuta = Str::slug($resumenRuta['ruta']);

        return $documento->download("movimientos-ruta-{$nombreRuta}-{$validated['fecha']}.pdf");
    }

    public function comprobante(MovimientoRutaV2Deposito $deposito): BinaryFileResponse
    {
        $rutaComprobante = $this->resolverRutaComprobante($deposito->comprobante_path);

        if ($rutaComprobante === null) {
            Log::warning('Comprobante de depósito V2 no encontrado.', [
                'deposito_id' => $deposito->id,
                'comprobante_path' => $deposito->comprobante_path,
                'local_root' => config('filesystems.disks.local.root'),
            ]);

            abort(404, 'El comprobante no está disponible en el almacenamiento del servidor.');
        }

        return response()->file($rutaComprobante);
    }

    public function comprobanteGasto(MovimientoRutaV2Gasto $gasto): BinaryFileResponse
    {
        $rutaComprobante = $this->resolverRutaComprobante($gasto->comprobante_path);

        if ($rutaComprobante === null) {
            Log::warning('Comprobante de gasto V2 no encontrado.', [
                'gasto_id' => $gasto->id,
                'comprobante_path' => $gasto->comprobante_path,
                'local_root' => config('filesystems.disks.local.root'),
            ]);

            abort(404, 'El comprobante no está disponible en el almacenamiento del servidor.');
        }

        return response()->file($rutaComprobante);
    }

    private function resolverRutaComprobante(?string $comprobantePath): ?string
    {
        if ($comprobantePath === null || trim($comprobantePath) === '') {
            return null;
        }

        $rutaRelativa = ltrim(str_replace('\\', '/', trim($comprobantePath)), '/');

        if (in_array('..', explode('/', $rutaRelativa), true)) {
            return null;
        }

        $discoLocal = Storage::disk('local');

        if ($discoLocal->exists($rutaRelativa)) {
            $rutaActual = $discoLocal->path($rutaRelativa);

            if (is_file($rutaActual) && is_readable($rutaActual)) {
                return $rutaActual;
            }
        }

        $rutasCompatibles = [
            storage_path('app/'.$rutaRelativa),
            storage_path('app/private/'.$rutaRelativa),
            storage_path('app/public/'.$rutaRelativa),
        ];

        foreach (array_unique($rutasCompatibles) as $rutaCompatible) {
            if (is_file($rutaCompatible) && is_readable($rutaCompatible)) {
                return $rutaCompatible;
            }
        }

        return null;
    }

    /** @param  array<int, string>  $fechasDisponibles */
    private function fechaSeleccionada(Request $request, array $fechasDisponibles): ?string
    {
        $solicitada = trim((string) $request->input('fecha', ''));

        if ($solicitada !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $solicitada)) {
            return $solicitada;
        }

        return $fechasDisponibles[0] ?? null;
    }

    /** @return array<int, string> */
    private function fechasDisponibles(): array
    {
        return MovimientoRutaV2Transaccion::query()
            ->select('fecha')
            ->distinct()
            ->orderByDesc('fecha')
            ->pluck('fecha')
            ->map(fn (mixed $fecha): string => substr((string) $fecha, 0, 10))
            ->all();
    }

    private function importacionesPorFecha(?string $fecha): Collection
    {
        if ($fecha === null) {
            return collect();
        }

        return MovimientoRutaV2Importacion::query()
            ->with('usuario:id,name')
            ->whereDate('fecha_desde', $fecha)
            ->whereDate('fecha_hasta', $fecha)
            ->latest()
            ->limit(10)
            ->get();
    }

    private function depositosPorBanco(?string $fecha): Collection
    {
        if ($fecha === null) {
            return collect();
        }

        return MovimientoRutaV2Deposito::query()
            ->where('fecha', $fecha)
            ->where('estado', 'aplicado')
            ->select('banco')
            ->selectRaw('COUNT(*) as cantidad_depositos')
            ->selectRaw('SUM(monto) as monto_total')
            ->groupBy('banco')
            ->orderByDesc('monto_total')
            ->get();
    }

    private function resumenPorRutas(string $fecha): Collection
    {
        $movimientos = MovimientoRutaV2Transaccion::query()
            ->where('fecha', $fecha)
            ->select('ruta_key')
            ->selectRaw('MAX(ruta) as ruta')
            ->selectRaw('COUNT(*) as transacciones')
            ->selectRaw("SUM(CASE WHEN tipo = 'deposito' THEN monto ELSE 0 END) as depositos_csv")
            ->selectRaw("SUM(CASE WHEN tipo = 'retiro' THEN monto ELSE 0 END) as retiros")
            ->groupBy('ruta_key')
            ->get()
            ->keyBy('ruta_key');
        $depositos = MovimientoRutaV2Deposito::query()
            ->where('fecha', $fecha)
            ->where('estado', 'aplicado')
            ->select('ruta_key')
            ->selectRaw('MAX(ruta) as ruta')
            ->selectRaw('COUNT(*) as cantidad_depositos')
            ->selectRaw('SUM(monto) as depositado_banco')
            ->groupBy('ruta_key')
            ->get()
            ->keyBy('ruta_key');
        $gastos = MovimientoRutaV2Gasto::query()
            ->where('fecha', $fecha)
            ->where('estado', 'aplicado')
            ->select('ruta_key')
            ->selectRaw('COUNT(*) as cantidad_gastos')
            ->selectRaw('SUM(monto) as gastos_ruta')
            ->groupBy('ruta_key')
            ->get()
            ->keyBy('ruta_key');

        $movimientosHistoricos = MovimientoRutaV2Transaccion::query()
            ->where('fecha', '<=', $fecha)
            ->select('ruta_key')
            ->selectRaw("SUM(CASE WHEN tipo = 'retiro' THEN monto WHEN tipo = 'deposito' THEN -monto ELSE 0 END) as neto_esperado")
            ->groupBy('ruta_key')
            ->get()
            ->keyBy('ruta_key');
        $depositosHistoricos = MovimientoRutaV2Deposito::query()
            ->where('fecha', '<=', $fecha)
            ->where('estado', 'aplicado')
            ->select('ruta_key')
            ->selectRaw('SUM(monto) as depositado_banco')
            ->groupBy('ruta_key')
            ->get()
            ->keyBy('ruta_key');
        $gastosHistoricos = MovimientoRutaV2Gasto::query()
            ->where('fecha', '<=', $fecha)
            ->where('estado', 'aplicado')
            ->select('ruta_key')
            ->selectRaw('SUM(monto) as gastos_ruta')
            ->groupBy('ruta_key')
            ->get()
            ->keyBy('ruta_key');

        return $movimientos->map(function (MovimientoRutaV2Transaccion $movimiento) use ($depositos, $gastos, $movimientosHistoricos, $depositosHistoricos, $gastosHistoricos): array {
            $deposito = $depositos->get($movimiento->ruta_key);
            $gasto = $gastos->get($movimiento->ruta_key);
            $depositosCsv = (float) $movimiento->depositos_csv;
            $retiros = (float) $movimiento->retiros;
            $neto = $retiros - $depositosCsv;
            $depositadoBanco = (float) ($deposito?->depositado_banco ?? 0);
            $gastosRuta = (float) ($gasto?->gastos_ruta ?? 0);
            $pendiente = $neto - $depositadoBanco - $gastosRuta;
            $balancePendiente = (float) ($movimientosHistoricos->get($movimiento->ruta_key)?->neto_esperado ?? 0)
                - (float) ($depositosHistoricos->get($movimiento->ruta_key)?->depositado_banco ?? 0)
                - (float) ($gastosHistoricos->get($movimiento->ruta_key)?->gastos_ruta ?? 0);

            return [
                'ruta_key' => $movimiento->ruta_key,
                'ruta' => $movimiento->ruta,
                'transacciones' => (int) $movimiento->transacciones,
                'depositos_csv' => $depositosCsv,
                'retiros' => $retiros,
                'neto_esperado' => $neto,
                'depositado_banco' => $depositadoBanco,
                'cantidad_depositos' => (int) ($deposito?->cantidad_depositos ?? 0),
                'gastos_ruta' => $gastosRuta,
                'cantidad_gastos' => (int) ($gasto?->cantidad_gastos ?? 0),
                'pendiente' => $pendiente,
                'balance_pendiente' => $balancePendiente,
                'cumplimiento' => $neto > 0 ? (($depositadoBanco + $gastosRuta) / $neto) * 100 : 0,
                'estado' => $this->estadoConciliacion($neto, $depositadoBanco + $gastosRuta),
            ];
        })->sortBy('ruta', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    private function estadoConciliacion(float $neto, float $depositado): string
    {
        if ($depositado > $neto + 0.009) {
            return 'excedida';
        }

        if (abs($neto - $depositado) <= 0.009) {
            return 'conciliada';
        }

        return $depositado > 0 ? 'parcial' : 'pendiente';
    }
}
