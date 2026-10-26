<?php

namespace App\Http\Controllers\Gerencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\BuscarRentabilidadAgenciaRequest;
use App\Http\Requests\ConsultarRentabilidadAgenciaRequest;
use App\Http\Requests\GuardarRentabilidadAgenciaR20Request;
use App\Models\CentroDeCosto;
use App\Models\ClasificacionCuenta;
use App\Models\EntradaDiario;
use App\Models\RentabilidadAgencia;
use App\Models\RentabilidadAgenciaR20;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RentabilidadAgenciaController extends Controller
{
    private const AGENCIAS_CARGA_INICIAL = 200;

    private const AGENCIAS_BUSQUEDA_LIMITE = 100;

    private const CUENTAS_INGRESO = ['400110001', '400120001', '400130001', '400130002', '400130003'];

    private const TERMINAL_R20_GLOBAL = '*';

    private const MES_R20_GLOBAL = 'GLOBAL';

    public function index(ConsultarRentabilidadAgenciaRequest $request): View
    {
        $consultaRealizada = $request->filled('mes');
        $mesSeleccionado = (string) ($request->validated('mes') ?: now()->format('Y-m'));
        $empresaSeleccionada = trim((string) ($request->validated('empresa') ?? ''));
        $ciudadSeleccionada = trim((string) ($request->validated('ciudad') ?? ''));
        $rutaSeleccionada = trim((string) ($request->validated('ruta') ?? ''));
        $usarIngresosContables = (bool) ($request->validated('ingresos_contables') ?? false);
        $montoR20Global = $this->montoR20Global();
        $resumenCumplimiento = ['cumple' => 0, 'no_cumple' => 0];
        $resumenCiudades = ['cumple' => 0, 'no_cumple' => 0];
        $resumenRutas = ['cumple' => 0, 'no_cumple' => 0];
        $agenciasDataTable = collect();
        $agenciasDetalleGrupos = ['ciudad' => [], 'ruta' => []];
        $ciudadesResumen = collect();
        $rutasResumen = collect();
        $empresas = RentabilidadAgencia::query()
            ->activas()
            ->whereNotNull('empresa')
            ->whereRaw("TRIM(empresa) <> ''")
            ->selectRaw('TRIM(empresa) AS empresa')
            ->distinct()
            ->orderBy('empresa')
            ->pluck('empresa');
        $ciudades = RentabilidadAgencia::query()
            ->activas()
            ->whereNotNull('ciudad')
            ->whereRaw("TRIM(ciudad) <> ''")
            ->selectRaw('TRIM(ciudad) AS ciudad')
            ->distinct()
            ->orderBy('ciudad')
            ->pluck('ciudad');
        $rutas = RentabilidadAgencia::query()
            ->activas()
            ->whereNotNull('ruta')
            ->whereRaw("TRIM(ruta) <> ''")
            ->selectRaw('TRIM(ruta) AS ruta')
            ->distinct()
            ->orderBy('ruta')
            ->pluck('ruta');

        if (! $consultaRealizada) {
            $agencias = collect();

            return view('gerencia.rentabilidad-agencia', compact(
                'agencias',
                'mesSeleccionado',
                'empresaSeleccionada',
                'ciudadSeleccionada',
                'rutaSeleccionada',
                'empresas',
                'ciudades',
                'rutas',
                'resumenCumplimiento',
                'resumenCiudades',
                'resumenRutas',
                'agenciasDataTable',
                'agenciasDetalleGrupos',
                'ciudadesResumen',
                'rutasResumen',
                'consultaRealizada',
                'usarIngresosContables',
                'montoR20Global'
            ));
        }

        $fechaInicio = Carbon::createFromFormat('Y-m', $mesSeleccionado)->startOfMonth();
        $fechaFin = $fechaInicio->copy()->endOfMonth();
        $agenciasParaResumen = $this->consultaAgencias(
            $empresaSeleccionada,
            $ciudadSeleccionada,
            $rutaSeleccionada
        )
            ->orderBy('nombre_mostrar')
            ->orderBy('terminal')
            ->get();
        $this->cargarIndicadores($agenciasParaResumen, $fechaInicio, $fechaFin, $usarIngresosContables);
        $resumenCumplimiento = [
            'cumple' => $agenciasParaResumen->filter(fn (RentabilidadAgencia $agencia): bool => (bool) $agencia->cumple)->count(),
            'no_cumple' => $agenciasParaResumen->filter(fn (RentabilidadAgencia $agencia): bool => ! $agencia->cumple)->count(),
        ];
        $ciudadesAgrupadas = $this->agruparIndicadores($agenciasParaResumen, 'ciudad', 'Sin ciudad');
        $rutasAgrupadas = $this->agruparIndicadores($agenciasParaResumen, 'ruta', 'Sin ruta');
        $resumenCiudades = $this->contarCumplimiento($ciudadesAgrupadas);
        $resumenRutas = $this->contarCumplimiento($rutasAgrupadas);
        $agenciasDetalleGrupos = [
            'ciudad' => $ciudadesAgrupadas->pluck('agencias')->values(),
            'ruta' => $rutasAgrupadas->pluck('agencias')->values(),
        ];
        $ciudadesResumen = $ciudadesAgrupadas;
        $rutasResumen = $rutasAgrupadas;
        $agencias = $agenciasParaResumen;
        $agenciasDataTable = $this->formatearAgenciasDataTable($agencias)
            ->take(self::AGENCIAS_CARGA_INICIAL)
            ->values();

        return view('gerencia.rentabilidad-agencia', compact(
            'agencias',
            'mesSeleccionado',
            'empresaSeleccionada',
            'ciudadSeleccionada',
            'rutaSeleccionada',
            'empresas',
            'ciudades',
            'rutas',
            'resumenCumplimiento',
            'resumenCiudades',
            'resumenRutas',
            'agenciasDataTable',
            'agenciasDetalleGrupos',
            'ciudadesResumen',
            'rutasResumen',
            'consultaRealizada',
            'usarIngresosContables',
            'montoR20Global'
        ));
    }

    public function buscar(BuscarRentabilidadAgenciaRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $mesSeleccionado = (string) $datos['mes'];
        $buscar = trim((string) $datos['buscar']);
        $fechaInicio = Carbon::createFromFormat('Y-m', $mesSeleccionado)->startOfMonth();
        $fechaFin = $fechaInicio->copy()->endOfMonth();
        $agenciasBase = $this->consultaAgencias(
            trim((string) ($datos['empresa'] ?? '')),
            trim((string) ($datos['ciudad'] ?? '')),
            trim((string) ($datos['ruta'] ?? ''))
        );

        if (ctype_digit($buscar)) {
            $agenciaExacta = (clone $agenciasBase)->where('terminal', $buscar)->first();
            if ($agenciaExacta !== null) {
                $agencias = collect([$agenciaExacta]);
                $this->cargarIndicadores($agencias, $fechaInicio, $fechaFin, (bool) ($datos['ingresos_contables'] ?? false));

                return response()->json([
                    'data' => $this->formatearAgenciasDataTable($agencias)->values(),
                    'total' => 1,
                    'limit' => self::AGENCIAS_BUSQUEDA_LIMITE,
                ]);
            }
        }

        $agenciasQuery = $agenciasBase->where(function (Builder $query) use ($buscar): void {
            $patron = '%'.$buscar.'%';

            $query->where('terminal', 'like', $patron)
                ->orWhere('nombre_agencia', 'like', $patron)
                ->orWhere('agencia', 'like', $patron);
        });
        $total = (clone $agenciasQuery)->count();
        $agencias = $agenciasQuery
            ->orderByRaw('CASE WHEN TRIM(CAST(terminal AS CHAR)) = ? THEN 0 ELSE 1 END', [$buscar])
            ->orderBy('nombre_mostrar')
            ->orderBy('terminal')
            ->limit(self::AGENCIAS_BUSQUEDA_LIMITE)
            ->get();

        $this->cargarIndicadores($agencias, $fechaInicio, $fechaFin, (bool) ($datos['ingresos_contables'] ?? false));

        return response()->json([
            'data' => $this->formatearAgenciasDataTable($agencias)->values(),
            'total' => $total,
            'limit' => self::AGENCIAS_BUSQUEDA_LIMITE,
        ]);
    }

    public function guardarR20(GuardarRentabilidadAgenciaR20Request $request): RedirectResponse
    {
        $datos = $request->validated();
        RentabilidadAgenciaR20::query()->updateOrCreate(
            ['terminal' => self::TERMINAL_R20_GLOBAL, 'mes' => self::MES_R20_GLOBAL],
            ['monto' => $datos['monto'], 'usuario_id' => $request->user()?->getAuthIdentifier()]
        );

        return to_route('gerencia.rentabilidad-agencia', array_filter([
            'mes' => $datos['mes'],
            'empresa' => $datos['empresa'] ?? null,
            'ciudad' => $datos['ciudad'] ?? null,
            'ruta' => $datos['ruta'] ?? null,
            'ingresos_contables' => $datos['ingresos_contables'] ?? null,
        ], fn (mixed $valor): bool => $valor !== null && $valor !== ''))
            ->with('mensaje', 'El gasto R20 se guardó para todas las terminales y todos los meses.');
    }

    private function consultaAgencias(string $empresa, string $ciudad, string $ruta): Builder
    {
        $query = RentabilidadAgencia::query()
            ->activas()
            ->whereNotNull('terminal')
            ->whereRaw("TRIM(CAST(terminal AS CHAR)) <> ''")
            ->select(['id', 'terminal', 'nombre_agencia', 'agencia', 'sistema', 'empresa', 'ciudad', 'ruta', 'estatus'])
            ->selectRaw("COALESCE(NULLIF(TRIM(nombre_agencia), ''), NULLIF(TRIM(agencia), ''), 'SIN NOMBRE') AS nombre_mostrar");

        if ($empresa !== '') {
            $query->whereRaw('TRIM(empresa) = ?', [$empresa]);
        }
        if ($ciudad !== '') {
            $query->whereRaw('TRIM(ciudad) = ?', [$ciudad]);
        }
        if ($ruta !== '') {
            $query->whereRaw('TRIM(ruta) = ?', [$ruta]);
        }

        return $query;
    }

    private function cargarIndicadores(Collection $agencias, Carbon $fechaInicio, Carbon $fechaFin, bool $usarIngresosContables): void
    {
        $terminales = $agencias
            ->pluck('terminal')
            ->map(fn ($terminal): string => trim((string) $terminal))
            ->filter()
            ->unique()
            ->values();
        $centrosDeCosto = $terminales->isEmpty()
            ? collect()
            : CentroDeCosto::query()
                ->whereIn('id_viejo', $terminales->all())
                ->where('inactivo', false)
                ->get(['id_viejo', 'company_id', 'id_centro_costo'])
                ->groupBy('id_viejo');
        $ventasBet = $usarIngresosContables ? collect() : $this->ventasBrutasPorTerminal('vt_usuarios_bet', $fechaInicio, $fechaFin, $terminales);
        $ventasNet = $usarIngresosContables ? collect() : $this->ventasBrutasPorTerminal('vt_usuarios_net', $fechaInicio, $fechaFin, $terminales);
        $ingresosContables = $usarIngresosContables ? $this->ingresosContablesPorTerminal($fechaInicio, $fechaFin, $terminales) : collect();
        $costosYGastos = $this->costosYGastosPorTerminal($fechaInicio, $fechaFin, $terminales);
        $montoR20Global = $terminales->isEmpty()
            ? 0.0
            : $this->montoR20Global();

        $agencias->transform(
            fn (RentabilidadAgencia $agencia): RentabilidadAgencia => $this->agregarIndicadores(
                $agencia,
                $ventasBet,
                $ventasNet,
                $costosYGastos,
                $usarIngresosContables,
                $ingresosContables,
                $montoR20Global,
                $centrosDeCosto
            )
        );
    }

    private function formatearAgenciasDataTable(Collection $agencias): Collection
    {
        return $agencias->map(fn (RentabilidadAgencia $agencia): array => [
            'id' => (int) $agencia->id,
            'nombre' => (string) $agencia->nombre_mostrar,
            'terminal' => trim((string) $agencia->terminal),
            'centro_costo_id' => $agencia->centro_costo_id,
            'venta_bruta' => (float) $agencia->venta_bruta_mes,
            'costos' => (float) $agencia->costos_mes,
            'gastos' => (float) $agencia->gastos_mes,
            'cuentas' => $agencia->cuentas_mes,
            'r20' => (float) $agencia->r20_mes,
            'balance' => (float) $agencia->balance_mes,
            'cumple' => (bool) $agencia->cumple,
        ]);
    }

    private function agruparIndicadores(Collection $agencias, string $campo, string $nombreVacio): Collection
    {
        return $agencias
            ->groupBy(function (RentabilidadAgencia $agencia) use ($campo, $nombreVacio): string {
                $nombre = trim((string) $agencia->getAttribute($campo));

                return $nombre !== '' ? $nombre : $nombreVacio;
            })
            ->map(function (Collection $grupo, $nombre): array {
                $ventaBruta = (float) $grupo->sum('venta_bruta_mes');
                $costos = (float) $grupo->sum('costos_mes');
                $gastos = (float) $grupo->sum('gastos_mes');
                $balance = $ventaBruta - $costos - $gastos;

                return [
                    'nombre' => (string) $nombre,
                    'cantidad_agencias' => $grupo->count(),
                    'agencias' => $grupo
                        ->map(fn (RentabilidadAgencia $agencia): array => [
                            'nombre' => (string) $agencia->nombre_mostrar,
                            'terminal' => trim((string) $agencia->terminal),
                        ])
                        ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)
                        ->values()
                        ->all(),
                    'venta_bruta_mes' => $ventaBruta,
                    'costos_mes' => $costos,
                    'gastos_mes' => $gastos,
                    'balance_mes' => $balance,
                    'cumple' => $balance >= 0,
                ];
            })
            ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * @return array{cumple: int, no_cumple: int}
     */
    private function contarCumplimiento(Collection $resumen): array
    {
        return [
            'cumple' => $resumen->filter(fn (array $fila): bool => $fila['cumple'])->count(),
            'no_cumple' => $resumen->filter(fn (array $fila): bool => ! $fila['cumple'])->count(),
        ];
    }

    private function agregarIndicadores(
        RentabilidadAgencia $agencia,
        Collection $ventasBet,
        Collection $ventasNet,
        Collection $costosYGastos,
        bool $usarIngresosContables,
        Collection $ingresosContables,
        float $montoR20Global,
        Collection $centrosDeCosto
    ): RentabilidadAgencia {
        $terminal = trim((string) $agencia->terminal);
        $sistema = mb_strtoupper(trim((string) $agencia->sistema));
        $movimientos = $costosYGastos->get($terminal, collect());
        $centrosTerminal = $centrosDeCosto->get($terminal, collect());
        $empresa = mb_strtolower(trim((string) $agencia->empresa));
        $companyId = match (true) {
            str_contains($empresa, 'negosur') => '169',
            str_contains($empresa, 'joselito') => '168',
            default => null,
        };
        $centro = $companyId === null
            ? ($centrosTerminal->count() === 1 ? $centrosTerminal->first() : null)
            : $centrosTerminal->first(fn (CentroDeCosto $item): bool => str_starts_with(trim((string) $item->company_id), $companyId));
        $montoR20 = $montoR20Global;
        if ($montoR20 > 0) {
            $movimientos->push([
                'terminal' => $terminal,
                'cuenta' => 'R20',
                'descripcion' => 'Gasto R20 manual',
                'tipo' => 'gasto',
                'clasificacion' => 'FIJO',
                'importe' => $montoR20,
            ]);
        }
        $ventaBruta = $usarIngresosContables
            ? (float) $ingresosContables->get($terminal, 0)
            : match ($sistema) {
                'LOTOBET' => (float) $ventasBet->get($terminal, 0),
                'LOTONET', 'LOTENET' => (float) $ventasNet->get($terminal, 0),
                default => (float) $ventasBet->get($terminal, 0) + (float) $ventasNet->get($terminal, 0),
            };
        $costos = (float) $movimientos
            ->where('tipo', 'costo')
            ->sum('importe');
        $gastos = (float) $movimientos
            ->where('tipo', 'gasto')
            ->sum('importe');
        $balance = $ventaBruta - $costos - $gastos;

        $agencia->setAttribute('venta_bruta_mes', $ventaBruta);
        $agencia->setAttribute('costos_mes', $costos);
        $agencia->setAttribute('gastos_mes', $gastos);
        $agencia->setAttribute('centro_costo_id', $centro?->id_centro_costo);
        $agencia->setAttribute('r20_mes', $montoR20);
        $agencia->setAttribute('cuentas_mes', $movimientos->values()->all());
        $agencia->setAttribute('balance_mes', $balance);
        $agencia->setAttribute('cumple', $balance >= 0);

        return $agencia;
    }

    private function ventasBrutasPorTerminal(
        string $tabla,
        Carbon $fechaInicio,
        Carbon $fechaFin,
        Collection $terminales
    ): Collection {
        if ($terminales->isEmpty()) {
            return collect();
        }

        return DB::table($tabla)
            ->whereBetween('fecha', [$fechaInicio->toDateString(), $fechaFin->toDateString()])
            ->whereIn('agencia_id', $terminales->all())
            ->whereNotNull('agencia_id')
            ->selectRaw('TRIM(CAST(agencia_id AS CHAR)) AS terminal, SUM(monto) AS venta_bruta')
            ->groupByRaw('TRIM(CAST(agencia_id AS CHAR))')
            ->pluck('venta_bruta', 'terminal');
    }

    private function costosYGastosPorTerminal(
        Carbon $fechaInicio,
        Carbon $fechaFin,
        Collection $terminales
    ): Collection {
        if ($terminales->isEmpty()) {
            return collect();
        }

        $clasificaciones = ClasificacionCuenta::clasificacionesActivas();
        if ($clasificaciones->isEmpty()) {
            return collect();
        }

        return EntradaDiario::query()
            ->from('entradas_diario as movimientos')
            ->join('cuentas_contables as cuentas', 'cuentas.cuenta', '=', 'movimientos.cuenta')
            ->whereBetween('movimientos.fecha', [$fechaInicio->toDateString(), $fechaFin->toDateString()])
            ->whereNotNull('movimientos.id_viejo')
            ->whereIn('movimientos.cuenta', $clasificaciones->keys()->all())
            ->whereIn('movimientos.id_viejo', $terminales->all())
            ->whereIn(DB::raw('LOWER(TRIM(cuentas.tipo))'), ['costo', 'gasto'])
            ->selectRaw('TRIM(CAST(movimientos.id_viejo AS CHAR)) AS terminal')
            ->selectRaw('TRIM(cuentas.cuenta) AS cuenta')
            ->selectRaw("COALESCE(NULLIF(TRIM(cuentas.descripcion), ''), 'Sin descripción') AS descripcion")
            ->selectRaw('LOWER(TRIM(cuentas.tipo)) AS tipo')
            ->selectRaw('SUM(COALESCE(movimientos.debito, 0) - COALESCE(movimientos.credito, 0)) AS importe')
            ->groupByRaw('TRIM(CAST(movimientos.id_viejo AS CHAR)), TRIM(cuentas.cuenta), cuentas.descripcion, LOWER(TRIM(cuentas.tipo))')
            ->orderByRaw('TRIM(cuentas.cuenta)')
            ->get()
            ->map(fn (EntradaDiario $movimiento): array => [
                'terminal' => (string) $movimiento->terminal,
                'cuenta' => (string) $movimiento->cuenta,
                'descripcion' => (string) $movimiento->descripcion,
                'tipo' => (string) $movimiento->tipo,
                'clasificacion' => $clasificaciones->get((string) $movimiento->cuenta, ''),
                'importe' => (float) $movimiento->importe,
            ])
            ->groupBy('terminal');
    }

    private function ingresosContablesPorTerminal(Carbon $fechaInicio, Carbon $fechaFin, Collection $terminales): Collection
    {
        if ($terminales->isEmpty()) {
            return collect();
        }

        return EntradaDiario::query()
            ->whereBetween('fecha', [$fechaInicio->toDateString(), $fechaFin->toDateString()])
            ->whereNotNull('id_viejo')
            ->whereIn('cuenta', self::CUENTAS_INGRESO)
            ->whereIn('id_viejo', $terminales->all())
            ->selectRaw('TRIM(CAST(id_viejo AS CHAR)) AS terminal, SUM(COALESCE(credito, 0) - COALESCE(debito, 0)) AS ingreso')
            ->groupByRaw('TRIM(CAST(id_viejo AS CHAR))')
            ->pluck('ingreso', 'terminal');
    }

    private function montoR20Global(): float
    {
        return (float) RentabilidadAgenciaR20::query()
            ->where('terminal', self::TERMINAL_R20_GLOBAL)
            ->orderByRaw('CASE WHEN mes = ? THEN 0 ELSE 1 END', [self::MES_R20_GLOBAL])
            ->orderByDesc('updated_at')
            ->value('monto');
    }
}
