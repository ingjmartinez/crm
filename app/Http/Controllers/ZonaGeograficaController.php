<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarZonaGeograficaRequest;
use App\Models\ZonaGeografica;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ZonaGeograficaController extends Controller
{
    public function index(Request $request): View
    {
        $busqueda = trim($request->string('buscar')->toString());
        $zonas = ZonaGeografica::query()
            ->when($busqueda !== '', function ($query) use ($busqueda): void {
                $query->where(function ($query) use ($busqueda): void {
                    foreach (['region', 'provincia', 'municipio', 'ciudad_seccion', 'sector_barrio_paraje'] as $campo) {
                        $query->orWhere($campo, 'like', "%{$busqueda}%");
                    }
                });
            })
            ->orderBy('region')
            ->orderBy('provincia')
            ->orderBy('municipio')
            ->orderBy('ciudad_seccion')
            ->orderBy('sector_barrio_paraje')
            ->paginate(50)
            ->withQueryString();

        return view('mantenimiento.zonas-geograficas.index', [
            'zonas' => $zonas,
            'regiones' => ZonaGeografica::query()->distinct()->orderBy('region')->pluck('region'),
        ]);
    }

    public function store(GuardarZonaGeograficaRequest $request): RedirectResponse
    {
        ZonaGeografica::query()->create($request->validated());

        return to_route('mantenimiento.zonas-geograficas.index')
            ->with('success', 'Zona geográfica registrada correctamente.');
    }

    public function update(GuardarZonaGeograficaRequest $request, ZonaGeografica $zonaGeografica): RedirectResponse
    {
        $zonaGeografica->update($request->validated());

        return to_route('mantenimiento.zonas-geograficas.index', $request->only(['buscar', 'page']))
            ->with('success', 'Zona geográfica actualizada correctamente.');
    }

    public function destroy(ZonaGeografica $zonaGeografica): RedirectResponse
    {
        $zonaGeografica->delete();

        return to_route('mantenimiento.zonas-geograficas.index')
            ->with('success', 'Zona geográfica eliminada correctamente.');
    }

    public function opciones(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nivel' => ['required', 'in:provincia,municipio,ciudad_seccion,sector_barrio_paraje'],
            'region' => ['nullable', 'string', 'max:80'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'municipio' => ['nullable', 'string', 'max:120'],
            'ciudad_seccion' => ['nullable', 'string', 'max:160'],
        ]);
        $nivel = $validated['nivel'];
        $query = ZonaGeografica::query();

        foreach (['region', 'provincia', 'municipio', 'ciudad_seccion'] as $campo) {
            if (! empty($validated[$campo])) {
                $query->where($campo, $validated[$campo]);
            }
        }

        return response()->json($query->distinct()->orderBy($nivel)->pluck($nivel));
    }
}
