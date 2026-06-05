<?php

namespace App\Http\Controllers;

use App\Models\OperacionDepositoRuta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
                'data' => [],
            ]);
        }

        $query = OperacionDepositoRuta::query()->latest();
        $total = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('whatsapp_phone', 'like', "%{$search}%")
                    ->orWhere('banco', 'like', "%{$search}%")
                    ->orWhere('estado', 'like', "%{$search}%")
                    ->orWhere('comprobante_message_id', 'like', "%{$search}%");
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
            ->map(function (OperacionDepositoRuta $deposito) {
                return [
                    'id' => $deposito->id,
                    'fecha' => optional($deposito->created_at)->format('d/m/Y h:i A'),
                    'whatsapp_phone' => $deposito->whatsapp_phone,
                    'banco' => $deposito->banco,
                    'estado' => ucfirst($deposito->estado),
                    'imagen_url' => route('operaciones.deposito-ruta.imagen', $deposito),
                ];
            });

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows,
        ]);
    }

    public function imagen(OperacionDepositoRuta $deposito): RedirectResponse
    {
        return redirect()->away($deposito->comprobante_url);
    }
}
