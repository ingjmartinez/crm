<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarSelectorAgenciaRequest;
use App\Http\Requests\GuardarSelectorAgenciaSeleccionRequest;
use App\Models\RentabilidadAgenciaV5;
use App\Models\SelectorAgenciaSeleccion;
use App\Services\RentabilidadAgenciaV10Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SelectorAgenciaController extends Controller
{
    public function __construct(private readonly RentabilidadAgenciaV10Service $reporte) {}

    public function index(ConsultarSelectorAgenciaRequest $request): View
    {
        $seleccionGuardada = SelectorAgenciaSeleccion::query()->where('user_id', $request->user()->getAuthIdentifier())->first()?->terminales;
        $filtros = ['anio' => now()->year, 'mes_inicio' => 1, 'mes_fin' => now()->month, 'empresa' => '', 'ruta' => '', ...$request->validated()];
        foreach (['empresa', 'ruta'] as $campo) {
            $filtros[$campo] = trim((string) $filtros[$campo]);
        }
        $resultado = $request->filled('anio') ? $this->reporte->reporte([
            ...$filtros, 'alcance' => 'todas', 'terminal' => '',
            'productos_filtrados' => 1, 'productos' => ['Tradicional'],
            'premios_filtrados' => 1, 'premios_productos' => ['Tradicional'],
        ]) : null;
        $meses = [];
        if ($resultado !== null) {
            for ($fecha = $resultado['desde']->copy(); $fecha->lte($resultado['hasta']); $fecha->addMonth()) {
                $meses[] = ['codigo' => $fecha->format('Y-m'), 'nombre' => $fecha->copy()->locale('es')->isoFormat('MMMM YYYY')];
            }
        }
        $datos = $resultado === null ? [] : $resultado['filas']
            ->filter(fn (array $fila): bool => collect($fila['periodos'])->contains(fn (array $periodo): bool => (float) $periodo['ventas_tradicionales'] !== 0.0))
            ->map(fn (array $fila): array => [
                'terminal' => $fila['terminal'], 'nombre' => $fila['nombre'], 'empresa' => $fila['empresa'], 'ruta' => $fila['ruta'],
                'periodos' => array_map(fn (array $periodo): array => [
                    'mes' => $periodo['mes'], 'ventas_tradicionales' => $periodo['ventas_tradicionales'], 'premios_tradicionales' => $periodo['premios_sacados'],
                ], $fila['periodos']),
            ])->values()->all();
        $rutasDisponibles = RentabilidadAgenciaV5::query()->activas()->whereNotNull('ruta')->whereRaw("TRIM(ruta) <> ''")
            ->selectRaw("COALESCE(TRIM(empresa), '') AS empresa, TRIM(ruta) AS ruta")->distinct()->orderBy('ruta')->get();
        $empresas = RentabilidadAgenciaV5::query()->activas()->whereNotNull('empresa')->whereRaw("TRIM(empresa) <> ''")
            ->selectRaw('TRIM(empresa) AS empresa')->distinct()->orderBy('empresa')->pluck('empresa');
        $rutas = $rutasDisponibles->when($filtros['empresa'] !== '', fn (Collection $filas): Collection => $filas->where('empresa', $filtros['empresa']))->pluck('ruta')->unique()->values();

        return view('mantenimiento.selector-agencia', compact('filtros', 'resultado', 'datos', 'meses', 'empresas', 'rutas', 'rutasDisponibles', 'seleccionGuardada'));
    }

    public function guardarSeleccion(GuardarSelectorAgenciaSeleccionRequest $request): JsonResponse
    {
        $terminales = collect($request->validated('terminales'))
            ->map(fn (array $terminal): array => ['empresa' => trim((string) $terminal['empresa']), 'terminal' => trim($terminal['terminal'])])
            ->unique(fn (array $terminal): string => json_encode($terminal, JSON_THROW_ON_ERROR))->values()->all();
        $seleccion = SelectorAgenciaSeleccion::query()->updateOrCreate(
            ['user_id' => $request->user()->getAuthIdentifier()],
            ['terminales' => $terminales],
        );

        return response()->json(['guardada' => true, 'terminales' => $seleccion->terminales]);
    }
}
