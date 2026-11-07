<?php

namespace App\Http\Controllers\Gerencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultarRentabilidadAgenciaV11Request;
use App\Http\Requests\ImprimirRentabilidadAgenciaV11Request;
use App\Models\RentabilidadAgenciaV5;
use App\Services\ClasificacionRentabilidadAgenciaV11;
use App\Services\RentabilidadAgenciaV10Service;
use App\Services\RentabilidadAgenciaV11Pdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class RentabilidadAgenciaV11Controller extends Controller
{
    private const VERSION_IMPRESION = 5;

    public function __construct(private readonly RentabilidadAgenciaV10Service $reporte, private readonly ClasificacionRentabilidadAgenciaV11 $clasificacion) {}

    public function index(ConsultarRentabilidadAgenciaV11Request $request): View
    {
        $filtros = [...['anio' => now()->year, 'mes_inicio' => 1, 'mes_fin' => now()->month, 'empresa' => '', 'ruta' => '', 'alcance' => 'todas', 'terminal' => '', 'agrupacion' => 'terminal'], ...$request->validated()];
        foreach (['empresa', 'ruta', 'terminal'] as $campo) {
            $filtros[$campo] = trim((string) $filtros[$campo]);
        }
        $productos = $this->reporte->productos($filtros);
        $productosPremios = $this->reporte->productosPremios($filtros);
        $resultado = $request->filled('anio') ? $this->reporte->reporte($filtros) : null;
        $meses = [];
        if ($resultado !== null) {
            for ($fecha = $resultado['desde']->copy(); $fecha->lte($resultado['hasta']); $fecha->addMonth()) {
                $meses[] = ['codigo' => $fecha->format('Y-m'), 'nombre' => $fecha->copy()->locale('es')->isoFormat('MMMM YYYY')];
            }
        }
        $tradicionalGlobal = $resultado === null ? null : $this->reporte->reporte([
            ...$filtros, 'ruta' => '', 'alcance' => 'todas', 'terminal' => '',
            'productos_filtrados' => 1, 'productos' => ['Tradicional'],
            'premios_filtrados' => 1,
            'premios_productos' => array_values(array_intersect($productosPremios, ['Tradicional'])),
        ]);
        $referenciasGlobales = $resultado === null ? [] : $this->referenciasGlobales($filtros, $resultado, $tradicionalGlobal);
        if ($resultado !== null) {
            $resultado['filas'] = $resultado['filas']->filter(fn (array $fila): bool => collect($fila['periodos'])
                ->contains(fn (array $periodo): bool => (float) $periodo['ventas'] !== 0.0))->values();
        }
        $datosTabla = $resultado === null ? [] : $resultado['filas']->map(fn (array $fila): array => [
            'terminal' => $fila['terminal'], 'nombre' => $fila['nombre'],
            'empresa' => $fila['empresa'], 'ruta' => $fila['ruta'],
            'periodos' => array_map(fn (array $periodo): array => $this->calcularPeriodoGlobal($periodo, $referenciasGlobales['empresas'][$fila['empresa']]['meses'][$periodo['mes']]['premios_sacados'] ?? null), $fila['periodos']),
        ])->values()->all();
        $datosTabla = array_map(fn (array $fila): array => [...$fila, 'clasificacion' => $this->clasificacion->clasificar($fila['periodos'])], $datosTabla);
        $resumenCategorias = $this->clasificacion->resumir($datosTabla);
        if ($filtros['agrupacion'] === 'ruta') {
            $datosTabla = $this->agruparPorRuta($datosTabla);
            $datosTabla = array_map(fn (array $fila): array => [...$fila, 'clasificacion' => $this->clasificacion->clasificar($fila['periodos'])], $datosTabla);
        }
        $informeId = null;
        if ($datosTabla !== []) {
            $informeId = (string) Str::uuid();
            Cache::put('rentabilidad-v11:impresion:'.$informeId, [
                'version' => self::VERSION_IMPRESION,
                'usuario' => (string) $request->user()->getAuthIdentifier(),
                'filtros' => $filtros, 'productos' => $productos, 'meses' => $meses, 'filas' => $datosTabla,
                'productos_premios' => $productosPremios,
                'referencias_globales' => $referenciasGlobales,
            ], now()->addMinutes(10));
        }
        $rutasDisponibles = RentabilidadAgenciaV5::query()->activas()->whereNotNull('ruta')->whereRaw("TRIM(ruta) <> ''")
            ->selectRaw("COALESCE(TRIM(empresa), '') AS empresa, TRIM(ruta) AS ruta")->distinct()->orderBy('ruta')->get();
        $empresas = RentabilidadAgenciaV5::query()->activas()->whereNotNull('empresa')->whereRaw("TRIM(empresa) <> ''")
            ->selectRaw('TRIM(empresa) AS empresa')->distinct()->orderBy('empresa')->pluck('empresa');
        $rutas = $rutasDisponibles->when($filtros['empresa'] !== '', fn (Collection $filas): Collection => $filas->where('empresa', $filtros['empresa']))->pluck('ruta')->unique()->values();

        return view('gerencia.rentabilidad-agencia-v11', compact('filtros', 'productos', 'productosPremios', 'resultado', 'meses', 'datosTabla', 'empresas', 'rutas', 'rutasDisponibles', 'informeId', 'referenciasGlobales', 'resumenCategorias'));
    }

    public function imprimir(ImprimirRentabilidadAgenciaV11Request $request, RentabilidadAgenciaV11Pdf $generador): Response|RedirectResponse
    {
        try {
            $consulta = Cache::get('rentabilidad-v11:impresion:'.$request->validated('informe_id'));
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

            return redirect()->route('gerencia.rentabilidad-agencia-v11')->withErrors($exception->errors());
        }
        $porRuta = ($consulta['filtros']['agrupacion'] ?? 'terminal') === 'ruta';
        $grupos = $porRuta ? collect(['Resumen por ruta' => $filas]) : $filas->groupBy(fn (array $fila): string => $fila['ruta'] ?: 'Sin ruta')->sortKeys();
        $bloquesMeses = [$consulta['meses']];
        $filtros = $consulta['filtros'];
        $productos = $consulta['productos'];
        $productosPremios = $consulta['productos_premios'];
        $referenciasGlobales = $consulta['referencias_globales'];
        $esImpresion = $request->validated('salida') === 'imprimir';
        $tamanoHoja = $request->validated('tamano_hoja', 'letter');
        $clavePdf = 'rentabilidad-v11:pdf:'.hash('sha256', json_encode([$request->validated('informe_id'), $ruta, $tamanoHoja, 'canvas-v10'], JSON_THROW_ON_ERROR));
        $pdfCodificado = $esImpresion ? null : Cache::get($clavePdf);
        $contenidoPdf = is_string($pdfCodificado) ? base64_decode($pdfCodificado, true) : false;
        if (is_string($contenidoPdf) && str_starts_with($contenidoPdf, '%PDF-')) {
            return $this->descargarPdf($contenidoPdf);
        }
        $anchoHoja = $tamanoHoja === 'legal' ? 1008 : 792;
        $tamanoFuenteImportes = $this->tamanoFuenteImpresion($grupos, $anchoHoja, count($consulta['meses']));
        $datos = compact('grupos', 'bloquesMeses', 'filtros', 'productos', 'productosPremios', 'esImpresion', 'referenciasGlobales', 'tamanoHoja', 'tamanoFuenteImportes');
        if ($esImpresion) {
            return response()->view('gerencia.rentabilidad-agencia-v11-impresion', $datos)->header('Cache-Control', 'private, no-store');
        }
        $contenidoPdf = $generador->generar($datos);
        Cache::put($clavePdf, base64_encode($contenidoPdf), now()->addMinutes(10));

        return $this->descargarPdf($contenidoPdf);
    }

    private function descargarPdf(string $contenido): Response
    {
        return new Response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="rentabilidad_agencia_v11.pdf"',
            'Content-Length' => (string) strlen($contenido),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * @param  Collection<string, Collection<int, array<string, mixed>>>  $grupos
     */
    private function tamanoFuenteImpresion(Collection $grupos, int $anchoHoja, int $mesesPorBloque): float
    {
        $columnas = [
            'ventas' => ['ventas_tradicionales', 'ventas_no_tradicionales', 'ventas_recargas'],
            'ingresos_calculados' => ['ingresos_tradicionales', 'ingresos_no_tradicionales', 'ingresos_recargas'],
            'premios_sacados' => ['premios_sacados'], 'utilidad' => ['utilidad'],
            'gastos' => ['gastos'], 'balance_general' => ['balance_general'],
        ];
        $metricas = Pdf::getDomPDF()->getFontMetrics();
        $fuente = $metricas->getFont('DejaVu Sans', 'bold');
        $pesos = RentabilidadAgenciaV11Pdf::PESOS_COLUMNAS;
        $anchoMes = ($anchoHoja - RentabilidadAgenciaV11Pdf::MARGEN_PAGINA * 2)
            * (1 - RentabilidadAgenciaV11Pdf::ANCHO_AGENCIA) / max(1, $mesesPorBloque);
        $margen = RentabilidadAgenciaV11Pdf::margenCelda($mesesPorBloque);
        $tamano = 18.0;
        foreach ($grupos as $filas) {
            $periodos = $filas->flatMap(fn (array $fila): array => $fila['periodos']);
            $conjuntos = $periodos->map(fn (array $periodo): Collection => collect([$periodo]))
                ->concat($periodos->groupBy('mes')->values());
            foreach ($conjuntos as $conjunto) {
                foreach ($columnas as $columna => $campos) {
                    $anchoDisponible = $anchoMes * $pesos[$columna] / array_sum($pesos) - $margen * 2 - 0.5;
                    foreach ($campos as $campo) {
                        $medida = $metricas->getTextWidth(number_format($conjunto->sum($campo), 0), $fuente, 1);
                        $tamano = min($tamano, $anchoDisponible / max(1, $medida));
                    }
                }
            }
        }

        return floor($tamano * 10) / 10;
    }

    /**
     * @param  array<string, mixed>  $periodo
     * @return array<string, mixed>
     */
    private function calcularPeriodoGlobal(array $periodo, ?float $porcentaje): array
    {
        $premios = $porcentaje === null ? null : round($periodo['ventas_tradicionales'] * $porcentaje / 100, 2);
        $utilidad = $premios === null ? null : round($periodo['ventas_tradicionales'] - $premios, 2);

        return [
            ...$periodo,
            'porcentaje_dev_g' => $porcentaje,
            'premios_sacados' => $premios,
            'utilidad' => $utilidad,
            'balance_general' => $utilidad === null ? null : round($utilidad - $periodo['gastos'], 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $globalTradicional
     * @return array<string, mixed>
     */
    private function referenciasGlobales(array $filtros, array $resultado, array $globalTradicional): array
    {
        $ingresos2 = $this->reporte->porcentajesIngresos2($filtros);
        $totalesIngresos2 = collect($ingresos2)->flatMap(fn (array $meses): array => array_map(fn (string $mes, array $datos): array => ['mes' => $mes, ...$datos], array_keys($meses), array_values($meses)))
            ->groupBy('mes')->map(function (Collection $empresas): ?float {
                $ingresos = $empresas->sum('ingresos');

                return $ingresos == 0 || $empresas->contains(fn (array $empresa): bool => $empresa['ingresos'] === null) ? null : $empresas->sum('ventas') / $ingresos;
            })->all();
        $global = $filtros['ruta'] === '' && $filtros['alcance'] !== 'terminal'
            ? $resultado
            : $this->reporte->reporte([...$filtros, 'ruta' => '', 'alcance' => 'todas', 'terminal' => '']);

        return [
            'ingresos_2' => ['empresas' => $ingresos2, 'total' => $totalesIngresos2],
            'ingresos' => $global['filas']->flatMap(fn (array $fila): array => array_map(fn (array $periodo): array => [...$periodo, 'empresa' => $fila['empresa']], $fila['periodos']))
                ->groupBy('mes')->map(function (Collection $periodos): ?float {
                    $empresas = $periodos->unique('empresa');
                    $base = $empresas->sum('base_porcentaje_no_tradicional');

                    return $base <= 0 || $empresas->contains(fn (array $periodo): bool => $periodo['porcentaje_no_tradicional'] === null) ? null
                        : $empresas->sum(fn (array $periodo): float => $periodo['base_porcentaje_no_tradicional'] * $periodo['porcentaje_no_tradicional']) / $base;
                })->all(),
            'empresas' => $globalTradicional['filas']->groupBy('empresa')->map(fn (Collection $filas): array => $this->referencia($filas))->all(),
            'total' => $this->referencia($globalTradicional['filas']),
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
                    $mes['porcentaje_dev_g'] = $periodo['porcentaje_dev_g'];
                    foreach ($campos as $campo) {
                        $sinDatos = in_array($campo, ['premios_sacados', 'utilidad', 'balance_general'], true)
                            && $terminales->contains(fn (array $fila): bool => $fila['periodos'][$indice][$campo] === null);
                        $mes[$campo] = $sinDatos ? null : round((float) $terminales->sum(fn (array $fila): float => (float) ($fila['periodos'][$indice][$campo] ?? 0)), 2);
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
