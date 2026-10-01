<?php

namespace App\Http\Controllers\Bi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bi\IndexAgenciaTerminalRequest;
use App\Http\Requests\Bi\RecalcularVentasRequest;
use App\Models\BiVentaHora;
use App\Models\VentaOnlinePromedioHistorico;
use App\Services\Bi\AgenciaTerminalCatalogo;
use App\Services\Bi\IndicadoresBi;
use App\Services\Bi\ResumirPremiosDia;
use App\Services\Bi\ResumirVentasDia;
use App\Services\Bi\VentasBetPorCategoria;
use App\Services\Bi\VentasPremiosPeriodo;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(IndexAgenciaTerminalRequest $request, AgenciaTerminalCatalogo $catalogo, VentasBetPorCategoria $ventasBet, VentasPremiosPeriodo $ventasPremios, IndicadoresBi $indicadores): Response
    {
        $busqueda = trim((string) $request->validated('q', ''));
        $rangoDisponible = $ventasBet->rangoDisponible();
        $hasta = CarbonImmutable::parse($request->validated('hasta') ?: ($rangoDisponible['hasta'] ?? null) ?: today()->toDateString());
        $desde = CarbonImmutable::parse($request->validated('desde') ?: $hasta->subDays(4)->toDateString());
        $lecturas = BiVentaHora::query()
            ->whereDate('fecha', today()->toDateString())
            ->whereBetween('hora', [6, 22])
            ->orderBy('hora')
            ->get(['hora', 'tradicional_acumulado', 'no_tradicional_acumulado', 'externas_acumulado', 'recargas_acumulado', 'otros_acumulado', 'quiniela_loteka_acumulado', 'mega_chance_acumulado', 'capturado_en', 'rutas', 'terminales_evaluadas', 'terminales_con_venta', 'terminales_categoria']);
        $diaSemanaAnterior = CarbonImmutable::today()->subDays(7);

        return Inertia::render('Bi/Dashboard', [
            'modulo' => 'BI',
            'menuUrl' => route('dashboard.index'),
            'biUrl' => route('bi.index'),
            'recalcularUrl' => route('bi.recalcular'),
            'mensajeRecalculo' => session('biRecalculo'),
            'terminales' => fn () => $catalogo->paginar($busqueda),
            'busquedaTerminal' => $busqueda,
            'catalogoActualizado' => fn () => $catalogo->ultimaActualizacion(),
            'periodoVentas' => [
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
                'primeraFecha' => $rangoDisponible['desde'],
                'ultimaFecha' => $rangoDisponible['hasta'],
            ],
            'ventasPorCategoria' => fn () => $ventasBet->resumir($desde, $hasta),
            'ventasPremios' => fn () => $ventasPremios->resumir($desde, $hasta),
            'indicadores' => fn () => $indicadores->resumir($desde, $hasta, $lecturas->last()),
            'fechaLecturas' => today()->toDateString(),
            'lecturasPorHora' => $lecturas->map(fn (BiVentaHora $lectura): array => [
                'hora' => $lectura->hora,
                'tradicional' => (float) $lectura->tradicional_acumulado,
                'noTradicional' => (float) $lectura->no_tradicional_acumulado,
                'total' => (float) $lectura->tradicional_acumulado
                    + (float) $lectura->no_tradicional_acumulado
                    + (float) $lectura->externas_acumulado
                    + (float) $lectura->recargas_acumulado
                    + (float) $lectura->otros_acumulado,
                'capturadoEn' => $lectura->capturado_en->toDateTimeString(),
            ])
                ->all(),
            'rutasConMasVenta' => collect($lecturas->last()?->rutas ?? [])->take(10)->values()->all(),
            'quinielaLotekaHoy' => $lecturas->last()?->quiniela_loteka_acumulado !== null
                ? (float) $lecturas->last()->quiniela_loteka_acumulado
                : null,
            'quinielaLotekaSemanaAnterior' => fn (): ?float => $this->ventaProductoSemanaAnterior('43', $diaSemanaAnterior),
            'quinielaLotekaRecordAnterior' => fn (): ?float => $this->recordProductoAnterior('43', CarbonImmutable::today()),
            'megaChanceHoy' => $lecturas->last()?->mega_chance_acumulado !== null
                ? (float) $lecturas->last()->mega_chance_acumulado
                : null,
            'megaChanceSemanaAnterior' => fn (): ?float => $this->ventaProductoSemanaAnterior('38', $diaSemanaAnterior),
            'megaChanceRecordAnterior' => fn (): ?float => $this->recordProductoAnterior('38', CarbonImmutable::today()),
            'promediosHistoricos' => fn () => VentaOnlinePromedioHistorico::query()
                ->whereIn('tipo_categoria', ['tradicional', 'no_tradicional'])
                ->get(['tipo_categoria', 'monto_promedio', 'meses_incluidos'])
                ->mapWithKeys(fn (VentaOnlinePromedioHistorico $promedio): array => [
                    $promedio->tipo_categoria => [
                        'monto' => (float) $promedio->monto_promedio,
                        'meses' => $promedio->meses_incluidos,
                    ],
                ])->all(),
        ]);
    }

    public function recalcular(RecalcularVentasRequest $request, ResumirVentasDia $resumidorVentas, ResumirPremiosDia $resumidorPremios): RedirectResponse
    {
        $desde = CarbonImmutable::parse($request->validated('desde'));
        $hasta = CarbonImmutable::parse($request->validated('hasta'));
        $ultimoDiaCerrado = $hasta->min(CarbonImmutable::yesterday());
        $cantidad = 0;

        for ($dia = $desde; $dia->lessThanOrEqualTo($ultimoDiaCerrado); $dia = $dia->addDay()) {
            $resumidorVentas->resumir($dia);
            $resumidorPremios->resumir($dia);
            $cantidad++;
        }

        return redirect()->route('bi.index', [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
        ])->with('biRecalculo', "Se recalcularon {$cantidad} día(s) desde ventas y premios.");
    }

    private function ventaProductoSemanaAnterior(string $productoId, CarbonImmutable $dia): ?float
    {
        $venta = DB::table('bi_producto_dias')
            ->where('producto_id', $productoId)
            ->where('fecha', $dia->toDateString())
            ->select(['monto', 'registros'])
            ->first();

        return (int) ($venta?->registros ?? 0) > 0 ? (float) $venta->monto : null;
    }

    private function recordProductoAnterior(string $productoId, CarbonImmutable $hoy): ?float
    {
        $record = DB::table('bi_producto_dias')
            ->where('producto_id', $productoId)
            ->where('fecha', '<', $hoy->toDateString())
            ->where('registros', '>', 0)
            ->orderByDesc('monto')
            ->value('monto');

        return $record !== null ? (float) $record : null;
    }
}
