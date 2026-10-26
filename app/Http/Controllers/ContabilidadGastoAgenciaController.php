<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReporteGastoAgenciaRequest;
use App\Models\CentroDeCosto;
use App\Models\ClasificacionCuenta;
use App\Models\EntradaDiario;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ContabilidadGastoAgenciaController extends Controller
{
    public function index(ReporteGastoAgenciaRequest $request): View
    {
        $datos = $request->validated();
        $fechaInicio = $datos['fecha_inicio'] ?? Carbon::today()->startOfMonth()->toDateString();
        $fechaFin = $datos['fecha_fin'] ?? Carbon::today()->toDateString();
        $empresa = $datos['empresa'] ?? '';
        $busqueda = trim($datos['buscar'] ?? '');

        $cuentas = ClasificacionCuenta::cuentasActivas();

        $agencias = CentroDeCosto::query()
            ->select(['id', 'company_id', 'id_centro_costo', 'id_viejo', 'descripcion'])
            ->when($empresa !== '', fn ($query) => $query->empresa($empresa))
            ->when($busqueda !== '', function ($query) use ($busqueda): void {
                $query->where(function ($query) use ($busqueda): void {
                    $query->where('id_centro_costo', 'like', "%{$busqueda}%")
                        ->orWhere('id_viejo', 'like', "%{$busqueda}%")
                        ->orWhere('descripcion', 'like', "%{$busqueda}%");
                });
            })
            ->orderBy('id_centro_costo')
            ->orderBy('company_id')
            ->paginate(50)
            ->withQueryString();

        $montos = collect();
        if ($cuentas->isNotEmpty() && $agencias->isNotEmpty()) {
            $montos = EntradaDiario::query()
                ->whereBetween('fecha', [$fechaInicio, $fechaFin])
                ->whereIn('cuenta', $cuentas->all())
                ->whereIn('company_id', $agencias->pluck('company_id')->unique()->all())
                ->whereIn('id_centro_costo', $agencias->pluck('id_centro_costo')->map(fn ($id): string => (string) $id)->unique()->all())
                ->selectRaw('company_id, id_centro_costo, SUM(COALESCE(debito, 0) - COALESCE(credito, 0)) AS gasto')
                ->groupBy('company_id', 'id_centro_costo')
                ->get()
                ->keyBy(fn (EntradaDiario $movimiento): string => $movimiento->company_id.'|'.$movimiento->id_centro_costo);
        }

        return view('contabilidad.gasto-agencia', compact('agencias', 'montos', 'fechaInicio', 'fechaFin', 'empresa', 'busqueda'));
    }
}
