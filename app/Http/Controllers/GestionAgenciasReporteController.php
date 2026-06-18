<?php

namespace App\Http\Controllers;

use App\Models\Agencia;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ZipArchive;

class GestionAgenciasReporteController extends Controller
{
    public function index(): View
    {
        return view('reportes.gestion-agencias', [
            'filas' => collect(),
            'resumen' => $this->resumenDesdeTabla(),
            'archivos' => [],
            'agenciasSinVentas' => $this->agenciasSinVentasDesdeTabla(),
            'ventasPorAgencia' => $this->ventasPorAgenciaDesdeTabla(),
        ]);
    }

    public function procesar(Request $request): View
    {
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

        $resultadoTradicional = $this->limpiarArchivo($tradicional, 'Tradicional', [
            'fecha' => 'Fecha',
            'agencia' => 'Agencia',
            'total_apostado' => 'Total Apostado',
            'estatus' => 'Estatus',
            'terminal' => 'Terminal',
            'usuario_venta' => 'Usr. Venta',
        ]);

        $resultadoNoTradicional = $this->limpiarArchivo($noTradicional, 'No Tradicional', [
            'agencia' => 'Agencia',
            'estatus' => 'Estatus',
            'fecha' => 'Fecha',
            'terminal' => 'Id Terminal',
            'usuario_venta' => 'Usr. Venta',
            'total_apostado' => 'Total Apostado',
        ]);

        $filas = $resultadoTradicional['filas']->merge($resultadoNoTradicional['filas'])->values();

        $this->reemplazarTablaReporte($filas);

        $resumen = $this->resumenDesdeTabla(
            $resultadoTradicional['total_cargadas'] + $resultadoNoTradicional['total_cargadas'],
            $resultadoTradicional['validas'],
            $resultadoNoTradicional['validas']
        );
        $agenciasSinVentas = $this->agenciasSinVentasDesdeTabla();
        $ventasPorAgencia = $this->ventasPorAgenciaDesdeTabla();

        return view('reportes.gestion-agencias', [
            'filas' => collect(),
            'resumen' => $resumen,
            'agenciasSinVentas' => $agenciasSinVentas,
            'ventasPorAgencia' => $ventasPorAgencia,
            'archivos' => [
                'tradicional' => $tradicional->getClientOriginalName(),
                'no_tradicional' => $noTradicional->getClientOriginalName(),
            ],
        ]);
    }

    public function data(Request $request)
    {
        $columns = [
            0 => 'tipo',
            1 => 'fecha_transaccion',
            2 => 'agencia',
            3 => 'terminal',
            4 => 'usuario_venta',
            5 => 'estatus',
            6 => 'total_apostado',
        ];

        $draw = (int) $request->input('draw', 1);
        $start = max((int) $request->input('start', 0), 0);
        $length = (int) $request->input('length', 25);
        $length = $length > 0 ? min($length, 500) : 25;
        $search = trim((string) $request->input('search.value', ''));
        $orderIndex = (int) $request->input('order.0.column', 1);
        $orderColumn = $columns[$orderIndex] ?? 'fecha_transaccion';
        $orderDir = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $base = DB::table('gestion_agencias_ventas');
        $recordsTotal = (clone $base)->count();

        if ($search !== '') {
            $base->where(function ($query) use ($search) {
                $query->where('tipo', 'like', "%{$search}%")
                    ->orWhere('fecha_texto', 'like', "%{$search}%")
                    ->orWhere('agencia', 'like', "%{$search}%")
                    ->orWhere('terminal', 'like', "%{$search}%")
                    ->orWhere('usuario_venta', 'like', "%{$search}%")
                    ->orWhere('estatus', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $base)->count();
        $data = $base
            ->orderBy($orderColumn, $orderDir)
            ->offset($start)
            ->limit($length)
            ->get()
            ->map(fn ($row) => [
                'tipo' => $row->tipo,
                'fecha' => $row->fecha_texto,
                'agencia' => $row->agencia,
                'terminal' => $row->terminal,
                'usuario_venta' => $row->usuario_venta,
                'estatus' => $row->estatus,
                'total_apostado' => number_format((float) $row->total_apostado, 2),
            ]);

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    private function reemplazarTablaReporte($filas): void
    {
        $now = now();
        $rows = $filas->map(function ($fila) use ($now) {
            return [
                'tipo' => $fila['tipo'],
                'fecha_transaccion' => $this->fechaSql($fila['fecha_orden'] ?? null),
                'fecha_texto' => $fila['fecha'],
                'agencia' => $fila['agencia'],
                'terminal' => $fila['terminal'],
                'terminal_clave' => $this->claveAgenciaReporte((string) ($fila['terminal'] ?? ''), (string) ($fila['agencia'] ?? '')),
                'usuario_venta' => $fila['usuario_venta'],
                'total_apostado' => $fila['total_apostado'],
                'estatus' => $fila['estatus'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        });

        DB::transaction(function () use ($rows) {
            DB::table('gestion_agencias_ventas')->delete();

            $rows->chunk(1000)->each(function ($chunk) {
                DB::table('gestion_agencias_ventas')->insert($chunk->all());
            });
        });
    }

    private function resumenDesdeTabla(?int $totalCargadas = null, ?int $tradicionalValidas = null, ?int $noTradicionalValidas = null): ?array
    {
        $query = DB::table('gestion_agencias_ventas');

        if (!(clone $query)->exists()) {
            return null;
        }

        $agenciasConVentas = (clone $query)
            ->whereNotNull('terminal_clave')
            ->where('terminal_clave', '<>', '')
            ->distinct('terminal_clave')
            ->count('terminal_clave');

        $agenciasSinVentas = $this->agenciasSinVentasDesdeTabla();

        return [
            'total_cargadas' => $totalCargadas ?? (clone $query)->count(),
            'total_validas' => $agenciasConVentas,
            'total_eliminadas' => $agenciasSinVentas->count(),
            'filas_validas' => (clone $query)->count(),
            'total_apostado' => (float) (clone $query)->sum('total_apostado'),
            'tradicional_validas' => $tradicionalValidas ?? (clone $query)->where('tipo', 'Tradicional')->count(),
            'no_tradicional_validas' => $noTradicionalValidas ?? (clone $query)->where('tipo', 'No Tradicional')->count(),
        ];
    }

    private function agenciasSinVentasDesdeTabla()
    {
        $clavesConVentas = DB::table('gestion_agencias_ventas')
            ->whereNotNull('terminal_clave')
            ->where('terminal_clave', '<>', '')
            ->distinct()
            ->pluck('terminal_clave')
            ->flip()
            ->all();

        return $this->agenciasActivasSinVentas($clavesConVentas);
    }

    private function ventasPorAgenciaDesdeTabla()
    {
        $rows = DB::table('gestion_agencias_ventas')
            ->orderBy('terminal')
            ->orderBy('agencia')
            ->get(['tipo', 'fecha_transaccion', 'fecha_texto', 'agencia', 'terminal', 'terminal_clave', 'total_apostado']);

        return $rows
            ->groupBy('terminal_clave')
            ->map(function ($items) {
                $primera = $items->first();
                $tradicional = $items->where('tipo', 'Tradicional');
                $noTradicional = $items->where('tipo', 'No Tradicional');
                $terminal = (string) ($primera->terminal ?? '');
                $agencia = (string) ($primera->agencia ?? '');

                return [
                    'agencia' => $agencia,
                    'terminal' => $terminal,
                    'label' => trim($terminal . ' - ' . $agencia, ' -'),
                    'busqueda' => strtolower(trim($terminal . ' ' . $agencia)),
                    'tradicional' => [
                        'total' => round((float) $tradicional->sum('total_apostado'), 2),
                        'ultima' => $this->ultimaTransaccionDb($tradicional),
                    ],
                    'no_tradicional' => [
                        'total' => round((float) $noTradicional->sum('total_apostado'), 2),
                        'ultima' => $this->ultimaTransaccionDb($noTradicional),
                    ],
                    'total' => round((float) $items->sum('total_apostado'), 2),
                ];
            })
            ->sortBy('label')
            ->values();
    }

    private function ultimaTransaccionDb($filas): ?array
    {
        $fila = $filas
            ->sortByDesc('fecha_transaccion')
            ->first();

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

    private function limpiarArchivo(UploadedFile $archivo, string $tipo, array $columnasRequeridas): array
    {
        $path = $archivo->getRealPath();

        if (!$path) {
            throw ValidationException::withMessages([
                $this->campoArchivo($tipo) => 'No se pudo leer el archivo ' . $tipo . '.',
            ]);
        }

        if ($this->esCsv($archivo)) {
            return $this->limpiarCsv($archivo, $tipo, $columnasRequeridas);
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages([
                $this->campoArchivo($tipo) => 'No se pudo abrir el archivo ' . $tipo . '.',
            ]);
        }

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
        $filas = collect();
        $totalCargadas = 0;
        $eliminadas = 0;
        foreach ($this->iterarFilas($sheetXml) as $rowNumber => $rowXml) {
            if ($rowNumber <= 1) {
                continue;
            }

            $totalCargadas++;
            $row = $this->parsearFilaXml($rowXml, $sharedStrings, $columnasPermitidas);
            $estatus = $this->limpiarTexto($row[$mapa['estatus']] ?? '');

            if ($estatus === '') {
                continue;
            }

            if (strcasecmp($estatus, 'Validos') !== 0) {
                $eliminadas++;
                continue;
            }

            $fecha = $this->normalizarFecha($row[$mapa['fecha']] ?? '');
            $agencia = $this->limpiarTexto($row[$mapa['agencia']] ?? '');
            $terminal = $this->formatearTerminalConsulta($row[$mapa['terminal']] ?? '');
            $usuarioVenta = $this->limpiarTexto($row[$mapa['usuario_venta']] ?? '');
            $totalApostado = $this->limpiarMonto($row[$mapa['total_apostado']] ?? 0);

            if ($agencia === '' && $terminal === '' && $usuarioVenta === '' && $totalApostado == 0.0) {
                continue;
            }

            $filas->push([
                'tipo' => $tipo,
                'fecha' => $fecha['texto'],
                'fecha_orden' => $fecha['orden'],
                'agencia' => $agencia,
                'terminal' => $terminal,
                'usuario_venta' => $usuarioVenta,
                'total_apostado' => $totalApostado,
                'estatus' => 'Validos',
            ]);
        }

        $zip->close();

        return [
            'filas' => $filas,
            'total_cargadas' => $totalCargadas,
            'validas' => $filas->count(),
            'total_eliminadas' => $eliminadas,
        ];
    }

    private function limpiarCsv(UploadedFile $archivo, string $tipo, array $columnasRequeridas): array
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

        $primeraLinea = fgets($handle);
        $headers = $primeraLinea !== false ? str_getcsv($primeraLinea, $delimiter) : false;

        if (!$headers) {
            fclose($handle);
            throw ValidationException::withMessages([
                $this->campoArchivo($tipo) => 'El archivo ' . $tipo . ' no contiene encabezados validos.',
            ]);
        }

        [$headers, $primeraFila] = $this->normalizarPrimeraLineaCsv($headers, $tipo);
        $mapa = $this->mapearColumnasCsv($headers, $columnasRequeridas, $tipo);
        $filas = collect();
        $totalCargadas = 0;
        $eliminadas = 0;

        if ($primeraFila !== null) {
            $this->agregarFilaCsv($primeraFila, $mapa, $tipo, $filas, $totalCargadas, $eliminadas);
        }

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $this->agregarFilaCsv($row, $mapa, $tipo, $filas, $totalCargadas, $eliminadas);
        }

        fclose($handle);

        return [
            'filas' => $filas,
            'total_cargadas' => $totalCargadas,
            'validas' => $filas->count(),
            'total_eliminadas' => $eliminadas,
        ];
    }

    private function agregarFilaCsv(array $row, array $mapa, string $tipo, $filas, int &$totalCargadas, int &$eliminadas): void
    {
        if ($this->filaVacia($row)) {
            return;
        }

        $totalCargadas++;
        $estatus = $this->limpiarTexto($row[$mapa['estatus']] ?? '');

        if ($estatus === '') {
            return;
        }

        if (strcasecmp($estatus, 'Validos') !== 0) {
            $eliminadas++;
            return;
        }

        $fecha = $this->normalizarFecha($row[$mapa['fecha']] ?? '');
        $agencia = $this->limpiarTexto($row[$mapa['agencia']] ?? '');
        $terminal = $this->formatearTerminalConsulta($row[$mapa['terminal']] ?? '');
        $usuarioVenta = $this->limpiarTexto($row[$mapa['usuario_venta']] ?? '');
        $totalApostado = $this->limpiarMonto($row[$mapa['total_apostado']] ?? 0);

        if ($agencia === '' && $terminal === '' && $usuarioVenta === '' && $totalApostado == 0.0) {
            return;
        }

        $filas->push([
            'tipo' => $tipo,
            'fecha' => $fecha['texto'],
            'fecha_orden' => $fecha['orden'],
            'agencia' => $agencia,
            'terminal' => $terminal,
            'usuario_venta' => $usuarioVenta,
            'total_apostado' => $totalApostado,
            'estatus' => 'Validos',
        ]);
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

    private function agenciasActivasSinVentas(array $clavesConVentas)
    {
        return Agencia::query()
            ->where('estatus', 1)
            ->whereNotNull('terminal')
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
