<?php

namespace App\Http\Controllers;

use App\Http\Requests\CargarTipoPagoTerminalRequest;
use App\Http\Requests\ListarTipoPagoTerminalRequest;
use App\Models\TipoPagoTerminalDia;
use App\Services\Mantenimiento\ImportarTipoPagoTerminalCsv;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class MantenimientoTipoPagoController extends Controller
{
    public function index(ListarTipoPagoTerminalRequest $request): View
    {
        $validated = $request->validated();
        $ultimaFecha = TipoPagoTerminalDia::query()->max('fecha');
        $fechaConsulta = $validated['fecha'] ?? null;

        return view('mantenimiento.tipo-pago.index', [
            'fechaConsulta' => $fechaConsulta,
            'mesConsulta' => $validated['mes'] ?? substr($fechaConsulta ?: ($ultimaFecha ?: today()->toDateString()), 0, 7),
            'ultimaFecha' => $ultimaFecha,
        ]);
    }

    public function data(ListarTipoPagoTerminalRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $fecha = $validated['fecha'] ?? null;
        $mes = $validated['mes'] ?? substr($fecha ?: today()->toDateString(), 0, 7);
        $inicio = $fecha ?: CarbonImmutable::createFromFormat('Y-m', $mes)->startOfMonth()->toDateString();
        $fin = $fecha ?: CarbonImmutable::createFromFormat('Y-m', $mes)->endOfMonth()->toDateString();
        $baseQuery = TipoPagoTerminalDia::query()
            ->where('fecha', '>=', $inicio)
            ->where('fecha', '<', CarbonImmutable::parse($fin)->addDay()->toDateString());
        $fechas = (clone $baseQuery)->distinct()->orderBy('fecha')->pluck('fecha')
            ->map(fn ($dia): string => CarbonImmutable::parse($dia)->toDateString())
            ->all();
        if ($fecha !== null && $fechas === []) {
            $fechas = [$fecha];
        }

        $query = (clone $baseQuery)->select('terminal')->distinct();
        $total = (clone $query)->count('terminal');
        $busqueda = trim((string) data_get($validated, 'search.value', ''));

        if ($busqueda !== '') {
            $query->where('terminal', 'like', "%{$busqueda}%");
        }

        $filtrados = (clone $query)->count('terminal');
        $terminales = $query
            ->orderBy('terminal')
            ->offset((int) ($validated['start'] ?? 0))
            ->limit((int) ($validated['length'] ?? 25))
            ->pluck('terminal');
        $pagos = $terminales->isEmpty() ? collect() : (clone $baseQuery)
            ->whereIn('terminal', $terminales)
            ->get(['terminal', 'fecha', 'tipo_pago'])
            ->groupBy('terminal');

        return response()->json([
            'draw' => (int) ($validated['draw'] ?? 0),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtrados,
            'fechas' => $fechas,
            'data' => $terminales->map(fn (string $terminal): array => [
                'terminal' => $terminal,
                'pagos' => $pagos->get($terminal, collect())
                    ->mapWithKeys(fn (TipoPagoTerminalDia $row): array => [$row->fecha->toDateString() => $row->tipo_pago])
                    ->all(),
            ])->all(),
        ]);
    }

    public function store(CargarTipoPagoTerminalRequest $request, ImportarTipoPagoTerminalCsv $importador): RedirectResponse
    {
        $validated = $request->validated();
        $resultado = $importador->importar($validated['archivo'], $validated['fecha'], $request->user()?->id);

        return to_route('mantenimiento.tipo-pago.index', ['fecha' => $validated['fecha']])
            ->with('success', "Se cargaron {$resultado['cargadas']} terminales para {$validated['fecha']}. Filas sin terminal omitidas: {$resultado['omitidas']}.");
    }
}
