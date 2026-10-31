<?php

namespace App\Http\Controllers;

use App\Http\Requests\AsignarCuentaCentroCostoRequest;
use App\Http\Requests\BuscarCuentaCentroCostoRequest;
use App\Models\CentroCostoCuentaRuta;
use App\Models\CentroDeCosto;
use App\Models\CuentaContable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class CentroCostoCuentaController extends Controller
{
    public function index(BuscarCuentaCentroCostoRequest $request, CentroDeCosto $centro): JsonResponse
    {
        $empresa = substr(trim((string) $centro->company_id), 0, 3);
        abort_unless(in_array($empresa, CentroDeCosto::EMPRESAS_VALIDAS, true), 422, 'La empresa del centro de costo no es valida.');
        $busqueda = trim((string) $request->validated('buscar', ''));

        $cuentas = CuentaContable::query()
            ->where('company_id', $empresa)
            ->when($busqueda !== '', function (Builder $query) use ($busqueda): void {
                $query->where(function (Builder $query) use ($busqueda): void {
                    $query->where('cuenta', 'like', '%'.$busqueda.'%')
                        ->orWhere('descripcion', 'like', '%'.$busqueda.'%');
                });
            })
            ->orderBy('cuenta')
            ->limit(50)
            ->get(['id', 'cuenta', 'descripcion']);

        return response()->json($cuentas);
    }

    public function update(AsignarCuentaCentroCostoRequest $request, CentroDeCosto $centro): JsonResponse
    {
        $empresa = substr(trim((string) $centro->company_id), 0, 3);
        $ruta = CentroCostoCuentaRuta::claveRuta($centro->id_grupo);
        abort_unless($ruta !== '' && in_array($empresa, CentroDeCosto::EMPRESAS_VALIDAS, true), 422, 'El centro debe tener una ruta y empresa validas.');
        $asociacion = CentroCostoCuentaRuta::query()->updateOrCreate(
            ['company_id' => $empresa, 'ruta_key' => $ruta],
            $request->validated(),
        );
        $asociacion->load('cuentaRuta');

        return response()->json([
            'message' => 'Cuenta guardada para toda la ruta.',
            'EmpresaCodigo' => $empresa,
            'RutaClave' => $ruta,
            'id' => $centro->id,
            'CuentaRutaId' => $asociacion->cuenta_ruta_id,
            'CuentaRuta' => $asociacion->cuentaRuta?->cuenta ?? '',
            'CuentaRutaDescripcion' => $asociacion->cuentaRuta?->descripcion ?? '',
        ]);
    }
}
