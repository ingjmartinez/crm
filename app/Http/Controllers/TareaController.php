<?php

namespace App\Http\Controllers;

use App\Models\Tarea;
use App\Models\TareaComentario;
use App\Models\DepartamentoCrm;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TareaController extends Controller
{
    /**
     * Vista principal — Dashboard + Gantt.
     */
    public function index()
    {
        $departamentos = DepartamentoCrm::activos()->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();

        // Estadísticas rápidas
        $stats = [
            'total' => Tarea::count(),
            'pendientes' => Tarea::where('estado', 'pendiente')->count(),
            'en_progreso' => Tarea::where('estado', 'en_progreso')->count(),
            'completadas' => Tarea::where('estado', 'completada')->count(),
            'atrasadas' => Tarea::atrasadas()->count(),
        ];

        return view('tareas.index', compact('departamentos', 'usuarios', 'stats'));
    }

    /**
     * API — Tareas para el diagrama Gantt (JSON).
     */
    public function ganttData(Request $request)
    {
        $query = Tarea::with(['departamento', 'creador', 'asignado', 'subtareas'])
                      ->principales();

        // Filtro por departamento
        if ($request->filled('departamento_id')) {
            $query->delDepartamento($request->departamento_id);
        }

        // Filtro por estado
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // Filtro por usuario asignado
        if ($request->filled('asignado_id')) {
            $query->where('asignado_id', $request->asignado_id);
        }

        // Filtro solo atrasadas
        if ($request->boolean('atrasadas')) {
            $query->atrasadas();
        }

        // Filtro rango de fechas
        if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
            $query->where(function ($q) use ($request) {
                $q->whereBetween('fecha_inicio', [$request->fecha_desde, $request->fecha_hasta])
                  ->orWhereBetween('fecha_fin', [$request->fecha_desde, $request->fecha_hasta]);
            });
        }

        $tareas = $query->orderBy('fecha_inicio')->get();

        $data = [];
        foreach ($tareas as $tarea) {
            $data[] = $this->formatTareaGantt($tarea);

            // Incluir subtareas
            foreach ($tarea->subtareas as $sub) {
                $data[] = $this->formatTareaGantt($sub, $tarea->id);
            }
        }

        return response()->json($data);
    }

    /**
     * API — Listado para DataTable.
     */
    public function list(Request $request)
    {
        $query = Tarea::with(['departamento', 'creador', 'asignado']);

        if ($request->filled('departamento_id')) {
            $query->delDepartamento($request->departamento_id);
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // Búsqueda
        if ($request->has('search') && $request->search['value']) {
            $search = $request->search['value'];
            $query->where(function ($q) use ($search) {
                $q->where('titulo', 'like', "%{$search}%")
                  ->orWhereHas('departamento', fn($d) => $d->where('nombre', 'like', "%{$search}%"))
                  ->orWhereHas('asignado', fn($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        $totalRecords = Tarea::count();
        $filteredRecords = $query->count();

        $columns = ['id', 'titulo', 'departamento_id', 'estado', 'prioridad', 'progreso', 'fecha_inicio', 'fecha_fin', 'created_at'];
        $orderColumn = $columns[$request->input('order.0.column', 0)] ?? 'id';
        $orderDir = $request->input('order.0.dir', 'desc');

        $start = $request->input('start', 0);
        $length = $request->input('length', 10);

        $tareas = $query->orderBy($orderColumn, $orderDir)
                        ->skip($start)
                        ->take($length)
                        ->get()
                        ->map(function ($tarea) {
                            return [
                                'id' => $tarea->id,
                                'titulo' => $tarea->titulo,
                                'departamento' => $tarea->departamento->nombre ?? '-',
                                'depto_color' => $tarea->departamento->color ?? '#405189',
                                'asignado' => $tarea->asignado->name ?? 'Sin asignar',
                                'estado' => $tarea->estado,
                                'badge_estado' => $tarea->badge_estado,
                                'prioridad' => $tarea->prioridad,
                                'progreso' => $tarea->progreso,
                                'fecha_inicio' => $tarea->fecha_inicio->format('d/m/Y'),
                                'fecha_fin' => $tarea->fecha_fin->format('d/m/Y'),
                                'atrasada' => $tarea->atrasada,
                                'dias_atraso' => $tarea->dias_atraso,
                            ];
                        });

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $tareas,
        ]);
    }

    /**
     * Guardar nueva tarea.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'departamento_id' => 'required|exists:departamentos_crm,id',
            'asignado_id' => 'nullable|exists:users,id',
            'tarea_padre_id' => 'nullable|exists:tareas,id',
            'prioridad' => 'required|in:baja,media,alta,critica',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['estado'] = 'pendiente';
        $validated['progreso'] = 0;

        $tarea = Tarea::create($validated);

        // Registrar en historial
        TareaComentario::create([
            'tarea_id' => $tarea->id,
            'user_id' => auth()->id(),
            'comentario' => 'Tarea creada.',
            'tipo' => 'cambio_estado',
        ]);

        return response()->json(['success' => true, 'message' => 'Tarea creada exitosamente.', 'tarea' => $tarea]);
    }

    /**
     * Detalle de una tarea (JSON).
     */
    public function show(Tarea $tarea)
    {
        $tarea->load(['departamento', 'creador', 'asignado', 'subtareas.asignado', 'comentarios.usuario']);

        return response()->json([
            'tarea' => $tarea,
            'atrasada' => $tarea->atrasada,
            'dias_atraso' => $tarea->dias_atraso,
        ]);
    }

    /**
     * Actualizar tarea.
     */
    public function update(Request $request, Tarea $tarea)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'departamento_id' => 'required|exists:departamentos_crm,id',
            'asignado_id' => 'nullable|exists:users,id',
            'prioridad' => 'required|in:baja,media,alta,critica',
            'estado' => 'required|in:pendiente,en_progreso,completada,cancelada',
            'progreso' => 'required|integer|min:0|max:100',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $estadoAnterior = $tarea->estado;
        $progresoAnterior = $tarea->progreso;

        // Si se marca como completada, registrar fecha
        if ($validated['estado'] === 'completada' && $estadoAnterior !== 'completada') {
            $validated['fecha_completada'] = Carbon::today();
            $validated['progreso'] = 100;
        }

        // Si se cambia de completada a otro estado, limpiar fecha
        if ($estadoAnterior === 'completada' && $validated['estado'] !== 'completada') {
            $validated['fecha_completada'] = null;
        }

        $tarea->update($validated);

        // Registrar cambios en historial
        if ($estadoAnterior !== $validated['estado']) {
            TareaComentario::create([
                'tarea_id' => $tarea->id,
                'user_id' => auth()->id(),
                'comentario' => "Estado cambiado de \"{$estadoAnterior}\" a \"{$validated['estado']}\".",
                'tipo' => 'cambio_estado',
            ]);
        }

        if ($progresoAnterior !== $validated['progreso']) {
            TareaComentario::create([
                'tarea_id' => $tarea->id,
                'user_id' => auth()->id(),
                'comentario' => "Progreso actualizado de {$progresoAnterior}% a {$validated['progreso']}%.",
                'tipo' => 'cambio_progreso',
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Tarea actualizada exitosamente.']);
    }

    /**
     * Eliminar tarea.
     */
    public function destroy(Tarea $tarea)
    {
        $tarea->delete();
        return response()->json(['success' => true, 'message' => 'Tarea eliminada exitosamente.']);
    }

    /**
     * Agregar comentario a una tarea.
     */
    public function addComentario(Request $request, Tarea $tarea)
    {
        $validated = $request->validate([
            'comentario' => 'required|string|max:1000',
        ]);

        $comentario = TareaComentario::create([
            'tarea_id' => $tarea->id,
            'user_id' => auth()->id(),
            'comentario' => $validated['comentario'],
            'tipo' => 'comentario',
        ]);

        $comentario->load('usuario');

        return response()->json(['success' => true, 'comentario' => $comentario]);
    }

    /**
     * API — Estadísticas para dashboard.
     */
    public function stats(Request $request)
    {
        $query = Tarea::query();

        if ($request->filled('departamento_id')) {
            $query->delDepartamento($request->departamento_id);
        }

        $total = (clone $query)->count();
        $porEstado = [
            'pendientes' => (clone $query)->where('estado', 'pendiente')->count(),
            'en_progreso' => (clone $query)->where('estado', 'en_progreso')->count(),
            'completadas' => (clone $query)->where('estado', 'completada')->count(),
            'canceladas' => (clone $query)->where('estado', 'cancelada')->count(),
        ];
        $atrasadas = (clone $query)->atrasadas()->count();

        // Tareas por departamento
        $porDepartamento = DepartamentoCrm::activos()
            ->withCount('tareas')
            ->orderByDesc('tareas_count')
            ->get()
            ->map(fn($d) => ['nombre' => $d->nombre, 'color' => $d->color, 'total' => $d->tareas_count]);

        return response()->json([
            'total' => $total,
            'por_estado' => $porEstado,
            'atrasadas' => $atrasadas,
            'por_departamento' => $porDepartamento,
        ]);
    }

    /* ───────────── CRUD Departamentos ───────────── */

    public function departamentos()
    {
        return response()->json(DepartamentoCrm::activos()->orderBy('nombre')->get());
    }

    public function usuarios()
    {
        return response()->json(
            User::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
        );
    }

    public function storeDepartamento(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:departamentos_crm,nombre',
            'descripcion' => 'nullable|string|max:255',
            'color' => 'required|string|max:7',
        ]);

        $depto = DepartamentoCrm::create($validated);
        return response()->json(['success' => true, 'departamento' => $depto]);
    }

    public function updateDepartamento(Request $request, DepartamentoCrm $departamento)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:departamentos_crm,nombre,' . $departamento->id,
            'descripcion' => 'nullable|string|max:255',
            'color' => 'required|string|max:7',
        ]);

        $departamento->update($validated);
        return response()->json(['success' => true, 'departamento' => $departamento]);
    }

    public function destroyDepartamento(DepartamentoCrm $departamento)
    {
        if ($departamento->tareas()->count() > 0) {
            return response()->json(['success' => false, 'message' => 'No se puede eliminar un departamento con tareas asignadas.'], 422);
        }
        $departamento->delete();
        return response()->json(['success' => true, 'message' => 'Departamento eliminado.']);
    }

    /* ───────────── Helpers ───────────── */

    private function formatTareaGantt(Tarea $tarea, $parentId = null): array
    {
        $color = $tarea->atrasada ? '#f06548' : $tarea->color_prioridad;

        return [
            'id' => $tarea->id,
            'text' => $tarea->titulo,
            'start_date' => $tarea->fecha_inicio->format('Y-m-d'),
            'end_date' => $tarea->fecha_fin->addDay()->format('Y-m-d'), // Gantt usa end exclusive
            'progress' => $tarea->progreso / 100,
            'parent' => $parentId,
            'color' => $color,
            'estado' => $tarea->estado,
            'prioridad' => $tarea->prioridad,
            'departamento' => $tarea->departamento->nombre ?? '',
            'depto_color' => $tarea->departamento->color ?? '#405189',
            'asignado' => $tarea->asignado->name ?? 'Sin asignar',
            'atrasada' => $tarea->atrasada,
            'dias_atraso' => $tarea->dias_atraso,
        ];
    }
}
