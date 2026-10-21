<?php

namespace App\Http\Controllers\Bi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bi\ConsultarControlHorarioRequest;
use App\Http\Requests\Bi\GuardarHorarioRequest;
use App\Http\Requests\Bi\IndexAgenciaTerminalRequest;
use App\Http\Requests\Bi\RecalcularVentasRequest;
use App\Models\BiHorario;
use App\Models\BiLimiteProducto;
use App\Models\BiVentaHora;
use App\Models\BiVentaSnapshot;
use App\Models\CatalogoJuego;
use App\Models\VentaOnlinePromedioHistorico;
use App\Services\Bi\AgenciaTerminalCatalogo;
use App\Services\Bi\AlertasProductos;
use App\Services\Bi\ControlHorario;
use App\Services\Bi\IndicadoresBi;
use App\Services\Bi\ResumenZonaBi;
use App\Services\Bi\ResumirPremiosDia;
use App\Services\Bi\ResumirVentasDia;
use App\Services\Bi\VentasBetPorCategoria;
use App\Services\Bi\VentasPremiosPeriodo;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function guardarHorario(GuardarHorarioRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['cedula'] = in_array($data['tipo'], ['empleado', 'empleado_terminal'], true) ? $data['cedula'] : null;
        $data['terminal'] = in_array($data['tipo'], ['terminal', 'empleado_terminal'], true)
            ? (ltrim($data['terminal'], '0') ?: '0') : null;
        $data['clave'] = $data['tipo'].':'.($data['cedula'] ?? '').':'.($data['terminal'] ?? '');
        BiHorario::query()->updateOrCreate(['clave' => $data['clave']], $data);

        return back()->with('biHorarioMensaje', 'Horario guardado.');
    }

    public function eliminarHorario(int $biHorario): RedirectResponse
    {
        BiHorario::query()->findOrFail($biHorario)->delete();

        return back()->with('biHorarioMensaje', 'Horario eliminado.');
    }

    public function controlHorario(ConsultarControlHorarioRequest $request, ControlHorario $controlHorario): JsonResponse
    {
        return response()->json($controlHorario->consultar($request->validated()));
    }

    public function index(IndexAgenciaTerminalRequest $request, AgenciaTerminalCatalogo $catalogo, VentasBetPorCategoria $ventasBet, VentasPremiosPeriodo $ventasPremios, IndicadoresBi $indicadores, AlertasProductos $alertasProductos, ResumenZonaBi $resumenZona): Response
    {
        $busqueda = trim((string) $request->validated('q', ''));
        $zona = trim((string) $request->validated('zona', ''));
        $empresa = trim((string) $request->validated('empresa', ''));
        $ruta = trim((string) $request->validated('ruta', ''));
        $rangoDisponible = $ventasBet->rangoDisponible();
        $hasta = CarbonImmutable::parse($request->validated('hasta') ?: ($rangoDisponible['hasta'] ?? null) ?: today()->toDateString());
        $desde = CarbonImmutable::parse($request->validated('desde') ?: $hasta->subDays(4)->toDateString());
        $lecturas = BiVentaHora::query()
            ->where('fecha', today()->toDateString())
            ->whereBetween('hora', [6, 22])
            ->orderBy('hora')
            ->get(['hora', 'tradicional_acumulado', 'no_tradicional_acumulado', 'externas_acumulado', 'recargas_acumulado', 'otros_acumulado', 'quiniela_loteka_acumulado', 'mega_chance_acumulado', 'capturado_en', 'terminales_evaluadas', 'terminales_con_venta', 'terminales_categoria', 'productos', 'productos_terminales']);
        $snapshot = $lecturas->isNotEmpty()
            ? BiVentaSnapshot::query()->where('fecha', today()->toDateString())->first(['hora', 'rutas', 'productos_terminales'])
            : null;
        $diaSemanaAnterior = CarbonImmutable::today()->subDays(7);
        $lecturasSemanaAnterior = BiVentaHora::query()
            ->where('fecha', $diaSemanaAnterior->toDateString())
            ->whereBetween('hora', [6, 22])
            ->orderBy('hora')
            ->get(['hora', 'tradicional_acumulado', 'no_tradicional_acumulado', 'externas_acumulado', 'recargas_acumulado', 'otros_acumulado', 'productos_terminales', 'capturado_en']);
        $datosZona = $zona !== '' || $empresa !== '' || $ruta !== '' ? $resumenZona->resumir($zona, $empresa, $ruta, $desde, $hasta, $lecturas) : null;

        return Inertia::render('Bi/Dashboard', [
            'modulo' => 'BI',
            'limitesProductosUrl' => route('bi.limites-productos.guardar'),
            'mensajeLimite' => session('biLimiteMensaje'),
            'productosDisponibles' => fn () => CatalogoJuego::query()->orderBy('descripcion')->get(['producto_id', 'descripcion']),
            'gruposDisponibles' => collect(BiLimiteProducto::GRUPOS)->map(fn (string $nombre, string $id): array => [
                'producto_id' => $id,
                'descripcion' => $nombre,
            ])->values()->all(),
            'terminalesLimites' => fn () => $catalogo->paraAlertas($zona, $empresa, $ruta),
            'alertasProductos' => fn () => $alertasProductos->resumir($lecturas->last(), $snapshot, $datosZona['terminales'] ?? null),
            'menuUrl' => route('dashboard.index'),
            'biUrl' => route('bi.index', [], false),
            'controlHorarioUrl' => route('bi.control-horario', [], false),
            'guardarHorarioUrl' => route('bi.horarios.guardar', [], false),
            'eliminarHorarioUrl' => route('bi.horarios.eliminar', ['biHorario' => '__ID__'], false),
            'horarios' => fn () => BiHorario::query()->orderBy('tipo')->orderBy('clave')->get(['id', 'tipo', 'cedula', 'terminal', 'entrada', 'salida']),
            'mensajeHorario' => session('biHorarioMensaje'),
            'recalcularUrl' => route('bi.recalcular'),
            'mensajeRecalculo' => session('biRecalculo'),
            'terminales' => fn () => $catalogo->paginar($busqueda, $zona, $empresa, $ruta),
            'zonas' => fn () => $catalogo->zonas($empresa),
            'zonaSeleccionada' => $zona,
            'empresas' => fn () => $catalogo->empresas(),
            'empresaSeleccionada' => $empresa,
            'rutas' => fn () => $catalogo->rutas($empresa, $zona),
            'rutaSeleccionada' => $ruta,
            'busquedaTerminal' => $busqueda,
            'catalogoActualizado' => fn () => $catalogo->ultimaActualizacion(),
            'periodoVentas' => [
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
                'primeraFecha' => $rangoDisponible['desde'],
                'ultimaFecha' => $rangoDisponible['hasta'],
            ],
            'ventasPorCategoria' => fn () => $datosZona['ventasPorCategoria'] ?? $ventasBet->resumir($desde, $hasta),
            'ventasPremios' => fn () => $datosZona['ventasPremios'] ?? $ventasPremios->resumir($desde, $hasta),
            'indicadores' => fn () => $datosZona['indicadores'] ?? $indicadores->resumir($desde, $hasta, $lecturas->last()),
            'fechaLecturas' => today()->toDateString(),
            'lecturasPorHora' => $datosZona['lecturasPorHora'] ?? $lecturas->map(fn (BiVentaHora $lectura): array => [
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
            'fechaLecturasSemanaAnterior' => $diaSemanaAnterior->toDateString(),
            'lecturasSemanaAnterior' => $datosZona !== null ? $resumenZona->filtrarLecturas($lecturasSemanaAnterior, $datosZona['terminales'])
                ->map(fn (array $hora): array => ['hora' => $hora['hora'], 'total' => $hora['total']])->all()
                : $lecturasSemanaAnterior->map(fn (BiVentaHora $lectura): array => [
                    'hora' => $lectura->hora,
                    'total' => (float) $lectura->tradicional_acumulado
                        + (float) $lectura->no_tradicional_acumulado
                        + (float) $lectura->externas_acumulado
                        + (float) $lectura->recargas_acumulado
                        + (float) $lectura->otros_acumulado,
                ])->all(),
            'rutasConMasVenta' => $datosZona['rutasConMasVenta'] ?? collect($snapshot?->rutas ?? [])->take(10)->values()->all(),
            'quinielaLotekaHoy' => $datosZona !== null ? $datosZona['quinielaLotekaHoy'] : ($lecturas->last()?->quiniela_loteka_acumulado !== null
                ? (float) $lecturas->last()->quiniela_loteka_acumulado
                : null),
            'quinielaLotekaSemanaAnterior' => fn (): ?float => $datosZona !== null ? $datosZona['quinielaLotekaSemanaAnterior'] : $this->ventaProductoSemanaAnterior('43', $diaSemanaAnterior),
            'quinielaLotekaRecordAnterior' => fn (): ?float => $datosZona !== null ? null : $this->recordProductoAnterior('43', CarbonImmutable::today()),
            'megaChanceHoy' => $datosZona !== null ? $datosZona['megaChanceHoy'] : ($lecturas->last()?->mega_chance_acumulado !== null
                ? (float) $lecturas->last()->mega_chance_acumulado
                : null),
            'megaChanceSemanaAnterior' => fn (): ?float => $datosZona !== null ? $datosZona['megaChanceSemanaAnterior'] : $this->ventaProductoSemanaAnterior('38', $diaSemanaAnterior),
            'megaChanceRecordAnterior' => fn (): ?float => $datosZona !== null ? null : $this->recordProductoAnterior('38', CarbonImmutable::today()),
            'promediosHistoricos' => fn () => $datosZona !== null ? [] : VentaOnlinePromedioHistorico::query()
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
