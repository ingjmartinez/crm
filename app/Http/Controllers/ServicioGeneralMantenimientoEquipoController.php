<?php

namespace App\Http\Controllers;

use App\Models\Agencia;
use App\Models\ServicioGeneralMantenimientoEquipo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ServicioGeneralMantenimientoEquipoController extends Controller
{
    public function index()
    {
        return view('servicios-generales.mantenimiento-equipos', [
            'setupPending' => !$this->tablaExiste(),
            'terminales' => $this->terminales(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        if (!$this->tablaExiste()) {
            return response()->json([
                'data' => [],
                'stats' => $this->emptyStats(),
                'setup_pending' => true,
                'message' => 'La tabla de mantenimiento de equipos aun no existe.',
            ]);
        }

        $query = ServicioGeneralMantenimientoEquipo::query()
            ->with(['creadoPor:id,name', 'realizadoPor:id,name']);

        if ($request->filled('estado')) {
            $estado = $request->estado;

            if ($estado === 'realizado') {
                $query->where('estado', 'realizado');
            } elseif ($estado === 'vencido') {
                $query->where('estado', 'programado')->whereDate('fecha_mantenimiento', '<', now()->toDateString());
            } elseif ($estado === 'por_vencer') {
                $query->where('estado', 'programado')
                    ->whereDate('fecha_mantenimiento', '>=', now()->toDateString())
                    ->whereDate('fecha_mantenimiento', '<=', now()->copy()->addDays(30)->toDateString());
            } elseif ($estado === 'vigente') {
                $query->where('estado', 'programado')
                    ->whereDate('fecha_mantenimiento', '>', now()->copy()->addDays(30)->toDateString());
            }
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('terminal_codigo', 'like', '%' . $search . '%')
                    ->orWhere('nombre_agencia', 'like', '%' . $search . '%')
                    ->orWhere('equipo_tipo', 'like', '%' . $search . '%')
                    ->orWhere('equipo_codigo', 'like', '%' . $search . '%')
                    ->orWhere('descripcion', 'like', '%' . $search . '%');
            });
        }

        $items = $query
            ->orderByRaw("CASE WHEN estado = 'realizado' THEN 4 WHEN fecha_mantenimiento < CURDATE() THEN 1 WHEN fecha_mantenimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 2 ELSE 3 END")
            ->orderBy('fecha_mantenimiento')
            ->get()
            ->map(fn(ServicioGeneralMantenimientoEquipo $item) => $this->serialize($item));

        return response()->json([
            'data' => $items,
            'stats' => $this->stats(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$this->tablaExiste()) {
            return response()->json(['success' => false, 'message' => 'La tabla de mantenimiento aun no existe.'], 503);
        }

        $validated = $this->validatedData($request);
        $agencia = $this->buscarAgenciaPorTerminal($validated['terminal_codigo']);

        $item = ServicioGeneralMantenimientoEquipo::create([
            ...$validated,
            'terminal_codigo' => trim($validated['terminal_codigo']),
            'agencia_id' => $agencia?->id,
            'nombre_agencia' => $agencia?->nombre_agencia,
            'estado' => 'programado',
            'creado_por' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mantenimiento registrado.',
            'item' => $this->serialize($item),
        ]);
    }

    public function update(Request $request, ServicioGeneralMantenimientoEquipo $mantenimiento): JsonResponse
    {
        if (!$this->tablaExiste()) {
            return response()->json(['success' => false, 'message' => 'La tabla de mantenimiento aun no existe.'], 503);
        }

        $validated = $this->validatedData($request);
        $agencia = $this->buscarAgenciaPorTerminal($validated['terminal_codigo']);

        $mantenimiento->fill([
            ...$validated,
            'terminal_codigo' => trim($validated['terminal_codigo']),
            'agencia_id' => $agencia?->id,
            'nombre_agencia' => $agencia?->nombre_agencia,
        ]);

        if ($mantenimiento->estado === 'realizado') {
            $mantenimiento->estado = 'programado';
            $mantenimiento->realizado_at = null;
            $mantenimiento->realizado_por = null;
        }

        $mantenimiento->save();

        return response()->json([
            'success' => true,
            'message' => 'Mantenimiento actualizado.',
            'item' => $this->serialize($mantenimiento),
        ]);
    }

    public function realizar(Request $request, ServicioGeneralMantenimientoEquipo $mantenimiento): JsonResponse
    {
        if (!$this->tablaExiste()) {
            return response()->json(['success' => false, 'message' => 'La tabla de mantenimiento aun no existe.'], 503);
        }

        $validated = $request->validate([
            'observacion' => ['nullable', 'string', 'max:2000'],
        ]);

        $mantenimiento->estado = 'realizado';
        $mantenimiento->realizado_at = now();
        $mantenimiento->realizado_por = auth()->id();
        $mantenimiento->observacion = $validated['observacion'] ?? $mantenimiento->observacion;
        $mantenimiento->save();

        return response()->json([
            'success' => true,
            'message' => 'Mantenimiento marcado como realizado.',
            'item' => $this->serialize($mantenimiento),
        ]);
    }

    public function destroy(ServicioGeneralMantenimientoEquipo $mantenimiento): JsonResponse
    {
        $mantenimiento->delete();

        return response()->json([
            'success' => true,
            'message' => 'Registro eliminado.',
        ]);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'terminal_codigo' => ['required', 'string', 'max:80'],
            'equipo_tipo' => ['required', 'string', 'max:80'],
            'equipo_codigo' => ['nullable', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'fecha_mantenimiento' => ['required', 'date'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function serialize(ServicioGeneralMantenimientoEquipo $item): array
    {
        return [
            'id' => $item->id,
            'terminal_codigo' => $item->terminal_codigo,
            'agencia_id' => $item->agencia_id,
            'nombre_agencia' => $item->nombre_agencia,
            'equipo_tipo' => $item->equipo_tipo,
            'equipo_codigo' => $item->equipo_codigo,
            'descripcion' => $item->descripcion,
            'fecha_mantenimiento' => optional($item->fecha_mantenimiento)->format('Y-m-d'),
            'estado' => $item->estado,
            'estado_calculado' => $item->estado_calculado,
            'dias_restantes' => $item->dias_restantes,
            'realizado_at' => optional($item->realizado_at)->format('Y-m-d H:i:s'),
            'creado_por' => optional($item->creadoPor)->name,
            'realizado_por' => optional($item->realizadoPor)->name,
            'observacion' => $item->observacion,
        ];
    }

    private function stats(): array
    {
        if (!$this->tablaExiste()) {
            return $this->emptyStats();
        }

        return [
            'vencido' => ServicioGeneralMantenimientoEquipo::where('estado', 'programado')
                ->whereDate('fecha_mantenimiento', '<', now()->toDateString())
                ->count(),
            'por_vencer' => ServicioGeneralMantenimientoEquipo::where('estado', 'programado')
                ->whereDate('fecha_mantenimiento', '>=', now()->toDateString())
                ->whereDate('fecha_mantenimiento', '<=', now()->copy()->addDays(30)->toDateString())
                ->count(),
            'vigente' => ServicioGeneralMantenimientoEquipo::where('estado', 'programado')
                ->whereDate('fecha_mantenimiento', '>', now()->copy()->addDays(30)->toDateString())
                ->count(),
            'realizado' => ServicioGeneralMantenimientoEquipo::where('estado', 'realizado')->count(),
        ];
    }

    private function emptyStats(): array
    {
        return ['vencido' => 0, 'por_vencer' => 0, 'vigente' => 0, 'realizado' => 0];
    }

    private function terminales()
    {
        return Agencia::query()
            ->select('id', 'terminal', 'nombre_agencia', 'empresa', 'ciudad')
            ->whereNotNull('terminal')
            ->whereRaw("TRIM(COALESCE(terminal, '')) <> ''")
            ->orderBy('terminal')
            ->get();
    }

    private function buscarAgenciaPorTerminal(string $terminal): ?Agencia
    {
        return Agencia::query()
            ->whereRaw("TRIM(CAST(terminal AS CHAR)) = ?", [trim($terminal)])
            ->first();
    }

    private function tablaExiste(): bool
    {
        return Schema::hasTable('servicio_general_mantenimiento_equipos');
    }
}
