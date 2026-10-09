<?php

namespace App\Http\Controllers;

use App\Http\Requests\Operaciones\ActualizarBancoOperacionRequest;
use App\Http\Requests\Operaciones\GuardarBancoOperacionRequest;
use App\Models\BancoOperacion;
use App\Models\MovimientoRutaV2Deposito;
use App\Models\OperacionDepositoRuta;
use App\Models\ReporteDiarioRuta;
use App\Services\Operaciones\CatalogoCuentasBancariasEmpresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BancoOperacionController extends Controller
{
    public function __construct(private readonly CatalogoCuentasBancariasEmpresa $catalogo) {}

    public function index(): View
    {
        $bancos = BancoOperacion::query()
            ->orderBy('empresa_id')
            ->orderBy('nombre')
            ->get()
            ->map(fn (BancoOperacion $banco): array => $this->presentarBanco($banco));

        return view('operaciones.bancos.index', [
            'bancos' => $bancos,
        ]);
    }

    public function cuentas(string $empresaId): JsonResponse
    {
        abort_unless(in_array($empresaId, ['168', '169'], true), 404);

        return response()->json(['cuentas' => $this->catalogo->listar($empresaId)]);
    }

    public function store(GuardarBancoOperacionRequest $request): RedirectResponse
    {
        BancoOperacion::query()->create($this->datosBanco($request->validated()));

        return redirect()->route('operaciones.bancos.index')
            ->with('success', 'Banco agregado correctamente.');
    }

    public function update(ActualizarBancoOperacionRequest $request, BancoOperacion $banco): RedirectResponse
    {
        $banco->update($this->datosBanco($request->validated(), $banco));

        return redirect()->route('operaciones.bancos.index')
            ->with('success', 'Cuenta contable del banco actualizada correctamente.');
    }

    /** @param array{empresa_id: string, cuenta_codigo: string} $datos @return array{empresa_id: string, cuenta_codigo: string, cuenta_descripcion: string, nombre: string} */
    private function datosBanco(array $datos, ?BancoOperacion $banco = null): array
    {
        $cuenta = $this->catalogo->buscar($datos['empresa_id'], $datos['cuenta_codigo']);
        if ($cuenta === null) {
            throw ValidationException::withMessages(['cuenta_codigo' => 'La cuenta no existe en el catálogo de la empresa seleccionada.']);
        }

        $duplicado = BancoOperacion::query()
            ->where('empresa_id', $datos['empresa_id'])
            ->where('cuenta_codigo', $datos['cuenta_codigo'])
            ->when($banco !== null, fn ($query) => $query->where('id', '<>', $banco->id))
            ->exists();
        if ($duplicado) {
            throw ValidationException::withMessages(['cuenta_codigo' => 'Esta cuenta bancaria ya está registrada para la empresa.']);
        }

        return [
            'empresa_id' => $datos['empresa_id'],
            'cuenta_codigo' => $cuenta['cuenta'],
            'cuenta_descripcion' => $cuenta['descripcion'],
            'nombre' => $banco?->nombre ?? $cuenta['descripcion'],
        ];
    }

    public function destroy(BancoOperacion $banco): RedirectResponse
    {
        $banco->delete();

        return redirect()->route('operaciones.bancos.index')
            ->with('success', 'Banco eliminado correctamente.');
    }

    /** @return array{modelo: BancoOperacion, nombre: string, usos: int} */
    private function presentarBanco(BancoOperacion $banco): array
    {
        return [
            'modelo' => $banco,
            'nombre' => $banco->nombre,
            'usos' => $this->contarUsos($banco),
        ];
    }

    private function contarUsos(BancoOperacion $banco): int
    {
        return MovimientoRutaV2Deposito::query()->where('banco', $banco->nombre)->where('empresa_id', $banco->empresa_id)->count()
            + ReporteDiarioRuta::query()->where('banco_nombre', $banco->nombre)->count()
            + OperacionDepositoRuta::query()->where('banco', $banco->nombre)->count();
    }
}
