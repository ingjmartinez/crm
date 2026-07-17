<?php

namespace App\Http\Controllers;

use App\Models\RendimientoCoordinador;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RendimientoCoordinadorController extends Controller
{
    public function index(Request $request, RendimientoCoordinador $reporte)
    {
        $fechaInicioDefault = now()->startOfMonth()->toDateString();
        $fechaFinDefault = now()->toDateString();
        $filtrosAplicados = $request->boolean('aplicar');

        $validated = $request->validate([
            'aplicar' => ['nullable', 'boolean'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'sistema' => ['nullable', 'in:Todos,Lotobet,Lotonet'],
        ]);

        $filtros = [
            'fecha_inicio' => Carbon::parse($validated['fecha_inicio'] ?? $fechaInicioDefault)->toDateString(),
            'fecha_fin' => Carbon::parse($validated['fecha_fin'] ?? $fechaFinDefault)->toDateString(),
            'sistema' => $validated['sistema'] ?? 'Todos',
        ];

        $resultado = $filtrosAplicados
            ? $reporte->generar($filtros)
            : [
                'coordinadores' => collect(),
                'agencias_sin_coordinador' => collect(),
                'resumen' => [],
            ];

        return view('incentivos.rendimiento-coordinador', [
            'coordinadores' => $resultado['coordinadores'],
            'agenciasSinCoordinador' => $resultado['agencias_sin_coordinador'],
            'resumen' => $resultado['resumen'],
            'filtros' => $filtros,
            'filtrosAplicados' => $filtrosAplicados,
        ]);
    }
}
