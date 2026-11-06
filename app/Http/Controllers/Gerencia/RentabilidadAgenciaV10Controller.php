<?php

namespace App\Http\Controllers\Gerencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultarRentabilidadAgenciaV10Request;
use App\Http\Requests\ImprimirRentabilidadAgenciaV10Request;
use App\Models\RentabilidadAgenciaV5;
use App\Services\RentabilidadAgenciaV10Service;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class RentabilidadAgenciaV10Controller extends Controller
{
    private const VERSION_IMPRESION = 6;

    public function __construct(private readonly RentabilidadAgenciaV10Service $reporte) {}

    public function index(ConsultarRentabilidadAgenciaV10Request $request): View
    {
        $filtros = [...['anio' => now()->year, 'mes_inicio' => 1, 'mes_fin' => now()->month, 'empresa' => '', 'ruta' => '', 'alcance' => 'todas', 'terminal' => '', 'agrupacion' => 'terminal'], ...$request->validated()];
        foreach (['empresa', 'ruta', 'terminal'] as $campo) {
            $filtros[$campo] = trim((string) $filtros[$campo]);
        }
        $productos = $this->reporte->productos($filtros);
        $resultado = $request->filled('anio') ? $this->reporte->reporte($filtros) : null;
        $meses = [];
        if ($resultado !== null) {
            for ($fecha = $resultado['desde']->copy(); $fecha->lte($resultado['hasta']); $fecha->addMonth()) {
                $meses[] = ['codigo' => $fecha->format('Y-m'), 'nombre' => $fecha->copy()->locale('es')->isoFormat('MMMM YYYY')];
            }
        }
        $datosTabla = $resultado === null ? [] : $resultado['filas']->map(fn (array $fila): array => [
            'terminal' => $fila['terminal'], 'nombre' => $fila['nombre'],
            'empresa' => $fila['empresa'], 'ruta' => $fila['ruta'], 'periodos' => $fila['periodos'],
        ])->values()->all();
        if ($filtros['agrupacion'] === 'ruta') {
            $datosTabla = $this->agruparPorRuta($datosTabla);
        }
        $referenciasGlobales = $resultado === null ? [] : $this->referenciasGlobales($filtros, $resultado);
        $informeId = null;
        if ($datosTabla !== []) {
            $informeId = (string) Str::uuid();
            Cache::put('rentabilidad-v10:impresion:'.$informeId, [
                'version' => self::VERSION_IMPRESION,
                'usuario' => (string) $request->user()->getAuthIdentifier(),
                'filtros' => $filtros, 'productos' => $productos, 'meses' => $meses, 'filas' => $datosTabla,
                'referencias_globales' => $referenciasGlobales,
            ], now()->addMinutes(10));
        }
        $rutasDisponibles = RentabilidadAgenciaV5::query()->activas()->whereNotNull('ruta')->whereRaw("TRIM(ruta) <> ''")
            ->selectRaw("COALESCE(TRIM(empresa), '') AS empresa, TRIM(ruta) AS ruta")->distinct()->orderBy('ruta')->get();
        $empresas = RentabilidadAgenciaV5::query()->activas()->whereNotNull('empresa')->whereRaw("TRIM(empresa) <> ''")
            ->selectRaw('TRIM(empresa) AS empresa')->distinct()->orderBy('empresa')->pluck('empresa');
        $rutas = $rutasDisponibles->when($filtros['empresa'] !== '', fn (Collection $filas): Collection => $filas->where('empresa', $filtros['empresa']))->pluck('ruta')->unique()->values();

        return view('gerencia.rentabilidad-agencia-v10', compact('filtros', 'productos', 'resultado', 'meses', 'datosTabla', 'empresas', 'rutas', 'rutasDisponibles', 'informeId', 'referenciasGlobales'));
    }

    public function imprimir(ImprimirRentabilidadAgenciaV10Request $request): Response|RedirectResponse
    {
        try {
            $consulta = Cache::get('rentabilidad-v10:impresion:'.$request->validated('informe_id'));
            if ($consulta === null || $consulta['usuario'] !== (string) $request->user()->getAuthIdentifier()) {
                throw ValidationException::withMessages(['informe_id' => 'La consulta ha caducado. Vuelve a consultar el reporte antes de imprimir.']);
            }
            if (($consulta['version'] ?? null) !== self::VERSION_IMPRESION) {
                throw ValidationException::withMessages(['informe_id' => 'Esta consulta es anterior a la actualización de porcentajes. Pulsa Consultar nuevamente y vuelve a generar el documento.']);
            }
            $ruta = trim((string) $request->validated('ruta_impresion', ''));
            $filas = collect($consulta['filas'])->when($ruta !== '', fn (Collection $filas): Collection => $filas->where('ruta', $ruta));
            if ($filas->isEmpty()) {
                throw ValidationException::withMessages(['ruta_impresion' => 'La ruta seleccionada no pertenece a esta consulta.']);
            }
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return redirect()->route('gerencia.rentabilidad-agencia-v10')->withErrors($exception->errors());
        }
        $porRuta = ($consulta['filtros']['agrupacion'] ?? 'terminal') === 'ruta';
        $grupos = $porRuta ? collect(['Resumen por ruta' => $filas]) : $filas->groupBy(fn (array $fila): string => $fila['ruta'] ?: 'Sin ruta')->sortKeys();
        $bloquesMeses = array_chunk($consulta['meses'], 2, true);
        $filtros = $consulta['filtros'];
        $productos = $consulta['productos'];
        $referenciasGlobales = $consulta['referencias_globales'];
        $esImpresion = $request->validated('salida') === 'imprimir';
        $datos = compact('grupos', 'bloquesMeses', 'filtros', 'productos', 'esImpresion', 'referenciasGlobales');
        if ($esImpresion) {
            return response()->view('gerencia.rentabilidad-agencia-v10-impresion', $datos)->header('Cache-Control', 'private, no-store');
        }
        $pdf = Pdf::loadView('gerencia.rentabilidad-agencia-v10-impresion', $datos)->setPaper('a4', 'landscape');
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $dompdf->getCanvas()->page_text(695, 575, 'Página {PAGE_NUM} de {PAGE_COUNT}', $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 8, [0.4, 0.45, 0.5]);

        return $pdf->download('rentabilidad_agencia_v10.pdf')->header('Cache-Control', 'private, no-store');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    private function referenciasGlobales(array $filtros, array $resultado): array
    {
        $global = $filtros['ruta'] === '' && $filtros['alcance'] !== 'terminal'
            ? $resultado
            : $this->reporte->reporte([...$filtros, 'ruta' => '', 'alcance' => 'todas', 'terminal' => '']);

        return [
            'ingresos' => $global['filas']->flatMap(fn (array $fila): array => array_map(fn (array $periodo): array => [...$periodo, 'empresa' => $fila['empresa']], $fila['periodos']))
                ->groupBy('mes')->map(function (Collection $periodos): ?float {
                    $empresas = $periodos->unique('empresa');
                    $base = $empresas->sum('base_porcentaje_no_tradicional');

                    return $base <= 0 || $empresas->contains(fn (array $periodo): bool => $periodo['porcentaje_no_tradicional'] === null) ? null
                        : $empresas->sum(fn (array $periodo): float => $periodo['base_porcentaje_no_tradicional'] * $periodo['porcentaje_no_tradicional']) / $base;
                })->all(),
            'empresas' => $global['filas']->groupBy('empresa')->map(fn (Collection $filas): array => $this->referencia($filas))->all(),
            'total' => $this->referencia($global['filas']),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private function referencia(Collection $filas): array
    {
        $periodos = $filas->flatMap(fn (array $fila): array => $fila['periodos']);

        return [
            'meses' => $periodos->groupBy('mes')->map(fn (Collection $mes): array => $this->porcentajes($mes))->all(),
            'periodo' => $this->porcentajes($periodos),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $periodos
     * @return array{premios_sacados: ?float, utilidad: ?float}
     */
    private function porcentajes(Collection $periodos): array
    {
        $ventas = (float) $periodos->sum('ventas');

        return [
            'premios_sacados' => $ventas == 0 ? null : $periodos->sum('premios_sacados') / $ventas * 100,
            'utilidad' => $ventas == 0 ? null : $periodos->sum('utilidad') / $ventas * 100,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function agruparPorRuta(array $filas): array
    {
        $campos = ['ventas', 'ventas_loterias', 'ventas_tradicionales', 'ventas_no_tradicionales', 'ventas_recargas', 'ingresos_tradicionales', 'ingresos_recargas', 'premios_sacados', 'utilidad', 'costos', 'gastos_contables', 'r20', 'gastos', 'balance_general'];

        return collect($filas)
            ->groupBy(fn (array $fila): string => json_encode([$fila['empresa'], $fila['ruta']], JSON_THROW_ON_ERROR))
            ->map(function (Collection $terminales) use ($campos): array {
                $primera = $terminales->first();
                $periodos = [];
                foreach ($primera['periodos'] as $indice => $periodo) {
                    $mes = ['mes' => $periodo['mes']];
                    $mes['porcentaje_no_tradicional'] = $periodo['porcentaje_no_tradicional'];
                    foreach ($campos as $campo) {
                        $mes[$campo] = round((float) $terminales->sum(fn (array $fila): float => (float) ($fila['periodos'][$indice][$campo] ?? 0)), 2);
                    }
                    $incompletos = $terminales->filter(fn (array $fila): bool => $fila['periodos'][$indice]['ingresos_calculados'] === null);
                    $mes['ingresos_no_tradicionales'] = $incompletos->isEmpty() ? $terminales->sum(fn (array $fila): float => $fila['periodos'][$indice]['ingresos_no_tradicionales']) : null;
                    $mes['ingresos_calculados'] = $incompletos->isEmpty() ? round($terminales->sum(fn (array $fila): float => $fila['periodos'][$indice]['ingresos_calculados']), 2) : null;
                    $mes['estado_ingresos'] = $incompletos->isEmpty() ? 'listo' : 'sin_datos';
                    $periodos[] = $mes;
                }

                return [
                    'terminal' => '', 'nombre' => $primera['ruta'] ?: 'Sin ruta',
                    'empresa' => $primera['empresa'], 'ruta' => $primera['ruta'],
                    'cantidad_terminales' => $terminales->pluck('terminal')->unique()->count(),
                    'periodos' => $periodos,
                ];
            })
            ->sortBy([['nombre', 'asc'], ['empresa', 'asc']])->values()->all();
    }
}
