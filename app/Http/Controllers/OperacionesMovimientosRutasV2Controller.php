<?php

namespace App\Http\Controllers;

use App\Http\Requests\Operaciones\ClasificarMovimientoRutaV2GastoRequest;
use App\Http\Requests\Operaciones\ConsultarOpcionesMovimientoRutaV2GastoRequest;
use App\Http\Requests\Operaciones\FiltrarMovimientosRutasV2Request;
use App\Http\Requests\Operaciones\GuardarMovimientoRutaV2DepositoCentralRequest;
use App\Http\Requests\Operaciones\GuardarMovimientoRutaV2DepositoRequest;
use App\Http\Requests\Operaciones\GuardarMovimientoRutaV2GastoRequest;
use App\Http\Requests\Operaciones\GuardarMovimientoRutaV2TransferenciaRequest;
use App\Http\Requests\Operaciones\ProcesarMovimientosRutasV2Request;
use App\Http\Requests\Operaciones\ReporteMovimientoRutaV2PdfRequest;
use App\Models\BancoOperacion;
use App\Models\MovimientoRutaV2Deposito;
use App\Models\MovimientoRutaV2DepositoCentral;
use App\Models\MovimientoRutaV2Gasto;
use App\Models\MovimientoRutaV2Importacion;
use App\Models\MovimientoRutaV2Transaccion;
use App\Models\MovimientoRutaV2Transferencia;
use App\Services\Operaciones\ClasificacionGastoRutaService;
use App\Services\Operaciones\MovimientosRutasV2ImportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class OperacionesMovimientosRutasV2Controller extends Controller
{
    private const EMPRESAS = [
        'GJ' => 'Grupo Joselito',
        'NG' => 'Negosur',
    ];

    public function __construct(
        private readonly MovimientosRutasV2ImportService $importService,
        private readonly ClasificacionGastoRutaService $clasificacionGastoService,
    ) {}

    public function index(FiltrarMovimientosRutasV2Request $request): View
    {
        $validated = $request->validated();
        $fechasDisponibles = $this->fechasDisponibles();
        $fecha = $this->fechaSeleccionada($request, $fechasDisponibles);
        $empresa = $validated['empresa'] ?? null;
        $rutas = $fecha !== null ? $this->resumenPorRutas($fecha, $empresa) : collect();

        return view('operaciones.movimientos-rutas-v2', [
            'fecha' => $fecha,
            'empresa' => $empresa,
            'empresas' => self::EMPRESAS,
            'fechasDisponibles' => $fechasDisponibles,
            'rutas' => $rutas,
            'resumen' => $this->resumenGeneral($rutas),
            'bancos' => BancoOperacion::query()->orderBy('empresa_id')->orderBy('nombre')->get(),
            'depositosPorBanco' => $this->depositosPorBanco($fecha, $empresa),
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

    public function guardarDeposito(GuardarMovimientoRutaV2DepositoRequest $request): RedirectResponse|JsonResponse
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

        $empresaRuta = $this->empresaDesdeRuta($movimiento->ruta);
        $empresaIdRuta = match ($empresaRuta) {
            'GJ' => '168', 'NG' => '169', default => null,
        };
        if ($empresaIdRuta !== null && $empresaIdRuta !== $validated['empresa_id']) {
            throw ValidationException::withMessages(['empresa_id' => 'La empresa seleccionada no corresponde a la ruta.']);
        }

        $banco = BancoOperacion::query()->find($validated['banco_id']);
        if ($banco?->empresa_id !== $validated['empresa_id'] || blank($banco->cuenta_codigo)) {
            throw ValidationException::withMessages([
                'banco_id' => 'Selecciona un banco con cuenta contable de la empresa de la ruta.',
            ]);
        }

        if (! empty($validated['referencia'])) {
            $duplicado = MovimientoRutaV2Deposito::query()
                ->where('fecha', $validated['fecha'])
                ->where('banco', $banco->nombre)
                ->where('empresa_id', $validated['empresa_id'])
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
            'banco' => $banco->nombre,
            'empresa_id' => $validated['empresa_id'],
            'cuenta_banco' => $banco->cuenta_codigo,
            'referencia' => filled($validated['referencia'] ?? null) ? trim($validated['referencia']) : null,
            'comprobante_path' => $comprobantePath,
            'observacion' => $validated['observacion'] ?? null,
            'estado' => 'aplicado',
            'user_id' => $request->user()?->id,
        ]);

        $message = 'Depósito aplicado correctamente a la ruta '.$movimiento->ruta.'.';

        if ($request->expectsJson()) {
            return $this->respuestaAplicacion(
                $message,
                $validated['fecha'],
                $movimiento->ruta_key,
                $validated['empresa'] ?? null,
            );
        }

        return redirect()->route('operaciones.movimientos-rutas-v2', [
            'fecha' => $validated['fecha'],
            'empresa' => $validated['empresa'] ?? null,
        ])
            ->with('success', $message);
    }

    public function guardarGasto(GuardarMovimientoRutaV2GastoRequest $request): RedirectResponse|JsonResponse
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

        $clasificacion = $this->clasificacionGastoService->resolver(
            $movimiento->ruta_key,
            $validated,
            $request->user()?->id,
        );
        $comprobantePath = $request->file('comprobante')?->store(
            'operaciones/movimientos-rutas-v2/gastos',
            'local',
        );

        MovimientoRutaV2Gasto::query()->create([
            'fecha' => $validated['fecha'],
            'ruta_key' => $movimiento->ruta_key,
            'ruta' => $movimiento->ruta,
            'monto' => $validated['monto'],
            'concepto' => $clasificacion['cuenta_descripcion'],
            'comprobante_path' => $comprobantePath,
            'observacion' => $validated['observacion'] ?? null,
            'estado' => 'aplicado',
            'user_id' => $request->user()?->id,
            ...$clasificacion,
        ]);

        $message = 'Gasto aplicado correctamente a la ruta '.$movimiento->ruta.'.';

        if ($request->expectsJson()) {
            return $this->respuestaAplicacion(
                $message,
                $validated['fecha'],
                $movimiento->ruta_key,
                $validated['empresa'] ?? null,
            );
        }

        return redirect()->route('operaciones.movimientos-rutas-v2', [
            'fecha' => $validated['fecha'],
            'empresa' => $validated['empresa'] ?? null,
        ])
            ->with('success', $message);
    }

    public function guardarTransferencia(GuardarMovimientoRutaV2TransferenciaRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $reciboPath = null;

        try {
            DB::transaction(function () use ($request, $validated, &$reciboPath): void {
                $movimientos = MovimientoRutaV2Transaccion::query()
                    ->whereDate('fecha', $validated['fecha'])
                    ->whereIn('ruta_key', [$validated['ruta_origen_key'], $validated['ruta_destino_key']])
                    ->orderBy('ruta_key')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('ruta_key');
                $origen = $movimientos->get($validated['ruta_origen_key']);
                $destino = $movimientos->get($validated['ruta_destino_key']);

                if ($origen === null || $destino === null) {
                    throw ValidationException::withMessages(['ruta_destino_key' => 'Ambas rutas deben pertenecer al reporte de la fecha seleccionada.']);
                }

                $empresaOrigen = $this->empresaDesdeRuta($origen->ruta);
                if ($empresaOrigen !== $this->empresaDesdeRuta($destino->ruta)) {
                    throw ValidationException::withMessages(['ruta_destino_key' => 'Las rutas deben pertenecer a la misma empresa.']);
                }

                $rutas = $this->resumenPorRutas($validated['fecha'])->keyBy('ruta_key');
                $excedenteCentavos = max(0, (int) round(-$rutas[$origen->ruta_key]['pendiente'] * 100));
                $montoCentavos = (int) round((float) $validated['monto'] * 100);

                if ($excedenteCentavos === 0 || $montoCentavos > $excedenteCentavos) {
                    throw ValidationException::withMessages(['monto' => 'El monto supera el excedente disponible de la ruta de origen.']);
                }

                $pendienteDestinoCentavos = (int) round($rutas[$destino->ruta_key]['pendiente'] * 100);
                if ($pendienteDestinoCentavos <= 0 || $montoCentavos > $pendienteDestinoCentavos) {
                    throw ValidationException::withMessages(['monto' => 'El monto supera el pendiente disponible de la ruta de destino.']);
                }

                $reciboPath = $request->file('recibo')->store('operaciones/movimientos-rutas-v2/transferencias', 'local');
                MovimientoRutaV2Transferencia::query()->create([
                    'fecha' => $validated['fecha'],
                    'ruta_origen_key' => $origen->ruta_key,
                    'ruta_origen' => $origen->ruta,
                    'ruta_destino_key' => $destino->ruta_key,
                    'ruta_destino' => $destino->ruta,
                    'monto' => $validated['monto'],
                    'recibo_path' => $reciboPath,
                    'user_id' => $request->user()?->id,
                ]);
            });
        } catch (\Throwable $exception) {
            if ($reciboPath !== null) {
                Storage::disk('local')->delete($reciboPath);
            }

            throw $exception;
        }

        $message = 'Fondos transferidos correctamente entre las rutas.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('operaciones.movimientos-rutas-v2', [
            'fecha' => $validated['fecha'], 'empresa' => $validated['empresa'] ?? null,
        ])->with('success', $message);
    }

    public function guardarDepositoCentral(GuardarMovimientoRutaV2DepositoCentralRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $movimiento = MovimientoRutaV2Transaccion::query()
            ->whereDate('fecha', $validated['fecha'])
            ->where('ruta_key', $validated['ruta_key'])
            ->where('id_trans', $validated['id_trans'])
            ->where('tipo', 'deposito')
            ->first();

        if ($movimiento === null || ! $this->esDepositoCentral($movimiento)) {
            throw ValidationException::withMessages(['id_trans' => 'Selecciona un depósito por central de esta ruta y fecha.']);
        }

        if ((int) round((float) $validated['monto'] * 100) !== (int) round((float) $movimiento->monto * 100)) {
            throw ValidationException::withMessages([
                'monto' => 'El monto debe coincidir exactamente con el depósito de la transacción seleccionada: RD$ '.number_format((float) $movimiento->monto, 2).'.',
            ]);
        }

        $reciboPath = $request->file('recibo')->store('operaciones/movimientos-rutas-v2/depositos-central', 'local');

        try {
            MovimientoRutaV2DepositoCentral::query()->create([
                'fecha' => $validated['fecha'],
                'ruta_key' => $movimiento->ruta_key,
                'ruta' => $movimiento->ruta,
                'id_trans' => $movimiento->id_trans,
                'monto' => $validated['monto'],
                'recibo_path' => $reciboPath,
                'user_id' => $request->user()?->id,
            ]);
        } catch (QueryException $exception) {
            Storage::disk('local')->delete($reciboPath);

            if ((string) $exception->getCode() === '23000') {
                throw ValidationException::withMessages(['id_trans' => 'Esta transacción de depósito ya fue aplicada por central.']);
            }

            throw $exception;
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($reciboPath);

            throw $exception;
        }

        $message = 'Depósito por central aplicado al cuadre de la ruta '.$movimiento->ruta.'.';

        if ($request->expectsJson()) {
            return $this->respuestaAplicacion($message, $validated['fecha'], $movimiento->ruta_key, $validated['empresa'] ?? null);
        }

        return redirect()->route('operaciones.movimientos-rutas-v2', [
            'fecha' => $validated['fecha'], 'empresa' => $validated['empresa'] ?? null,
        ])->with('success', $message);
    }

    public function reciboDepositoCentral(int $deposito_central): BinaryFileResponse
    {
        $depositoCentral = MovimientoRutaV2DepositoCentral::query()->findOrFail($deposito_central);
        $rutaRecibo = $this->resolverRutaComprobante($depositoCentral->recibo_path);
        abort_if($rutaRecibo === null, 404, 'El recibo no está disponible.');

        return response()->file($rutaRecibo);
    }

    public function eliminarDepositoCentral(int $deposito_central): JsonResponse
    {
        $depositoCentral = MovimientoRutaV2DepositoCentral::query()->findOrFail($deposito_central);
        $rutaRecibo = $this->resolverRutaComprobante($depositoCentral->recibo_path);
        $depositoCentral->delete();

        if ($rutaRecibo !== null) {
            File::delete($rutaRecibo);
        }

        return response()->json(['message' => 'Depósito por central eliminado del cuadre.']);
    }

    public function reciboTransferencia(MovimientoRutaV2Transferencia $transferencia): BinaryFileResponse
    {
        $rutaRecibo = $this->resolverRutaComprobante($transferencia->recibo_path);
        abort_if($rutaRecibo === null, 404, 'El recibo no está disponible.');

        return response()->file($rutaRecibo);
    }

    public function tokenCsrf(): JsonResponse
    {
        return response()->json(['token' => csrf_token()]);
    }

    public function opcionesGasto(ConsultarOpcionesMovimientoRutaV2GastoRequest $request): JsonResponse
    {
        return response()->json([
            'cuentas' => $this->clasificacionGastoService->cuentas(),
            'terminales' => $this->clasificacionGastoService->terminales($request->validated('ruta_key')),
        ]);
    }

    public function clasificarGasto(
        ClasificarMovimientoRutaV2GastoRequest $request,
        MovimientoRutaV2Gasto $gasto,
    ): JsonResponse {
        $gasto->update($this->clasificacionGastoService->resolver(
            $gasto->ruta_key,
            $request->validated(),
            $request->user()?->id,
        ));

        return response()->json([
            'message' => 'Gasto clasificado correctamente sin modificar sus datos originales.',
            'gasto' => $this->datosGastoDetalle($gasto->fresh()),
        ]);
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

    public function eliminarImportacion(MovimientoRutaV2Importacion $importacion): JsonResponse
    {
        $fechaDesde = $importacion->fecha_desde->toDateString();
        $fechaHasta = $importacion->fecha_hasta->toDateString();
        $depositos = MovimientoRutaV2Deposito::query()
            ->whereDate('fecha', '>=', $fechaDesde)
            ->whereDate('fecha', '<=', $fechaHasta)
            ->get(['id', 'comprobante_path']);
        $transferencias = MovimientoRutaV2Transferencia::query()
            ->whereDate('fecha', '>=', $fechaDesde)
            ->whereDate('fecha', '<=', $fechaHasta)
            ->get(['id', 'recibo_path']);
        $depositosCentral = MovimientoRutaV2DepositoCentral::query()
            ->whereDate('fecha', '>=', $fechaDesde)
            ->whereDate('fecha', '<=', $fechaHasta)
            ->get(['id', 'recibo_path']);

        $resultado = DB::transaction(function () use ($fechaDesde, $fechaHasta, $depositos, $transferencias, $depositosCentral): array {
            $depositosEliminados = $depositos->count();
            $transferenciasEliminadas = $transferencias->count();
            $depositosCentralEliminados = $depositosCentral->count();
            MovimientoRutaV2DepositoCentral::query()
                ->whereDate('fecha', '>=', $fechaDesde)
                ->whereDate('fecha', '<=', $fechaHasta)
                ->delete();
            MovimientoRutaV2Transferencia::query()
                ->whereDate('fecha', '>=', $fechaDesde)
                ->whereDate('fecha', '<=', $fechaHasta)
                ->delete();
            MovimientoRutaV2Deposito::query()
                ->whereDate('fecha', '>=', $fechaDesde)
                ->whereDate('fecha', '<=', $fechaHasta)
                ->delete();
            $transaccionesEliminadas = MovimientoRutaV2Transaccion::query()
                ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
                ->delete();
            $importacionesEliminadas = MovimientoRutaV2Importacion::query()
                ->whereDate('fecha_desde', $fechaDesde)
                ->whereDate('fecha_hasta', $fechaHasta)
                ->delete();

            return compact('transaccionesEliminadas', 'depositosEliminados', 'transferenciasEliminadas', 'depositosCentralEliminados', 'importacionesEliminadas');
        });

        $depositos
            ->pluck('comprobante_path')
            ->filter()
            ->unique()
            ->each(function (string $comprobantePath): void {
                $rutaComprobante = $this->resolverRutaComprobante($comprobantePath);

                if ($rutaComprobante !== null) {
                    File::delete($rutaComprobante);
                }
            });

        $transferencias->pluck('recibo_path')->filter()->unique()->each(function (string $reciboPath): void {
            $rutaRecibo = $this->resolverRutaComprobante($reciboPath);
            if ($rutaRecibo !== null) {
                File::delete($rutaRecibo);
            }
        });
        $depositosCentral->pluck('recibo_path')->filter()->unique()->each(function (string $reciboPath): void {
            $rutaRecibo = $this->resolverRutaComprobante($reciboPath);
            if ($rutaRecibo !== null) {
                File::delete($rutaRecibo);
            }
        });

        return response()->json([
            'message' => sprintf(
                'Carga eliminada correctamente: %d transacciones, %d depósitos, %d transferencias y %d depósitos por central eliminados. Ya puedes subir el documento corregido.',
                $resultado['transaccionesEliminadas'],
                $resultado['depositosEliminados'],
                $resultado['transferenciasEliminadas'],
                $resultado['depositosCentralEliminados'],
            ),
            'data' => $resultado,
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
            ->whereDate('fecha', $validated['fecha'])
            ->where('ruta_key', $validated['ruta_key'])
            ->orderBy('id_trans')
            ->get(['id_trans', 'terminal', 'nombre_agencia', 'tipo', 'tipo_etiqueta', 'origen', 'monto_original']);
        $transacciones->each(function (MovimientoRutaV2Transaccion $transaccion): void {
            if ($this->esDepositoCentral($transaccion)) {
                $transaccion->tipo_etiqueta = 'Depósito - Central';
            }
        });
        $depositos = MovimientoRutaV2Deposito::query()
            ->with('usuario:id,name')
            ->whereDate('fecha', $validated['fecha'])
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
            ->whereDate('fecha', $validated['fecha'])
            ->where('ruta_key', $validated['ruta_key'])
            ->latest()
            ->get()
            ->map(fn (MovimientoRutaV2Gasto $gasto): array => $this->datosGastoDetalle($gasto));

        $transferencias = MovimientoRutaV2Transferencia::query()
            ->whereDate('fecha', $validated['fecha'])
            ->where(function ($query) use ($validated): void {
                $query->where('ruta_origen_key', $validated['ruta_key'])
                    ->orWhere('ruta_destino_key', $validated['ruta_key']);
            })
            ->latest()
            ->get()
            ->map(fn (MovimientoRutaV2Transferencia $transferencia): array => [
                'fecha_registro' => $transferencia->created_at?->format('d/m/Y h:i A'),
                'tipo' => $transferencia->ruta_origen_key === $validated['ruta_key'] ? 'Enviada' : 'Recibida',
                'otra_ruta' => $transferencia->ruta_origen_key === $validated['ruta_key'] ? $transferencia->ruta_destino : $transferencia->ruta_origen,
                'monto' => (float) $transferencia->monto,
                'recibo_url' => route('operaciones.movimientos-rutas-v2.transferencias.recibo', $transferencia),
            ]);

        $depositosCentral = MovimientoRutaV2DepositoCentral::query()
            ->whereDate('fecha', $validated['fecha'])
            ->where('ruta_key', $validated['ruta_key'])
            ->latest()
            ->get()
            ->map(fn (MovimientoRutaV2DepositoCentral $depositoCentral): array => [
                'fecha_registro' => $depositoCentral->created_at?->format('d/m/Y h:i A'),
                'id_trans' => $depositoCentral->id_trans,
                'monto' => (float) $depositoCentral->monto,
                'recibo_url' => route('operaciones.movimientos-rutas-v2.depositos-central.recibo', $depositoCentral),
                'eliminar_url' => route('operaciones.movimientos-rutas-v2.depositos-central.eliminar', $depositoCentral),
            ]);

        return response()->json(['transacciones' => $transacciones, 'depositos' => $depositos, 'gastos' => $gastos, 'transferencias' => $transferencias, 'depositos_central' => $depositosCentral]);
    }

    /** @return array<string, mixed> */
    private function datosGastoDetalle(MovimientoRutaV2Gasto $gasto): array
    {
        return [
            'id' => $gasto->id,
            'ruta_key' => $gasto->ruta_key,
            'fecha_registro' => $gasto->created_at?->format('d/m/Y h:i A'),
            'monto' => (float) $gasto->monto,
            'concepto' => $gasto->concepto,
            'cuenta_codigo' => $gasto->cuenta_codigo,
            'cuenta_descripcion' => $gasto->cuenta_descripcion,
            'distribucion_tipo' => $gasto->distribucion_tipo,
            'centro_costo_id' => $gasto->centro_costo_id,
            'terminal_destino' => $gasto->terminal_destino,
            'agencia_destino' => $gasto->agencia_destino,
            'socio_nombre' => $gasto->socio_nombre,
            'clasificado' => filled($gasto->cuenta_codigo) && filled($gasto->distribucion_tipo),
            'observacion' => $gasto->observacion,
            'estado' => $gasto->estado,
            'usuario' => $gasto->usuario?->name ?? 'Sistema',
            'comprobante_url' => $this->resolverRutaComprobante($gasto->comprobante_path) !== null
                ? route('operaciones.movimientos-rutas-v2.gastos.comprobante', $gasto)
                : null,
            'clasificar_url' => route('operaciones.movimientos-rutas-v2.gastos.clasificar', $gasto),
            'eliminar_url' => route('operaciones.movimientos-rutas-v2.gastos.eliminar', $gasto),
        ];
    }

    public function pdf(ReporteMovimientoRutaV2PdfRequest $request): Response
    {
        $validated = $request->validated();
        $empresa = $validated['empresa'] ?? null;
        $rutas = $this->resumenPorRutas($validated['fecha'], $empresa);

        abort_if($rutas->isEmpty(), 404, 'No se encontraron movimientos para la fecha seleccionada.');

        $documento = Pdf::loadView('operaciones.movimientos-rutas-v2-pdf', [
            'fecha' => $validated['fecha'],
            'empresaNombre' => $empresa !== null ? self::EMPRESAS[$empresa] : 'Todas las empresas',
            'resumen' => $this->resumenGeneral($rutas),
        ])->setPaper('letter', 'portrait');

        return $documento->download("resumen-movimientos-rutas-{$validated['fecha']}.pdf");
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

    /**
     * @param  Collection<int, array<string, mixed>>  $rutas
     * @return array{rutas: int, transacciones: int, neto_esperado: float, depositado_banco: float, gastos_ruta: float, pendiente: float, cumplimiento_depositos: float}
     */
    private function resumenGeneral(Collection $rutas): array
    {
        $netoEsperado = (float) $rutas->sum('neto_esperado');
        $depositadoBanco = (float) $rutas->sum('depositado_banco');

        return [
            'rutas' => $rutas->count(),
            'transacciones' => (int) $rutas->sum('transacciones'),
            'neto_esperado' => $netoEsperado,
            'depositado_banco' => $depositadoBanco,
            'gastos_ruta' => (float) $rutas->sum('gastos_ruta'),
            'pendiente' => (float) $rutas->sum('pendiente'),
            'cumplimiento_depositos' => $netoEsperado > 0 ? max(0, (($netoEsperado - (float) $rutas->sum('pendiente')) / $netoEsperado) * 100) : 0,
        ];
    }

    private function depositosPorBanco(?string $fecha, ?string $empresa = null): Collection
    {
        if ($fecha === null) {
            return collect();
        }

        return MovimientoRutaV2Deposito::query()
            ->whereDate('fecha', $fecha)
            ->where('estado', 'aplicado')
            ->when($empresa !== null, function ($query) use ($empresa): void {
                $query->where(function ($query) use ($empresa): void {
                    $query->where('ruta', 'like', "% {$empresa} %")
                        ->orWhere('ruta', 'like', "{$empresa} %")
                        ->orWhere('ruta', 'like', "% {$empresa}");
                });
            })
            ->select('banco')
            ->selectRaw('COUNT(*) as cantidad_depositos')
            ->selectRaw('SUM(monto) as monto_total')
            ->groupBy('banco')
            ->orderByDesc('monto_total')
            ->get();
    }

    private function respuestaAplicacion(string $message, string $fecha, string $rutaKey, ?string $empresa): JsonResponse
    {
        $rutas = $this->resumenPorRutas($fecha, $empresa);
        $ruta = $rutas->firstWhere('ruta_key', $rutaKey);

        abort_if($ruta === null, 404, 'No se pudo recalcular la ruta actualizada.');

        return response()->json([
            'message' => $message,
            'ruta' => $ruta,
            'resumen' => $this->resumenGeneral($rutas),
            'depositos_por_banco' => $this->depositosPorBanco($fecha, $empresa)
                ->map(fn (MovimientoRutaV2Deposito $deposito): array => [
                    'banco' => $deposito->banco,
                    'cantidad_depositos' => (int) $deposito->cantidad_depositos,
                    'monto_total' => (float) $deposito->monto_total,
                ])
                ->values(),
        ]);
    }

    private function resumenPorRutas(string $fecha, ?string $empresa = null): Collection
    {
        $movimientos = MovimientoRutaV2Transaccion::query()
            ->whereDate('fecha', $fecha)
            ->select('ruta_key')
            ->selectRaw('MAX(ruta) as ruta')
            ->selectRaw('COUNT(*) as transacciones')
            ->selectRaw("SUM(CASE WHEN tipo = 'deposito' THEN monto ELSE 0 END) as depositos_csv")
            ->selectRaw("SUM(CASE WHEN tipo = 'retiro' THEN monto ELSE 0 END) as retiros")
            ->groupBy('ruta_key')
            ->get()
            ->keyBy('ruta_key');
        $depositos = MovimientoRutaV2Deposito::query()
            ->whereDate('fecha', $fecha)
            ->where('estado', 'aplicado')
            ->select('ruta_key')
            ->selectRaw('MAX(ruta) as ruta')
            ->selectRaw('COUNT(*) as cantidad_depositos')
            ->selectRaw('SUM(monto) as depositado_banco')
            ->groupBy('ruta_key')
            ->get()
            ->keyBy('ruta_key');
        $gastos = MovimientoRutaV2Gasto::query()
            ->whereDate('fecha', $fecha)
            ->where('estado', 'aplicado')
            ->select('ruta_key')
            ->selectRaw('COUNT(*) as cantidad_gastos')
            ->selectRaw('SUM(monto) as gastos_ruta')
            ->groupBy('ruta_key')
            ->get()
            ->keyBy('ruta_key');
        $transferencias = MovimientoRutaV2Transferencia::query()
            ->whereDate('fecha', $fecha)
            ->get(['ruta_origen_key', 'ruta_destino_key', 'monto']);
        $depositosCentral = MovimientoRutaV2DepositoCentral::query()
            ->whereDate('fecha', $fecha)
            ->select('ruta_key')
            ->selectRaw('SUM(monto) as monto_total')
            ->groupBy('ruta_key')
            ->get()
            ->keyBy('ruta_key');

        $movimientosHistoricos = MovimientoRutaV2Transaccion::query()
            ->whereDate('fecha', '<=', $fecha)
            ->select('ruta_key')
            ->selectRaw("SUM(CASE WHEN tipo = 'retiro' THEN monto WHEN tipo = 'deposito' THEN -monto ELSE 0 END) as neto_esperado")
            ->groupBy('ruta_key')
            ->get()
            ->keyBy('ruta_key');
        $depositosHistoricos = MovimientoRutaV2Deposito::query()
            ->whereDate('fecha', '<=', $fecha)
            ->where('estado', 'aplicado')
            ->select('ruta_key')
            ->selectRaw('SUM(monto) as depositado_banco')
            ->groupBy('ruta_key')
            ->get()
            ->keyBy('ruta_key');
        $gastosHistoricos = MovimientoRutaV2Gasto::query()
            ->whereDate('fecha', '<=', $fecha)
            ->where('estado', 'aplicado')
            ->select('ruta_key')
            ->selectRaw('SUM(monto) as gastos_ruta')
            ->groupBy('ruta_key')
            ->get()
            ->keyBy('ruta_key');
        $transferenciasHistoricas = MovimientoRutaV2Transferencia::query()
            ->whereDate('fecha', '<=', $fecha)
            ->get(['ruta_origen_key', 'ruta_destino_key', 'monto']);

        return $movimientos->map(function (MovimientoRutaV2Transaccion $movimiento) use ($depositos, $gastos, $transferencias, $depositosCentral, $movimientosHistoricos, $depositosHistoricos, $gastosHistoricos, $transferenciasHistoricas): array {
            $deposito = $depositos->get($movimiento->ruta_key);
            $gasto = $gastos->get($movimiento->ruta_key);
            $depositosCsv = (float) $movimiento->depositos_csv;
            $montoCentral = (float) ($depositosCentral->get($movimiento->ruta_key)?->monto_total ?? 0);
            $retiros = (float) $movimiento->retiros;
            $neto = $retiros - $depositosCsv;
            $depositadoBanco = (float) ($deposito?->depositado_banco ?? 0);
            $gastosRuta = (float) ($gasto?->gastos_ruta ?? 0);
            $transferido = (float) $transferencias->where('ruta_origen_key', $movimiento->ruta_key)->sum('monto');
            $recibido = (float) $transferencias->where('ruta_destino_key', $movimiento->ruta_key)->sum('monto');
            $pendiente = $neto - $depositadoBanco - $gastosRuta + $transferido - $recibido + $montoCentral;
            $estado = $montoCentral > 0
                ? ($pendiente < -0.009 ? 'excedida' : ($pendiente > 0.009 ? 'pendiente' : 'conciliada'))
                : $this->estadoConciliacion($neto, $depositadoBanco + $gastosRuta - $transferido + $recibido);
            $balancePendiente = (float) ($movimientosHistoricos->get($movimiento->ruta_key)?->neto_esperado ?? 0)
                - (float) ($depositosHistoricos->get($movimiento->ruta_key)?->depositado_banco ?? 0)
                - (float) ($gastosHistoricos->get($movimiento->ruta_key)?->gastos_ruta ?? 0)
                + (float) $transferenciasHistoricas->where('ruta_origen_key', $movimiento->ruta_key)->sum('monto')
                - (float) $transferenciasHistoricas->where('ruta_destino_key', $movimiento->ruta_key)->sum('monto');

            return [
                'ruta_key' => $movimiento->ruta_key,
                'ruta' => $movimiento->ruta,
                'transacciones' => (int) $movimiento->transacciones,
                'depositos_csv' => $depositosCsv,
                'retiros' => $retiros,
                'depositos_central' => $montoCentral,
                'neto_esperado' => $neto,
                'depositado_banco' => $depositadoBanco,
                'cantidad_depositos' => (int) ($deposito?->cantidad_depositos ?? 0),
                'gastos_ruta' => $gastosRuta,
                'transferido' => $transferido,
                'recibido' => $recibido,
                'cantidad_gastos' => (int) ($gasto?->cantidad_gastos ?? 0),
                'pendiente' => $pendiente,
                'balance_pendiente' => $balancePendiente,
                'cumplimiento' => $neto > 0 ? max(0, (($neto - $pendiente) / $neto) * 100) : 0,
                'estado' => $estado,
            ];
        })->when(
            $empresa !== null,
            fn (Collection $rutas): Collection => $rutas->filter(
                fn (array $ruta): bool => $this->empresaDesdeRuta($ruta['ruta']) === $empresa
            )
        )->sortBy('ruta', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    private function empresaDesdeRuta(string $ruta): ?string
    {
        if (preg_match('/(?:^|[\s-])(GJ|NG)(?=$|[\s-])/iu', $ruta, $coincidencia) !== 1) {
            return null;
        }

        return strtoupper($coincidencia[1]);
    }

    private function esDepositoCentral(MovimientoRutaV2Transaccion $transaccion): bool
    {
        return $transaccion->tipo === 'deposito'
            && strcasecmp(trim($transaccion->origen ?? ''), 'CAJA DE PREMIOS RECAUDO') === 0;
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
