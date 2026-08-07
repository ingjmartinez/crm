<?php

namespace App\Http\Controllers;

use App\Http\Requests\Operaciones\GuardarBancoOperacionRequest;
use App\Models\BancoOperacion;
use App\Models\MovimientoRutaV2Deposito;
use App\Models\OperacionDepositoRuta;
use App\Models\ReporteDiarioRuta;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BancoOperacionController extends Controller
{
    public function index(): View
    {
        $bancos = BancoOperacion::query()
            ->orderBy('nombre')
            ->get()
            ->map(fn (BancoOperacion $banco): array => $this->presentarBanco($banco));

        return view('operaciones.bancos.index', [
            'bancos' => $bancos,
        ]);
    }

    public function store(GuardarBancoOperacionRequest $request): RedirectResponse
    {
        BancoOperacion::query()->create($request->validated());

        return redirect()->route('operaciones.bancos.index')
            ->with('success', 'Banco agregado correctamente.');
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
            'usos' => $this->contarUsos($banco->nombre),
        ];
    }

    private function contarUsos(string $nombre): int
    {
        return MovimientoRutaV2Deposito::query()->where('banco', $nombre)->count()
            + ReporteDiarioRuta::query()->where('banco_nombre', $nombre)->count()
            + OperacionDepositoRuta::query()->where('banco', $nombre)->count();
    }
}
