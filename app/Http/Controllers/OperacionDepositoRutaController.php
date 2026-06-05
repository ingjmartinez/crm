<?php

namespace App\Http\Controllers;

use App\Models\OperacionDepositoRuta;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OperacionDepositoRutaController extends Controller
{
    public function __construct(private readonly WhatsAppService $whatsAppService)
    {
    }

    public function index(): View
    {
        return view('operaciones.deposito-ruta');
    }

    public function data(Request $request): JsonResponse
    {
        if (!Schema::hasTable('operaciones_deposito_rutas')) {
            return response()->json([
                'draw' => (int) $request->input('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'resumen' => $this->emptyResumen(),
                'data' => [],
            ]);
        }

        $hasRutaNombre = Schema::hasColumn('operaciones_deposito_rutas', 'ruta_nombre');
        $hasMontoDepositado = Schema::hasColumn('operaciones_deposito_rutas', 'monto_depositado');
        $fecha = $this->normalizeFecha($request->input('fecha')) ?? now()->toDateString();
        $baseQuery = OperacionDepositoRuta::query();
        $baseQuery->whereDate('created_at', $fecha);

        $resumen = $this->resumen((clone $baseQuery), $hasMontoDepositado);
        $query = (clone $baseQuery)->latest();
        $total = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));

        if ($search !== '') {
            $query->where(function ($q) use ($search, $hasRutaNombre) {
                $q->where('whatsapp_phone', 'like', "%{$search}%")
                    ->orWhere('banco', 'like', "%{$search}%")
                    ->orWhere('estado', 'like', "%{$search}%")
                    ->orWhere('comprobante_message_id', 'like', "%{$search}%");

                if ($hasRutaNombre) {
                    $q->orWhere('ruta_nombre', 'like', "%{$search}%");
                }
            });
        }

        $filtered = (clone $query)->count();
        $start = max((int) $request->input('start', 0), 0);
        $length = (int) $request->input('length', 25);
        $length = $length > 0 ? min($length, 100) : 25;

        $rows = $query
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function (OperacionDepositoRuta $deposito) use ($hasRutaNombre, $hasMontoDepositado) {
                $monto = $hasMontoDepositado ? (float) $deposito->monto_depositado : 0.0;

                return [
                    'id' => $deposito->id,
                    'referencia' => 'Ref-' . str_pad((string) $deposito->id, 5, '0', STR_PAD_LEFT),
                    'fecha' => optional($deposito->created_at)->format('d/m/Y h:i A'),
                    'whatsapp_phone' => $deposito->whatsapp_phone,
                    'banco' => $deposito->banco,
                    'ruta_nombre' => $hasRutaNombre ? ($deposito->ruta_nombre ?: 'No indicada') : 'No indicada',
                    'monto_depositado' => number_format($monto, 2),
                    'monto_depositado_raw' => $monto,
                    'estado' => ucfirst($deposito->estado),
                    'comprobante_url' => $deposito->comprobante_url,
                ];
            });

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'resumen' => $resumen,
            'data' => $rows,
        ]);
    }

    public function updateEstado(Request $request, OperacionDepositoRuta $deposito): JsonResponse
    {
        $validated = $request->validate([
            'estado' => ['required', 'in:pendiente,recibido'],
        ]);
        $user = $request->user();
        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('superadmin');
        $estadoAnterior = (string) $deposito->estado;

        if ($estadoAnterior === 'recibido' && !$isSuperAdmin) {
            return response()->json([
                'message' => 'Este deposito ya esta recibido. Solo superadmin puede cambiarlo nuevamente.',
            ], 403);
        }

        if ($validated['estado'] === 'pendiente' && !$isSuperAdmin) {
            return response()->json([
                'message' => 'Solo superadmin puede devolver un deposito a pendiente.',
            ], 403);
        }

        $deposito->update([
            'estado' => $validated['estado'],
        ]);

        if ($estadoAnterior !== 'recibido' && $deposito->estado === 'recibido') {
            $this->notificarDepositoRecibido($deposito);
        }

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'estado' => ucfirst($deposito->estado),
        ]);
    }

    private function notificarDepositoRecibido(OperacionDepositoRuta $deposito): void
    {
        $monto = number_format((float) ($deposito->monto_depositado ?? 0), 2);
        $ruta = trim((string) ($deposito->ruta_nombre ?? ''));
        $ruta = $ruta !== '' ? $ruta : 'No indicada';
        $message = "Deposito recibido correctamente.\n\n"
            . "Banco: {$deposito->banco}\n"
            . "Ruta: {$ruta}\n"
            . "Monto: {$monto}\n"
            . "Estado: Recibido";

        $this->whatsAppService->sendText(
            (string) $deposito->whatsapp_phone,
            $message,
            $deposito->account
        );
    }

    private function resumen($query, bool $hasMontoDepositado): array
    {
        return [
            'pendiente' => (clone $query)->where('estado', 'pendiente')->count(),
            'recibido' => (clone $query)->where('estado', 'recibido')->count(),
            'monto_total' => $hasMontoDepositado ? (float) (clone $query)->sum('monto_depositado') : 0.0,
        ];
    }

    private function emptyResumen(): array
    {
        return [
            'pendiente' => 0,
            'recibido' => 0,
            'monto_total' => 0,
        ];
    }

    private function normalizeFecha(mixed $fecha): ?string
    {
        $fecha = trim((string) $fecha);

        if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return null;
        }

        return $fecha;
    }
}
