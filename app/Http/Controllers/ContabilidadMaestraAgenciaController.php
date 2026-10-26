<?php

namespace App\Http\Controllers;

use App\Models\CentroDeCosto;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContabilidadMaestraAgenciaController extends Controller
{
    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('buscar', ''));

        $centrosDeCosto = CentroDeCosto::query()
            ->select(['id', 'company_id', 'id_centro_costo', 'id_viejo', 'descripcion', 'id_grupo', 'id_sub_grupo', 'id_division'])
            ->when($busqueda !== '', function ($query) use ($busqueda): void {
                $query->where(function ($query) use ($busqueda): void {
                    $query->where('id_centro_costo', 'like', "%{$busqueda}%")
                        ->orWhere('id_viejo', 'like', "%{$busqueda}%")
                        ->orWhere('descripcion', 'like', "%{$busqueda}%")
                        ->orWhere('id_grupo', 'like', "%{$busqueda}%")
                        ->orWhere('id_sub_grupo', 'like', "%{$busqueda}%")
                        ->orWhere('id_division', 'like', "%{$busqueda}%");
                });
            })
            ->orderBy('id_centro_costo')
            ->orderBy('company_id')
            ->paginate(50)
            ->withQueryString();

        return view('contabilidad.maestra-agencia', compact('centrosDeCosto', 'busqueda'));
    }
}
