<?php

namespace App\Http\Controllers;

use App\Models\OperacionDepositoRuta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OperacionDepositoRutaController extends Controller
{
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

        $deposito->update([
            'estado' => $validated['estado'],
        ]);

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'estado' => ucfirst($deposito->estado),
        ]);
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
