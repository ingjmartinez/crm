<?php

namespace App\Http\Controllers;

use App\Models\CruceUsuarioSeguimiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CruceUsuarioSeguimientoController extends Controller
{
    public function index()
    {
        return view('tecnologia.cruce-usuarios-seguimiento', [
            'setupPending' => !$this->tableExists(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        if (!$this->tableExists()) {
            return response()->json([
                'data' => [],
                'stats' => $this->emptyStats(),
                'setup_pending' => true,
                'message' => 'La tabla de seguimiento de cruce de usuarios aun no ha sido creada.',
            ]);
        }

        $query = CruceUsuarioSeguimiento::query()
            ->with(['creadoPor:id,name', 'gestionIniciadaPor:id,name', 'finalizadoPor:id,name']);

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('cedula')) {
            $query->where('cedula', 'like', '%' . $this->normalizarCedula($request->cedula) . '%');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $cedulaSearch = $this->normalizarCedula($search);

            $query->where(function ($subQuery) use ($search, $cedulaSearch) {
                if ($cedulaSearch !== '') {
                    $subQuery->where('cedula', 'like', '%' . $cedulaSearch . '%');
                }

                $subQuery->orWhere('nombre_completo', 'like', '%' . $search . '%')
                    ->orWhere('detalle', 'like', '%' . $search . '%')
                    ->orWhere('estatus_origen', 'like', '%' . $search . '%');
            });
        }

        $items = $query
            ->orderByRaw("FIELD(estado, 'pendiente', 'en_gestion', 'finalizado')")
            ->orderByDesc('ultima_fecha_venta')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn(CruceUsuarioSeguimiento $seguimiento) => $this->serialize($seguimiento));

        return response()->json([
            'data' => $items,
            'stats' => $this->stats(),
        ]);
    }

    public function storeFromReporte(Request $request): JsonResponse
    {
        if (!$this->tableExists()) {
            return response()->json([
                'success' => false,
                'message' => 'La tabla de seguimiento aun no existe. Ejecuta la migracion pendiente.',
            ], 503);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.Identificacion' => ['required', 'string', 'max:20'],
            'items.*.Empleado_ID' => ['nullable'],
            'items.*.NombreCompleto' => ['nullable', 'string', 'max:180'],
            'items.*.Detalle' => ['nullable', 'string'],
            'items.*.Estatus' => ['required', 'string', 'max:80'],
            'items.*.Ultima_Fecha_Venta' => ['nullable', 'date'],
            'meta.sistema' => ['nullable', 'string', 'max:30'],
            'meta.empresa' => ['nullable', 'string', 'max:40'],
            'meta.fecha_inicio' => ['nullable', 'date'],
            'meta.fecha_fin' => ['nullable', 'date'],
        ]);

        $creados = 0;
        $existentes = 0;
        $seguimientos = [];

        foreach ($validated['items'] as $item) {
            $cedula = $this->normalizarCedula($item['Identificacion'] ?? '');

            if ($cedula === '') {
                continue;
            }

            $seguimiento = CruceUsuarioSeguimiento::firstOrCreate(
                [
                    'cedula' => $cedula,
                    'ultima_fecha_venta' => $item['Ultima_Fecha_Venta'] ?? null,
                    'estatus_origen' => $item['Estatus'],
                ],
                [
                    'empleado_id' => $item['Empleado_ID'] ?? null,
                    'nombre_completo' => $item['NombreCompleto'] ?? null,
                    'detalle' => $item['Detalle'] ?? null,
                    'reporte_fecha_inicio' => $validated['meta']['fecha_inicio'] ?? null,
                    'reporte_fecha_fin' => $validated['meta']['fecha_fin'] ?? null,
                    'sistema' => $validated['meta']['sistema'] ?? 'todos',
                    'empresa' => $validated['meta']['empresa'] ?? 'todos',
                    'estado' => 'pendiente',
                    'creado_por' => auth()->id(),
                ]
            );

            if ($seguimiento->wasRecentlyCreated) {
                $creados++;
            } else {
                $existentes++;
            }

            $seguimientos[] = $this->serialize($seguimiento);
        }

        return response()->json([
            'success' => true,
            'message' => "Seguimiento preparado. Nuevos: {$creados}. Ya existentes: {$existentes}.",
            'creados' => $creados,
            'existentes' => $existentes,
            'seguimientos' => $seguimientos,
        ]);
    }

    public function iniciar(CruceUsuarioSeguimiento $seguimiento): JsonResponse
    {
        if (!$this->tableExists()) {
            return response()->json(['success' => false, 'message' => 'La tabla de seguimiento aun no existe.'], 503);
        }

        if ($seguimiento->estado === 'finalizado') {
            return response()->json(['success' => false, 'message' => 'Este caso ya fue finalizado.'], 422);
        }

        if (!$seguimiento->gestion_inicio_at) {
            $seguimiento->gestion_inicio_at = now();
            $seguimiento->gestion_iniciada_por = auth()->id();
        }

        $seguimiento->estado = 'en_gestion';
        $seguimiento->save();

        return response()->json([
            'success' => true,
            'message' => 'Gestion iniciada.',
            'seguimiento' => $this->serialize($seguimiento->fresh(['creadoPor:id,name', 'gestionIniciadaPor:id,name', 'finalizadoPor:id,name'])),
        ]);
    }

    public function iniciarMasivo(Request $request): JsonResponse
    {
        if (!$this->tableExists()) {
            return response()->json(['success' => false, 'message' => 'La tabla de seguimiento aun no existe.'], 503);
        }

        $validated = $request->validate([
            'estado' => ['nullable', 'string', 'in:,pendiente,en_gestion,finalizado'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = CruceUsuarioSeguimiento::query()
            ->where('estado', 'pendiente');

        if (($validated['estado'] ?? '') !== '' && $validated['estado'] !== 'pendiente') {
            return response()->json([
                'success' => false,
                'message' => 'El inicio masivo solo aplica a casos pendientes.',
            ], 422);
        }

        if (!empty($validated['search'])) {
            $search = trim((string) $validated['search']);
            $cedulaSearch = $this->normalizarCedula($search);

            $query->where(function ($subQuery) use ($search, $cedulaSearch) {
                if ($cedulaSearch !== '') {
                    $subQuery->where('cedula', 'like', '%' . $cedulaSearch . '%');
                }

                $subQuery->orWhere('nombre_completo', 'like', '%' . $search . '%')
                    ->orWhere('detalle', 'like', '%' . $search . '%')
                    ->orWhere('estatus_origen', 'like', '%' . $search . '%');
            });
        }

        $ids = $query->pluck('id');

        if ($ids->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No hay casos pendientes para iniciar con los filtros actuales.',
                'actualizados' => 0,
            ]);
        }

        $actualizados = CruceUsuarioSeguimiento::whereIn('id', $ids)->update([
            'estado' => 'en_gestion',
            'gestion_inicio_at' => now(),
            'gestion_iniciada_por' => auth()->id(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Gestion iniciada para {$actualizados} caso(s).",
            'actualizados' => $actualizados,
            'stats' => $this->stats(),
        ]);
    }

    public function finalizar(Request $request, CruceUsuarioSeguimiento $seguimiento): JsonResponse
    {
        if (!$this->tableExists()) {
            return response()->json(['success' => false, 'message' => 'La tabla de seguimiento aun no existe.'], 503);
        }

        if ($seguimiento->estado === 'finalizado') {
            return response()->json(['success' => false, 'message' => 'Este caso ya fue finalizado.'], 422);
        }

        $validated = $request->validate([
            'observacion' => ['nullable', 'string', 'max:2000'],
        ]);

        if (!$seguimiento->gestion_inicio_at) {
            $seguimiento->gestion_inicio_at = now();
            $seguimiento->gestion_iniciada_por = auth()->id();
        }

        $seguimiento->estado = 'finalizado';
        $seguimiento->finalizado_at = now();
        $seguimiento->finalizado_por = auth()->id();
        $seguimiento->observacion = $validated['observacion'] ?? $seguimiento->observacion;
        $seguimiento->save();

        return response()->json([
            'success' => true,
            'message' => 'Caso finalizado.',
            'seguimiento' => $this->serialize($seguimiento->fresh(['creadoPor:id,name', 'gestionIniciadaPor:id,name', 'finalizadoPor:id,name'])),
        ]);
    }

    private function serialize(CruceUsuarioSeguimiento $seguimiento): array
    {
        return [
            'id' => $seguimiento->id,
            'codigo' => $seguimiento->codigo,
            'cedula' => $seguimiento->cedula,
            'empleado_id' => $seguimiento->empleado_id,
            'nombre_completo' => $seguimiento->nombre_completo,
            'detalle' => $seguimiento->detalle,
            'estatus_origen' => $seguimiento->estatus_origen,
            'ultima_fecha_venta' => optional($seguimiento->ultima_fecha_venta)->format('Y-m-d'),
            'reporte_fecha_inicio' => optional($seguimiento->reporte_fecha_inicio)->format('Y-m-d'),
            'reporte_fecha_fin' => optional($seguimiento->reporte_fecha_fin)->format('Y-m-d'),
            'sistema' => $seguimiento->sistema,
            'empresa' => $seguimiento->empresa,
            'estado' => $seguimiento->estado,
            'badge_estado' => $seguimiento->badge_estado,
            'gestion_inicio_at' => optional($seguimiento->gestion_inicio_at)->format('Y-m-d H:i:s'),
            'finalizado_at' => optional($seguimiento->finalizado_at)->format('Y-m-d H:i:s'),
            'creado_por' => optional($seguimiento->creadoPor)->name,
            'gestion_iniciada_por' => optional($seguimiento->gestionIniciadaPor)->name,
            'finalizado_por' => optional($seguimiento->finalizadoPor)->name,
            'observacion' => $seguimiento->observacion,
        ];
    }

    private function stats(): array
    {
        return [
            'pendiente' => CruceUsuarioSeguimiento::where('estado', 'pendiente')->count(),
            'en_gestion' => CruceUsuarioSeguimiento::where('estado', 'en_gestion')->count(),
            'finalizado' => CruceUsuarioSeguimiento::where('estado', 'finalizado')->count(),
        ];
    }

    private function emptyStats(): array
    {
        return ['pendiente' => 0, 'en_gestion' => 0, 'finalizado' => 0];
    }

    private function normalizarCedula(?string $cedula): string
    {
        return preg_replace('/[^0-9]/', '', (string) $cedula) ?? '';
    }

    private function tableExists(): bool
    {
        return Schema::hasTable('cruce_usuario_seguimientos');
    }
}
