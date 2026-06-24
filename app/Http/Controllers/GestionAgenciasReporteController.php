<?php

namespace App\Http\Controllers;

use App\Models\Agencia;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ZipArchive;

class GestionAgenciasReporteController extends Controller
{
    private const IMPORT_CHUNK_SIZE = 1000;
    private const REPORT_TEXT_COLLATION = 'utf8mb4_unicode_ci';

    public function index(): View
    {
        return view('reportes.gestion-agencias', [
            'filas' => collect(),
            'resumen' => $this->resumenDesdeTabla(),
            'filtrosCatalogo' => $this->filtrosDisponiblesDesdeTabla(),
            'estatusResumen' => $this->conteoEstatusTerminales(),
            'estatusDetalle' => $this->detalleEstatusTerminales(),
            'horaServidor' => now()->toIso8601String(),
            'archivos' => session('gestion_agencias_archivos', []),
            'agenciasSinVentas' => $this->agenciasSinVentasDesdeTabla(),
            'ventasPorAgencia' => $this->ventasPorAgenciaDesdeTabla(),
            'tendenciaVentasHora' => $this->tendenciaVentasPorHoraDesdeTabla(),
        ]);
    }

    public function procesar(Request $request): RedirectResponse
    {
        set_time_limit(300);
        DB::connection()->disableQueryLog();

        $validated = $request->validate([
            'tradicional' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:51200'],
            'no_tradicional' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:51200'],
        ], [
            'tradicional.uploaded' => 'No se pudo subir el archivo Tradicional. Revisa que no supere el limite de subida configurado en PHP.',
            'no_tradicional.uploaded' => 'No se pudo subir el archivo No Tradicional. Revisa que no supere el limite de subida configurado en PHP.',
        ]);

        /** @var UploadedFile $tradicional */
        $tradicional = $validated['tradicional'];
        /** @var UploadedFile $noTradicional */
        $noTradicional = $validated['no_tradicional'];

        $this->reemplazarTablaReporte(function () use ($tradicional, $noTradicional) {
            $this->limpiarArchivo($tradicional, 'Tradicional', [
                'fecha' => 'Fecha',
                'agencia' => 'Agencia',
                'total_apostado' => 'Total Apostado',
                'estatus' => 'Estatus',
                'terminal' => 'Terminal',
                'usuario_venta' => 'Usr. Venta',
            ]);

            $this->limpiarArchivo($noTradicional, 'No Tradicional', [
                'agencia' => 'Agencia',
                'estatus' => 'Estatus',
                'fecha' => 'Fecha',
                'terminal' => 'Id Terminal',
                'usuario_venta' => 'Usr. Venta',
                'total_apostado' => 'Total Apostado',
            ]);
        });

        return redirect()
            ->route('reportes.gestion-agencias')
            ->with('gestion_agencias_archivos', [
                'tradicional' => $tradicional->getClientOriginalName(),
                'no_tradicional' => $noTradicional->getClientOriginalName(),
            ]);
    }

    public function data(Request $request)
    {
        $momentoCalculo = now();
        $draw = (int) $request->input('draw', 1);
        $start = max((int) $request->input('start', 0), 0);
        $length = (int) $request->input('length', 25);
        $length = $length > 0 ? min($length, 500) : 25;
        $search = trim((string) $request->input('search.value', ''));
        $estatusFiltro = trim((string) $request->input('estatus_filter', ''));
        $filtrosAgencia = $this->filtrosAgencia($request);
        $umbrales = $this->umbralesVenta($request);
        $columns = [
            0 => 'gav.tipo',
            1 => 'gav.terminal',
            2 => 'gav.fecha_transaccion',
            3 => 'estatus_analisis',
        ];
        $orderIndex = (int) $request->input('order.0.column', 2);
        $orderColumn = $columns[$orderIndex] ?? 'gav.fecha_transaccion';
        $orderDir = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $estatusSql = $this->estatusVentaSql($umbrales, $momentoCalculo);
        $base = $this->baseUltimasVentasQuery($filtrosAgencia);
        $recordsTotal = (clone $base)->count();

        if ($search !== '') {
            $base->where(function ($query) use ($search) {
                $query->where('gav.tipo', 'like', "%{$search}%")
                    ->orWhere('gav.fecha_texto', 'like', "%{$search}%")
                    ->orWhere('gav.agencia', 'like', "%{$search}%")
                    ->orWhere('gav.terminal', 'like', "%{$search}%")
                    ->orWhere('gav.usuario_venta', 'like', "%{$search}%")
                    ->orWhere('agl.empresa', 'like', "%{$search}%")
                    ->orWhere('agl.ruta', 'like', "%{$search}%")
                    ->orWhere('agl.coordinador', 'like', "%{$search}%");
            });
        }

        if (in_array($estatusFiltro, ['Al dia', 'Aviso', 'En Alerta', 'Requiere llamada'], true)) {
            $base->whereRaw("{$estatusSql} = ?", [$estatusFiltro]);
        }

        $recordsFiltered = (clone $base)->count();
        $base->when($orderColumn === 'estatus_analisis',
            fn ($query) => $query->orderByRaw("{$estatusSql} {$orderDir}"),
            fn ($query) => $query->orderBy($orderColumn, $orderDir)
        );

        $data = $base
            ->offset($start)
            ->limit($length)
            ->get([
                'gav.tipo',
                'gav.fecha_texto',
                'gav.terminal',
                DB::raw("{$estatusSql} as estatus_analisis"),
            ])
            ->map(fn ($row) => [
                'tipo' => $row->tipo,
                'terminal' => $row->terminal,
                'fecha' => $row->fecha_texto,
                'estatus' => $row->estatus_analisis,
            ]);

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'estatusResumen' => $this->conteoEstatusTerminales($umbrales, $momentoCalculo, $filtrosAgencia),
            'estatusDetalle' => $this->detalleEstatusTerminales($umbrales, $momentoCalculo, $filtrosAgencia),
            'horaServidor' => $momentoCalculo->toIso8601String(),
            'data' => $data,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function pdf(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(180);

        $umbrales = $this->umbralesVenta($request);
        $filtrosAgencia = $this->filtrosAgencia($request);
        $resumen = $this->resumenDesdeTabla(null, null, null, $filtrosAgencia);
        $horaServidor = now();
        $estatusResumen = $this->conteoEstatusTerminales($umbrales, $horaServidor, $filtrosAgencia);
        $tendenciaVentasHora = $this->tendenciaVentasPorHoraDesdeTabla($filtrosAgencia);

        $pdf = Pdf::loadView('reportes.gestion-agencias-pdf', [
            'resumen' => $resumen,
            'umbrales' => $umbrales,
            'estatusResumen' => $estatusResumen,
            'tendenciaVentasHora' => $tendenciaVentasHora,
            'filtrosAgencia' => $filtrosAgencia,
            'horaServidor' => $horaServidor,
        ])
            ->setPaper('letter', 'landscape')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ]);

        return $pdf->download('reporte_gestion_agencias_' . $horaServidor->format('Ymd_His') . '.pdf');
    }

    private function reemplazarTablaReporte(callable $importador): void
    {
        DB::transaction(function () use ($importador) {
            DB::table('gestion_agencias_ventas')->delete();
            $importador();
        });
    }

    private function umbralesVenta(Request $request): array
    {
        $aviso = max((int) $request->input('umbral_aviso', 20), 1);
        $alerta = max((int) $request->input('umbral_alerta', 30), $aviso + 1);
        $llamada = max((int) $request->input('umbral_llamada', 60), $alerta + 1);

        return [
            'aviso' => $aviso,
            'alerta' => $alerta,
            'llamada' => $llamada,
        ];
    }

    private function filtrosAgencia(Request $request): array
    {
        return [
            'empresa' => trim((string) $request->input('empresa_filter', '')),
            'ruta' => trim((string) $request->input('ruta_filter', '')),
            'coordinador' => trim((string) $request->input('coordinador_filter', '')),
        ];
    }

    private function filtrosDisponiblesDesdeTabla(): array
    {
        $base = $this->baseUltimasVentasQuery();

        return [
            'empresas' => (clone $base)
                ->whereNotNull('agl.empresa')
                ->where('agl.empresa', '<>', '')
                ->distinct()
                ->orderBy('agl.empresa')
                ->pluck('agl.empresa')
                ->values()
                ->all(),
            'rutas' => (clone $base)
                ->whereNotNull('agl.ruta')
                ->where('agl.ruta', '<>', '')
                ->distinct()
                ->orderBy('agl.ruta')
                ->pluck('agl.ruta')
                ->values()
                ->all(),
            'coordinadores' => (clone $base)
                ->whereNotNull('agl.coordinador')
                ->where('agl.coordinador', '<>', '')
                ->distinct()
                ->orderBy('agl.coordinador')
                ->pluck('agl.coordinador')
                ->values()
                ->all(),
        ];
    }

    private function baseUltimasVentasQuery(array $filtros = [])
    {
        $ultimaVentaTerminalSubquery = DB::table('gestion_agencias_ventas')
            ->select('id', DB::raw('ROW_NUMBER() OVER (PARTITION BY terminal_clave ORDER BY fecha_transaccion DESC, id DESC) as venta_rank'))
            ->whereNotNull('terminal_clave')
            ->where('terminal_clave', '<>', '');

        $query = DB::table('gestion_agencias_ventas as gav')
            ->joinSub($ultimaVentaTerminalSubquery, 'uv', 'uv.id', '=', 'gav.id')
            ->leftJoinSub($this->agenciasLookupSubquery(), 'agl', 'agl.terminal_clave', '=', 'gav.terminal_clave')
            ->where('uv.venta_rank', 1);

        return $this->aplicarFiltrosAgencia($query, $filtros);
    }

    private function baseVentasConAgenciaQuery(array $filtros = [])
    {
        $query = DB::table('gestion_agencias_ventas as gav')
            ->leftJoinSub($this->agenciasLookupSubquery(), 'agl', 'agl.terminal_clave', '=', 'gav.terminal_clave');

        return $this->aplicarFiltrosAgencia($query, $filtros);
    }

    private function agenciasLookupSubquery()
    {
        $terminalClaveSql = $this->collateSql(
            $this->terminalClaveSql('a.terminal')
        );

        return DB::table('agencias as a')
            ->selectRaw("{$terminalClaveSql} as terminal_clave")
            ->selectRaw("MAX(NULLIF(TRIM(a.empresa), '')) as empresa")
            ->selectRaw("MAX(NULLIF(TRIM(a.ruta), '')) as ruta")
            ->selectRaw("MAX(NULLIF(TRIM(a.coordinador), '')) as coordinador")
            ->whereNotNull('a.terminal')
            ->where('a.terminal', '<>', '')
            ->groupByRaw($terminalClaveSql);
    }

    private function aplicarFiltrosAgencia($query, array $filtros = [])
    {
        $mapa = [
            'empresa' => 'agl.empresa',
            'ruta' => 'agl.ruta',
            'coordinador' => 'agl.coordinador',
        ];

        foreach ($mapa as $filtro => $columna) {
            $valor = trim((string) ($filtros[$filtro] ?? ''));

            if ($valor === '') {
                continue;
            }

            $query->where($columna, $valor);
        }

        return $query;
    }

    private function aplicarFiltrosAgenciaTablaAgencias($query, array $filtros = [])
    {
        $mapa = [
            'empresa' => 'empresa',
            'ruta' => 'ruta',
            'coordinador' => 'coordinador',
        ];

        foreach ($mapa as $filtro => $columna) {
            $valor = trim((string) ($filtros[$filtro] ?? ''));

            if ($valor === '') {
                continue;
            }

            $query->whereRaw("TRIM(COALESCE({$columna}, '')) = ?", [$valor]);
        }

        return $query;
    }

    private function terminalClaveSql(string $column): string
    {
        return "TRIM(LEADING '0' FROM REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$column}, ''), '-', ''), ' ', ''), '.', ''), ',', ''), '_', ''))";
    }

    private function collateSql(string $expression, string $collation = self::REPORT_TEXT_COLLATION): string
    {
        return "({$expression}) COLLATE {$collation}";
    }

    private function estatusVentaSql(array $umbrales, $momentoCalculo = null): string
    {
        $aviso = (int) $umbrales['aviso'];
        $alerta = (int) $umbrales['alerta'];
        $llamada = (int) $umbrales['llamada'];
        $momento = ($momentoCalculo instanceof Carbon ? $momentoCalculo : now())->format('Y-m-d H:i:s');

        return "CASE
            WHEN gav.fecha_transaccion IS NULL THEN 'Requiere llamada'
            WHEN TIMESTAMPDIFF(MINUTE, gav.fecha_transaccion, '{$momento}') >= {$llamada} THEN 'Requiere llamada'
            WHEN TIMESTAMPDIFF(MINUTE, gav.fecha_transaccion, '{$momento}') >= {$alerta} THEN 'En Alerta'
            WHEN TIMESTAMPDIFF(MINUTE, gav.fecha_transaccion, '{$momento}') >= {$aviso} THEN 'Aviso'
            ELSE 'Al dia'
        END";
    }

    private function conteoEstatusTerminales(?array $umbrales = null, $momentoCalculo = null, array $filtros = []): array
    {
        $umbrales ??= [
            'aviso' => 20,
            'alerta' => 30,
            'llamada' => 60,
        ];

        $estatusSql = $this->estatusVentaSql($umbrales, $momentoCalculo);
        $conteos = $this->baseUltimasVentasQuery($filtros)
            ->selectRaw("{$estatusSql} as estatus, COUNT(*) as total")
            ->groupByRaw($estatusSql)
            ->pluck('total', 'estatus');

        return [
            'Al dia' => (int) ($conteos['Al dia'] ?? 0),
            'Aviso' => (int) ($conteos['Aviso'] ?? 0),
            'En Alerta' => (int) ($conteos['En Alerta'] ?? 0),
            'Requiere llamada' => (int) ($conteos['Requiere llamada'] ?? 0),
        ];
    }

    private function detalleEstatusTerminales(?array $umbrales = null, $momentoCalculo = null, array $filtros = []): array
    {
        $umbrales ??= [
            'aviso' => 20,
            'alerta' => 30,
            'llamada' => 60,
        ];

        $estatusSql = $this->estatusVentaSql($umbrales, $momentoCalculo);
        $rows = $this->baseUltimasVentasQuery($filtros)
            ->orderBy('gav.fecha_transaccion', 'desc')
            ->get([
                'gav.tipo',
                'gav.fecha_texto',
                'gav.agencia',
                'gav.terminal',
                DB::raw("{$estatusSql} as estatus_analisis"),
            ]);

        $detalle = [
            'Al dia' => [],
            'Aviso' => [],
            'En Alerta' => [],
            'Requiere llamada' => [],
        ];

        foreach ($rows as $row) {
            $estatus = (string) $row->estatus_analisis;

            if (!array_key_exists($estatus, $detalle)) {
                continue;
            }

            $detalle[$estatus][] = [
                'agencia' => $row->agencia,
                'terminal' => $row->terminal,
                'tipo' => $row->tipo,
                'fecha' => $row->fecha_texto,
            ];
        }

        return $detalle;
    }

    private function resumenDesdeTabla(?int $totalCargadas = null, ?int $tradicionalValidas = null, ?int $noTradicionalValidas = null, array $filtros = []): ?array
    {
        $query = $this->baseVentasConAgenciaQuery($filtros);

        if (!(clone $query)->exists()) {
            return null;
        }

        $agenciasConVentas = (clone $query)
            ->whereNotNull('gav.terminal_clave')
            ->where('gav.terminal_clave', '<>', '')
            ->distinct('gav.terminal_clave')
            ->count('gav.terminal_clave');

        $agenciasSinVentas = $this->agenciasSinVentasDesdeTabla($filtros);
        $totalVendido = (float) (clone $query)->sum('gav.total_apostado');
        $horasConVentas = (clone $query)
            ->whereNotNull('gav.fecha_transaccion')
            ->selectRaw("COUNT(DISTINCT DATE_FORMAT(gav.fecha_transaccion, '%Y-%m-%d %H:00:00')) as total")
            ->value('total');
        $ventaPorHora = $horasConVentas > 0 ? $totalVendido / $horasConVentas : 0;

        return [
            'total_cargadas' => $totalCargadas ?? (clone $query)->count(),
            'total_validas' => $agenciasConVentas,
            'total_eliminadas' => $agenciasSinVentas->count(),
            'filas_validas' => (clone $query)->count(),
            'total_apostado' => $totalVendido,
            'venta_por_hora' => $ventaPorHora,
            'horas_con_ventas' => (int) $horasConVentas,
            'tradicional_validas' => $tradicionalValidas ?? (clone $query)->where('gav.tipo', 'Tradicional')->count(),
            'no_tradicional_validas' => $noTradicionalValidas ?? (clone $query)->where('gav.tipo', 'No Tradicional')->count(),
        ];
    }

    private function agenciasSinVentasDesdeTabla(array $filtros = [])
    {
        $clavesConVentas = $this->baseVentasConAgenciaQuery($filtros)
            ->whereNotNull('gav.terminal_clave')
            ->where('gav.terminal_clave', '<>', '')
            ->distinct()
            ->pluck('gav.terminal_clave')
            ->flip()
            ->all();

        return $this->agenciasActivasSinVentas($clavesConVentas, $filtros);
    }

    private function ventasPorAgenciaDesdeTabla()
    {
        $totales = DB::table('gestion_agencias_ventas')
            ->whereNotNull('terminal_clave')
            ->where('terminal_clave', '<>', '')
            ->selectRaw('terminal_clave')
            ->selectRaw('MAX(agencia) as agencia')
            ->selectRaw('MAX(terminal) as terminal')
            ->selectRaw('SUM(COALESCE(total_apostado, 0)) as total_general')
            ->selectRaw("SUM(CASE WHEN tipo = 'Tradicional' THEN COALESCE(total_apostado, 0) ELSE 0 END) as total_tradicional")
            ->selectRaw("SUM(CASE WHEN tipo = 'No Tradicional' THEN COALESCE(total_apostado, 0) ELSE 0 END) as total_no_tradicional")
            ->groupBy('terminal_clave')
            ->orderBy('terminal')
            ->orderBy('agencia')
            ->get();

        $ultimasVentasSubquery = DB::table('gestion_agencias_ventas')
            ->whereNotNull('terminal_clave')
            ->where('terminal_clave', '<>', '')
            ->select([
                'terminal_clave',
                'tipo',
                'fecha_transaccion',
                'fecha_texto',
                'total_apostado',
            ])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY terminal_clave, tipo ORDER BY fecha_transaccion DESC, id DESC) as venta_rank');

        $ultimasVentas = DB::query()
            ->fromSub($ultimasVentasSubquery, 'uv')
            ->where('venta_rank', 1)
            ->get()
            ->groupBy('terminal_clave')
            ->map(fn ($items) => $items->keyBy('tipo'));

        return $totales
            ->map(function ($row) use ($ultimasVentas) {
                $terminalClave = (string) ($row->terminal_clave ?? '');
                $terminal = (string) ($row->terminal ?? '');
                $agencia = (string) ($row->agencia ?? '');
                $ultimas = $ultimasVentas->get($terminalClave, collect());
                $tradicional = $ultimas->get('Tradicional');
                $noTradicional = $ultimas->get('No Tradicional');

                return [
                    'agencia' => $agencia,
                    'terminal' => $terminal,
                    'label' => trim($terminal . ' - ' . $agencia, ' -'),
                    'busqueda' => strtolower(trim($terminal . ' ' . $agencia)),
                    'tradicional' => [
                        'total' => round((float) ($row->total_tradicional ?? 0), 2),
                        'ultima' => $this->ultimaTransaccionAgrupada($tradicional),
                    ],
                    'no_tradicional' => [
                        'total' => round((float) ($row->total_no_tradicional ?? 0), 2),
                        'ultima' => $this->ultimaTransaccionAgrupada($noTradicional),
                    ],
                    'total' => round((float) ($row->total_general ?? 0), 2),
                ];
            })
            ->values();
    }

    private function tendenciaVentasPorHoraDesdeTabla(array $filtros = []): array
    {
        $rango = $this->baseVentasConAgenciaQuery($filtros)
            ->whereNotNull('gav.fecha_transaccion')
            ->selectRaw('MIN(gav.fecha_transaccion) as primera_venta, MAX(gav.fecha_transaccion) as ultima_venta')
            ->first();

        if (!$rango || !$rango->primera_venta || !$rango->ultima_venta) {
            return [
                'labels' => [],
                'series' => [],
                'primera_venta' => null,
                'ultima_venta' => null,
                'total' => 0,
            ];
        }

        $inicio = Carbon::parse($rango->primera_venta)->startOfHour();
        $fin = Carbon::parse($rango->ultima_venta)->startOfHour();

        $ventasPorHora = $this->baseVentasConAgenciaQuery($filtros)
            ->whereNotNull('gav.fecha_transaccion')
            ->where('gav.fecha_transaccion', '>=', $inicio->toDateTimeString())
            ->where('gav.fecha_transaccion', '<', $fin->copy()->addHour()->toDateTimeString())
            ->selectRaw("DATE_FORMAT(gav.fecha_transaccion, '%Y-%m-%d %H:00:00') as hora")
            ->selectRaw('SUM(COALESCE(gav.total_apostado, 0)) as total_ventas')
            ->groupByRaw("DATE_FORMAT(gav.fecha_transaccion, '%Y-%m-%d %H:00:00')")
            ->pluck('total_ventas', 'hora')
            ->map(fn ($total) => (float) $total)
            ->all();

        $labels = [];
        $series = [];
        $acumulado = 0.0;
        $cursor = $inicio->copy();
        $usarFechaEnLabel = !$inicio->isSameDay($fin);

        while ($cursor->lessThanOrEqualTo($fin)) {
            $key = $cursor->format('Y-m-d H:00:00');
            $acumulado += (float) ($ventasPorHora[$key] ?? 0);
            $labels[] = $cursor->format($usarFechaEnLabel ? 'd/m g A' : 'g A');
            $series[] = round($acumulado, 2);
            $cursor->addHour();
        }

        return [
            'labels' => $labels,
            'series' => $series,
            'primera_venta' => Carbon::parse($rango->primera_venta)->format('d-m-Y g:i A'),
            'ultima_venta' => Carbon::parse($rango->ultima_venta)->format('d-m-Y g:i A'),
            'total' => round($acumulado, 2),
        ];
    }

    private function ultimaTransaccionAgrupada($fila): ?array
    {
        if (!$fila) {
            return null;
        }

        return [
            'hora' => $this->horaTransaccion((string) ($fila->fecha_transaccion ?? ''), (string) ($fila->fecha_texto ?? '')),
            'monto' => round((float) ($fila->total_apostado ?? 0), 2),
        ];
    }

    private function fechaSql($valor): ?string
    {
        $valor = $this->limpiarTexto($valor);

        if ($valor === '') {
            return null;
        }

        try {
            return Carbon::parse($valor)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function limpiarArchivo(UploadedFile $archivo, string $tipo, array $columnasRequeridas): void
    {
        $path = $archivo->getRealPath();

        if (!$path) {
            throw ValidationException::withMessages([
                $this->campoArchivo($tipo) => 'No se pudo leer el archivo ' . $tipo . '.',
            ]);
        }

        if ($this->esCsv($archivo)) {
            $this->limpiarCsv($archivo, $tipo, $columnasRequeridas);
            return;
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages([
                $this->campoArchivo($tipo) => 'No se pudo abrir el archivo ' . $tipo . '.',
            ]);
        }

        try {
            $sheetPath = $this->obtenerPrimeraHoja($zip);
            $sharedStrings = $this->leerSharedStrings($zip);
            $sheetXml = $zip->getFromName($sheetPath);

            if ($sheetXml === false) {
                throw ValidationException::withMessages([
                    $this->campoArchivo($tipo) => 'No se pudo leer la hoja principal del archivo ' . $tipo . '.',
                ]);
            }

            $headers = $this->leerPrimeraFila($sheetXml, $sharedStrings);
            $mapa = $this->mapearColumnas($headers, $columnasRequeridas, $tipo);
            $columnasPermitidas = array_flip(array_map(fn ($column) => $this->indiceColumna($column), array_values($mapa)));
            $filas = [];

            foreach ($this->iterarFilas($sheetXml) as $rowNumber => $rowXml) {
                if ($rowNumber <= 1) {
                    continue;
                }

                $row = $this->parsearFilaXml($rowXml, $sharedStrings, $columnasPermitidas);
                $this->agregarFilaProcesada(
                    $this->normalizarFilaReporte($row, $mapa, $tipo),
                    $filas
                );
            }

            $this->insertarFilasReporte($filas);
        } finally {
            $zip->close();
        }
    }

    private function limpiarCsv(UploadedFile $archivo, string $tipo, array $columnasRequeridas): void
    {
        $path = $archivo->getRealPath();

        if (!$path) {
            throw ValidationException::withMessages([
                $this->campoArchivo($tipo) => 'No se pudo leer el archivo ' . $tipo . '.',
            ]);
        }

        $delimiter = $this->detectarSeparador($path);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                $this->campoArchivo($tipo) => 'No se pudo abrir el archivo ' . $tipo . '.',
            ]);
        }

        try {
            $primeraLinea = fgets($handle);
            $headers = $primeraLinea !== false ? str_getcsv($primeraLinea, $delimiter) : false;

            if (!$headers) {
                throw ValidationException::withMessages([
                    $this->campoArchivo($tipo) => 'El archivo ' . $tipo . ' no contiene encabezados validos.',
                ]);
            }

            [$headers, $primeraFila] = $this->normalizarPrimeraLineaCsv($headers, $tipo);
            $mapa = $this->mapearColumnasCsv($headers, $columnasRequeridas, $tipo);
            $filas = [];

            if ($primeraFila !== null) {
                $this->agregarFilaCsv($primeraFila, $mapa, $tipo, $filas);
            }

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $this->agregarFilaCsv($row, $mapa, $tipo, $filas);
            }

            $this->insertarFilasReporte($filas);
        } finally {
            fclose($handle);
        }
    }

    private function agregarFilaCsv(array $row, array $mapa, string $tipo, array &$filas): void
    {
        if ($this->filaVacia($row)) {
            return;
        }

        $this->agregarFilaProcesada(
            $this->normalizarFilaReporte($row, $mapa, $tipo),
            $filas
        );
    }

    private function normalizarFilaReporte(array $row, array $mapa, string $tipo): ?array
    {
        $estatus = $this->limpiarTexto($row[$mapa['estatus']] ?? '');

        if ($estatus === '' || strcasecmp($estatus, 'Validos') !== 0) {
            return null;
        }

        $fecha = $this->normalizarFecha($row[$mapa['fecha']] ?? '');
        $agencia = $this->limpiarTexto($row[$mapa['agencia']] ?? '');
        $terminal = $this->formatearTerminalConsulta($row[$mapa['terminal']] ?? '');
        $usuarioVenta = $this->limpiarTexto($row[$mapa['usuario_venta']] ?? '');
        $totalApostado = $this->limpiarMonto($row[$mapa['total_apostado']] ?? 0);

        if ($agencia === '' && $terminal === '' && $usuarioVenta === '' && $totalApostado == 0.0) {
            return null;
        }

        return [
            'tipo' => $tipo,
            'fecha_transaccion' => $this->fechaSql($fecha['orden'] ?? null),
            'fecha_texto' => $fecha['texto'],
            'agencia' => $agencia,
            'terminal' => $terminal,
            'terminal_clave' => $this->claveAgenciaReporte($terminal, $agencia),
            'usuario_venta' => $usuarioVenta,
            'total_apostado' => $totalApostado,
            'estatus' => 'Validos',
        ];
    }

    private function agregarFilaProcesada(?array $fila, array &$filas): void
    {
        if ($fila === null) {
            return;
        }

        $filas[] = $fila;

        if (count($filas) >= self::IMPORT_CHUNK_SIZE) {
            $this->insertarFilasReporte($filas);
            $filas = [];
        }
    }

    private function insertarFilasReporte(array $filas): void
    {
        if ($filas === []) {
            return;
        }

        $now = now();
        $rows = array_map(function ($fila) use ($now) {
            $fila['created_at'] = $now;
            $fila['updated_at'] = $now;

            return $fila;
        }, $filas);

        DB::table('gestion_agencias_ventas')->insert($rows);
    }

    private function formatearTerminalConsulta($valor): string
    {
        $terminal = $this->limpiarTexto($valor);

        if ($terminal === '' || str_starts_with($terminal, '0')) {
            return $terminal;
        }

        return '0' . $terminal;
    }

    private function claveAgenciaReporte(string $terminal, string $agencia): string
    {
        $terminal = preg_replace('/\D+/', '', $terminal) ?? '';
        $terminal = ltrim($terminal, '0');

        if ($terminal !== '') {
            return $terminal;
        }

        return $this->normalizarEncabezado($agencia);
    }

    private function agenciasActivasSinVentas(array $clavesConVentas, array $filtros = [])
    {
        $query = Agencia::query()
            ->where('estatus', 1)
            ->whereNotNull('terminal');

        $this->aplicarFiltrosAgenciaTablaAgencias($query, $filtros);

        return $query
            ->get(['agencia', 'nombre_agencia', 'terminal'])
            ->map(function (Agencia $agencia) {
                $terminal = $this->formatearTerminalConsulta($agencia->terminal);

                return [
                    'agencia_id' => $agencia->agencia,
                    'nombre_agencia' => $agencia->nombre_agencia,
                    'terminal' => $terminal,
                    'clave' => $this->claveAgenciaReporte($terminal, (string) ($agencia->nombre_agencia ?? $agencia->agencia ?? '')),
                ];
            })
            ->filter(fn ($agencia) => ($agencia['clave'] ?? '') !== '')
            ->unique('clave')
            ->reject(fn ($agencia) => isset($clavesConVentas[$agencia['clave']]))
            ->map(fn ($agencia) => [
                'agencia_id' => $agencia['agencia_id'],
                'nombre_agencia' => $agencia['nombre_agencia'],
                'terminal' => $agencia['terminal'],
            ])
            ->values();
    }

    private function ventasPorAgencia($filas)
    {
        return $filas
            ->groupBy(fn ($fila) => $this->claveAgenciaReporte((string) ($fila['terminal'] ?? ''), (string) ($fila['agencia'] ?? '')))
            ->map(function ($items) {
                $primera = $items->first();
                $tradicional = $items->where('tipo', 'Tradicional');
                $noTradicional = $items->where('tipo', 'No Tradicional');
                $ultimaTradicional = $this->ultimaTransaccion($tradicional);
                $ultimaNoTradicional = $this->ultimaTransaccion($noTradicional);
                $terminal = (string) ($primera['terminal'] ?? '');
                $agencia = (string) ($primera['agencia'] ?? '');

                return [
                    'agencia' => $agencia,
                    'terminal' => $terminal,
                    'label' => trim($terminal . ' - ' . $agencia, ' -'),
                    'busqueda' => strtolower(trim($terminal . ' ' . $agencia)),
                    'tradicional' => [
                        'total' => round((float) $tradicional->sum('total_apostado'), 2),
                        'ultima' => $ultimaTradicional,
                    ],
                    'no_tradicional' => [
                        'total' => round((float) $noTradicional->sum('total_apostado'), 2),
                        'ultima' => $ultimaNoTradicional,
                    ],
                    'total' => round((float) $items->sum('total_apostado'), 2),
                ];
            })
            ->sortBy('label')
            ->values();
    }

    private function ultimaTransaccion($filas): ?array
    {
        $fila = $filas
            ->sortByDesc('fecha_orden')
            ->first();

        if (!$fila) {
            return null;
        }

        return [
            'hora' => $this->horaTransaccion((string) ($fila['fecha_orden'] ?? ''), (string) ($fila['fecha'] ?? '')),
            'monto' => round((float) ($fila['total_apostado'] ?? 0), 2),
        ];
    }

    private function horaTransaccion(string $fechaOrden, string $fechaTexto): string
    {
        foreach ([$fechaOrden, $fechaTexto] as $fecha) {
            if ($fecha === '') {
                continue;
            }

            try {
                return Carbon::parse($fecha)->format('g:i A');
            } catch (\Throwable) {
                continue;
            }
        }

        return 'N/D';
    }

    private function normalizarPrimeraLineaCsv(array $headers, string $tipo): array
    {
        $encabezadosEsperados = $this->encabezadosEsperadosCsv($tipo);
        $totalEsperados = count($encabezadosEsperados);

        if ($totalEsperados === 0 || count($headers) <= $totalEsperados) {
            return [$headers, null];
        }

        $ultimoEncabezado = $encabezadosEsperados[$totalEsperados - 1];
        $campoPegado = (string) ($headers[$totalEsperados - 1] ?? '');

        if (!$this->primeraLineaTieneRegistroPegado($headers, $encabezadosEsperados, $campoPegado, $ultimoEncabezado)) {
            return [$headers, null];
        }

        $primerValor = substr($campoPegado, strlen($ultimoEncabezado));
        $primeraFila = array_merge([$primerValor], array_slice($headers, $totalEsperados));

        return [$encabezadosEsperados, $primeraFila];
    }

    private function primeraLineaTieneRegistroPegado(array $headers, array $encabezadosEsperados, string $campoPegado, string $ultimoEncabezado): bool
    {
        foreach (array_slice($encabezadosEsperados, 0, -1) as $index => $encabezado) {
            if ($this->normalizarEncabezado((string) ($headers[$index] ?? '')) !== $this->normalizarEncabezado($encabezado)) {
                return false;
            }
        }

        return str_starts_with($campoPegado, $ultimoEncabezado) && strlen($campoPegado) > strlen($ultimoEncabezado);
    }

    private function encabezadosEsperadosCsv(string $tipo): array
    {
        if ($tipo === 'Tradicional') {
            return [
                'Fecha',
                'No. Ticket',
                'Grupo',
                'Agencia',
                'No. Agencia',
                'Total Apostado',
                'Total Ganado',
                'Usr. Anulo',
                'Fec. Anulo',
                'Usr. Pago',
                'Fec. Pago',
                'Estatus',
                'Orden',
                'Empresa',
                'Serial No.',
                'Terminal',
                'Empresa Pago',
                'Usr. Venta',
            ];
        }

        return [
            'Ticket',
            'Consorcio',
            'Grupo',
            'Agencia',
            'Estatus',
            'Fecha',
            'Id Terminal',
            'Serial',
            'Usr. Venta',
            'Total Apostado',
            'Total Ganado',
            'Usr. Pago',
            'Fec. Pago',
            'Empresa Pago',
            'Usr. Anulo',
            'Fec. Anulado',
        ];
    }

    private function obtenerPrimeraHoja(ZipArchive $zip): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relsXml === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook = simplexml_load_string($workbookXml);
        $rels = simplexml_load_string($relsXml);

        if (!$workbook || !$rels) {
            return 'xl/worksheets/sheet1.xml';
        }

        $sheets = $workbook->sheets->sheet ?? [];
        $firstSheet = $sheets[0] ?? null;

        if (!$firstSheet) {
            return 'xl/worksheets/sheet1.xml';
        }

        $attributes = $firstSheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $relationshipId = (string) ($attributes['id'] ?? '');

        foreach ($rels->Relationship as $relationship) {
            if ((string) $relationship['Id'] !== $relationshipId) {
                continue;
            }

            $target = (string) $relationship['Target'];
            $target = str_replace('\\', '/', $target);

            if (str_starts_with($target, '/')) {
                return ltrim($target, '/');
            }

            return 'xl/' . ltrim($target, '/');
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private function leerSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $sharedStrings = [];
        $offset = 0;

        while (preg_match('/<si\b[^>]*>.*?<\/si>/s', $xml, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $sharedStrings[] = $this->extraerTextos($match[0][0]);
            $offset = $match[0][1] + strlen($match[0][0]);
        }

        return $sharedStrings;
    }

    private function leerPrimeraFila(string $sheetXml, array $sharedStrings): array
    {
        foreach ($this->iterarFilas($sheetXml) as $rowXml) {
            return $this->parsearFilaXml($rowXml, $sharedStrings);
        }

        return [];
    }

    private function parsearFilaXml(string $rowXml, array $sharedStrings, ?array $columnasPermitidas = null): array
    {
        $row = [];

        if (!preg_match_all('/<c\b([^>]*)>(.*?)<\/c>/s', $rowXml, $matches, PREG_SET_ORDER)) {
            return $row;
        }

        foreach ($matches as $match) {
            $attributes = $match[1];
            $content = $match[2];
            $reference = $this->atributoXml($attributes, 'r');

            if (!preg_match('/^([A-Z]+)/', $reference, $referenceMatch)) {
                continue;
            }

            $column = $referenceMatch[1];
            $columnIndex = $this->indiceColumna($column);

            if ($columnasPermitidas !== null && !isset($columnasPermitidas[$columnIndex])) {
                continue;
            }

            $row[$column] = $this->valorCelda($attributes, $content, $sharedStrings);
        }

        return $row;
    }

    private function valorCelda(string $attributes, string $content, array $sharedStrings): string
    {
        $type = $this->atributoXml($attributes, 't');
        $value = preg_match('/<v>(.*?)<\/v>/s', $content, $match)
            ? html_entity_decode($match[1], ENT_QUOTES | ENT_XML1, 'UTF-8')
            : '';

        if ($type === 's') {
            $index = (int) $value;

            return (string) ($sharedStrings[$index] ?? '');
        }

        if ($type === 'inlineStr') {
            return $this->extraerTextos($content);
        }

        return $value;
    }

    private function iterarFilas(string $sheetXml): \Generator
    {
        $offset = 0;

        while (preg_match('/<row\b[^>]*r="(\d+)"[^>]*>.*?<\/row>/s', $sheetXml, $match, PREG_OFFSET_CAPTURE, $offset)) {
            yield (int) $match[1][0] => $match[0][0];
            $offset = $match[0][1] + strlen($match[0][0]);
        }
    }

    private function atributoXml(string $attributes, string $name): string
    {
        return preg_match('/\b' . preg_quote($name, '/') . '="([^"]*)"/', $attributes, $match)
            ? html_entity_decode($match[1], ENT_QUOTES | ENT_XML1, 'UTF-8')
            : '';
    }

    private function indiceColumna(string $column): int
    {
        $index = 0;

        foreach (str_split($column) as $char) {
            $index = ($index * 26) + (ord($char) - 64);
        }

        return $index;
    }

    private function extraerTextos(string $xml): string
    {
        if (!preg_match_all('/<t(?:\s[^>]*)?>(.*?)<\/t>/s', $xml, $matches)) {
            return '';
        }

        return implode('', array_map(
            fn ($text) => html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            $matches[1]
        ));
    }

    private function mapearColumnas(array $headers, array $columnasRequeridas, string $tipo): array
    {
        $normalizados = collect($headers)
            ->mapWithKeys(fn ($header, $column) => [$this->normalizarEncabezado((string) $header) => $column]);

        $mapa = [];
        $faltantes = [];

        foreach ($columnasRequeridas as $clave => $nombre) {
            $columna = $normalizados->get($this->normalizarEncabezado($nombre));

            if ($columna === null) {
                $faltantes[] = $nombre;
                continue;
            }

            $mapa[$clave] = $columna;
        }

        if ($faltantes) {
            throw ValidationException::withMessages([
                $this->campoArchivo($tipo) => 'En ' . $tipo . ' faltan estas columnas: ' . implode(', ', $faltantes) . '.',
            ]);
        }

        return $mapa;
    }

    private function mapearColumnasCsv(array $headers, array $columnasRequeridas, string $tipo): array
    {
        $normalizados = collect($headers)
            ->mapWithKeys(fn ($header, $index) => [$this->normalizarEncabezado((string) $header) => $index]);

        $mapa = [];
        $faltantes = [];

        foreach ($columnasRequeridas as $clave => $nombre) {
            $columna = $normalizados->get($this->normalizarEncabezado($nombre));

            if ($columna === null) {
                $faltantes[] = $nombre;
                continue;
            }

            $mapa[$clave] = $columna;
        }

        if ($faltantes) {
            throw ValidationException::withMessages([
                $this->campoArchivo($tipo) => 'En ' . $tipo . ' faltan estas columnas: ' . implode(', ', $faltantes) . '.',
            ]);
        }

        return $mapa;
    }

    private function normalizarFecha($valor): array
    {
        if (is_numeric($valor)) {
            $fecha = ExcelDate::excelToDateTimeObject((float) $valor);

            return [
                'texto' => $fecha->format('d-m-Y h:i:s A'),
                'orden' => $fecha->format('Y-m-d H:i:s'),
            ];
        }

        if ($valor instanceof DateTimeInterface) {
            return [
                'texto' => $valor->format('d-m-Y h:i:s A'),
                'orden' => $valor->format('Y-m-d H:i:s'),
            ];
        }

        $texto = $this->limpiarTexto($valor);
        $formatos = ['d-m-Y h:i:s A', 'd-m-Y H:i:s', 'd/m/Y h:i:s A', 'd/m/Y H:i:s', 'Y-m-d H:i:s'];

        foreach ($formatos as $formato) {
            try {
                $fecha = Carbon::createFromFormat($formato, $texto);

                return [
                    'texto' => $fecha->format('d-m-Y h:i:s A'),
                    'orden' => $fecha->format('Y-m-d H:i:s'),
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        return [
            'texto' => $texto,
            'orden' => $texto,
        ];
    }

    private function normalizarEncabezado(string $valor): string
    {
        $valor = preg_replace('/^\xEF\xBB\xBF/', '', $valor) ?? $valor;
        $valor = strtolower(trim($valor));
        $valor = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor) ?: $valor;

        return preg_replace('/[^a-z0-9]+/', '', $valor) ?? $valor;
    }

    private function limpiarTexto($valor): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $valor) ?? '');
    }

    private function limpiarMonto($valor): float
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return 0.0;
        }

        $negativo = str_starts_with($valor, '(') && str_ends_with($valor, ')');
        $valor = str_replace(['(', ')', 'RD$', '$', ' '], '', $valor);
        $valor = str_replace(',', '', $valor);
        $numero = (float) $valor;

        return $negativo ? -1 * $numero : $numero;
    }

    private function detectarSeparador(string $path): string
    {
        $linea = '';
        $handle = fopen($path, 'rb');

        if ($handle !== false) {
            $linea = (string) fgets($handle);
            fclose($handle);
        }

        $separadores = [',' => 0, ';' => 0, "\t" => 0, '|' => 0];

        foreach (array_keys($separadores) as $separador) {
            $separadores[$separador] = count(str_getcsv($linea, $separador));
        }

        arsort($separadores);

        return (string) array_key_first($separadores);
    }

    private function filaVacia(array $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) $value) === '');
    }

    private function esCsv(UploadedFile $archivo): bool
    {
        return in_array(strtolower($archivo->getClientOriginalExtension()), ['csv', 'txt'], true);
    }

    private function campoArchivo(string $tipo): string
    {
        return $tipo === 'Tradicional' ? 'tradicional' : 'no_tradicional';
    }
}
