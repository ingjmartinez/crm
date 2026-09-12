<?php

namespace App\Services\Gerencia;

use App\Models\Agencia;
use App\Services\Lotobet\LotobetSessionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VentasEnVivoService
{
    /** @param array<string, mixed> $filtros */
    public function generarTotales(Carbon $fecha, array $filtros): array
    {
        $agencias = $this->consultarAgencias($filtros);
        $ventas = $this->consultarVentas($fecha, $filtros, $agencias, $this->cargarMapaTiposProductos());

        return [
            'resumen' => [
                'fecha' => $fecha->toDateString(),
                'total_tradicional' => (float) $ventas->where('tipo_categoria', 'tradicional')->sum('monto'),
                'total_no_tradicional' => (float) $ventas->where('tipo_categoria', 'no_tradicional')->sum('monto'),
            ],
        ];
    }

    /** @param array<string, mixed> $filtros */
    public function generar(Carbon $fecha, array $filtros): array
    {
        $agencias = $this->consultarAgencias($filtros);
        $mapaTipos = $this->cargarMapaTiposProductos();
        $ventas = $this->consultarVentas($fecha, $filtros, $agencias, $mapaTipos);

        if (($filtros['tipo_producto'] ?? 'todos') !== 'todos') {
            $tipoSeleccionado = (string) $filtros['tipo_producto'];
            $ventas = $ventas->where('tipo_categoria', $tipoSeleccionado)->values();
        }

        $buscar = mb_strtolower(trim((string) ($filtros['buscar'] ?? '')));
        if ($buscar !== '') {
            $ventas = $ventas->filter(function (array $fila) use ($buscar): bool {
                $texto = mb_strtolower(implode(' ', [
                    (string) ($fila['nombre_agencia'] ?? ''),
                    (string) ($fila['agencia'] ?? ''),
                    (string) ($fila['terminal'] ?? ''),
                    (string) ($fila['empresa'] ?? ''),
                    (string) ($fila['ciudad'] ?? ''),
                    (string) ($fila['ruta'] ?? ''),
                    (string) ($fila['producto'] ?? ''),
                ]));

                return str_contains($texto, $buscar);
            })->values();
        }

        $totalVentas = (float) $ventas->sum('monto');
        $totalTradicional = (float) $ventas->where('tipo_categoria', 'tradicional')->sum('monto');
        $totalNoTradicional = (float) $ventas->where('tipo_categoria', 'no_tradicional')->sum('monto');
        $terminalesConVenta = $ventas->pluck('terminal')->filter()->uniqueStrict()->count();

        $topTerminales = $this->rankingTerminales($ventas, true);
        $peoresAgencias = $this->rankingTerminales($ventas, false);
        $topProductos = $this->rankingProductos($ventas, true);
        $peoresProductos = $this->rankingProductos($ventas, false);

        $detalle = $ventas
            ->groupBy(function (array $fila): string {
                return implode('|', [
                    (string) $fila['sistema'],
                    (string) $fila['terminal'],
                    (string) $fila['producto_id'],
                    (string) $fila['tipo_categoria'],
                ]);
            })
            ->map(function (Collection $items): array {
                $primero = $items->first();

                return [
                    'empresa' => (string) ($primero['empresa'] ?? 'Sin asignar'),
                    'ciudad' => (string) ($primero['ciudad'] ?? 'Sin asignar'),
                    'ruta' => (string) ($primero['ruta'] ?? 'Sin asignar'),
                    'agencia' => (string) ($primero['agencia'] ?? 'Sin agencia'),
                    'nombre_agencia' => (string) ($primero['nombre_agencia'] ?? 'Sin agencia'),
                    'terminal' => (string) ($primero['terminal'] ?? '0'),
                    'sistema' => strtoupper((string) ($primero['sistema'] ?? 'lotobet')),
                    'tipo_categoria' => (string) ($primero['tipo_categoria'] ?? 'otros'),
                    'producto' => (string) ($primero['producto'] ?? 'Sin descripcion'),
                    'monto' => (float) $items->sum('monto'),
                ];
            })
            ->sortByDesc('monto')
            ->take(500)
            ->values();

        return [
            'resumen' => [
                'fecha' => $fecha->toDateString(),
                'total_ventas' => $totalVentas,
                'total_tradicional' => $totalTradicional,
                'total_no_tradicional' => $totalNoTradicional,
                'participacion_no_tradicional' => $totalVentas > 0 ? ($totalNoTradicional / $totalVentas) * 100 : 0,
                'terminales_con_venta' => $terminalesConVenta,
                'agencias_con_venta' => $terminalesConVenta,
                'ticket_promedio_terminal' => $terminalesConVenta > 0 ? $totalVentas / $terminalesConVenta : 0,
            ],
            'por_tipo' => [
                [
                    'key' => 'tradicional',
                    'nombre' => 'Tradicional',
                    'monto' => $totalTradicional,
                    'porcentaje' => $totalVentas > 0 ? ($totalTradicional / $totalVentas) * 100 : 0,
                ],
                [
                    'key' => 'no_tradicional',
                    'nombre' => 'No Tradicional',
                    'monto' => $totalNoTradicional,
                    'porcentaje' => $totalVentas > 0 ? ($totalNoTradicional / $totalVentas) * 100 : 0,
                ],
            ],
            'top_terminales' => $topTerminales->all(),
            'top_productos' => $topProductos->all(),
            'peores_agencias' => $peoresAgencias->all(),
            'peores_productos' => $peoresProductos->all(),
            'detalle' => $detalle->all(),
            'totales' => [
                'filas_detalle' => $detalle->count(),
                'productos_unicos' => $ventas->pluck('producto_id')->filter()->uniqueStrict()->count(),
            ],
        ];
    }

    /** @return array<string, Collection<int, string>> */
    public function opcionesFiltros(): array
    {
        $agencias = Agencia::query()
            ->where('estatus', 1)
            ->whereNotNull('terminal')
            ->get(['empresa', 'ciudad', 'ruta']);

        $valores = fn (string $campo): Collection => $agencias
            ->pluck($campo)
            ->map(fn ($valor): string => trim((string) $valor))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return [
            'empresas' => $valores('empresa'),
            'ciudades' => $valores('ciudad'),
            'rutas' => $valores('ruta'),
        ];
    }

    /** @param array<string, mixed> $filtros */
    private function consultarAgencias(array $filtros): Collection
    {
        return Agencia::query()
            ->where('estatus', 1)
            ->whereNotNull('terminal')
            ->when(($filtros['sistema'] ?? 'todos') !== 'todos', function (Builder $query) use ($filtros): void {
                if ($filtros['sistema'] === 'lotonet') {
                    $query->whereRaw("LOWER(TRIM(COALESCE(sistema, ''))) LIKE ?", ['%net%']);

                    return;
                }

                $query->whereRaw("LOWER(TRIM(COALESCE(sistema, ''))) NOT LIKE ?", ['%net%']);
            })
            ->when($filtros['empresa'] ?? null, fn (Builder $query, string $valor) => $query->where('empresa', $valor))
            ->when($filtros['ciudad'] ?? null, fn (Builder $query, string $valor) => $query->where('ciudad', $valor))
            ->when($filtros['ruta'] ?? null, fn (Builder $query, string $valor) => $query->where('ruta', $valor))
            ->get(['agencia', 'nombre_agencia', 'terminal', 'empresa', 'ciudad', 'ruta', 'sistema']);
    }

    /** @param array<string, mixed> $filtros */
    private function consultarVentas(Carbon $fecha, array $filtros, Collection $agencias, array $mapaTipos): Collection
    {
        $agenciasIndexadas = $agencias
            ->map(function (Agencia $agencia): array {
                return [
                    'agencia' => trim((string) $agencia->agencia),
                    'nombre_agencia' => trim((string) $agencia->nombre_agencia) ?: trim((string) $agencia->agencia),
                    'terminal' => trim((string) $agencia->terminal),
                    'terminal_normalizada' => $this->normalizarTerminal((string) $agencia->terminal),
                    'empresa' => $this->valorMaestro($agencia->empresa),
                    'ciudad' => $this->valorMaestro($agencia->ciudad),
                    'ruta' => $this->valorMaestro($agencia->ruta),
                    'sistema' => $this->normalizarSistema((string) $agencia->sistema),
                ];
            })
            ->groupBy(fn (array $agencia): string => $agencia['sistema'].'|'.$agencia['terminal_normalizada'])
            ->map(fn (Collection $items): array => $items->first());

        $sistemas = match ((string) ($filtros['sistema'] ?? 'todos')) {
            'lotobet' => ['lotobet'],
            'lotonet' => ['lotonet'],
            default => ['lotobet', 'lotonet'],
        };

        $filas = collect();

        foreach ($sistemas as $sistema) {
            $terminales = $agencias
                ->filter(fn (Agencia $agencia): bool => $this->normalizarSistema((string) $agencia->sistema) === $sistema)
                ->pluck('terminal')
                ->flatMap(function ($terminal): array {
                    $original = trim((string) $terminal);

                    return [$original, $this->normalizarTerminal($original)];
                })
                ->filter(fn (string $terminal): bool => $terminal !== '' && $terminal !== '0')
                ->uniqueStrict()
                ->values();

            if ($terminales->isEmpty()) {
                continue;
            }

            $rows = $sistema === 'lotobet'
                ? $this->consultarVentasLotobet($fecha, $terminales)
                : $this->consultarVentasLotonetTabla($fecha, $terminales);

            foreach ($rows as $row) {
                $terminalNormalizada = $this->normalizarTerminal((string) $row->agencia_id);
                $agencia = $agenciasIndexadas->get($sistema.'|'.$terminalNormalizada);
                if (! is_array($agencia)) {
                    continue;
                }

                $tipoCategoria = $this->resolverTipoCategoria($row, $mapaTipos);

                $filas->push([
                    'sistema' => $sistema,
                    'terminal' => $agencia['terminal'],
                    'agencia' => $agencia['agencia'] !== '' ? $agencia['agencia'] : $agencia['terminal'],
                    'nombre_agencia' => $agencia['nombre_agencia'] !== '' ? $agencia['nombre_agencia'] : $agencia['terminal'],
                    'empresa' => $agencia['empresa'],
                    'ciudad' => $agencia['ciudad'],
                    'ruta' => $agencia['ruta'],
                    'tipo_categoria' => $tipoCategoria,
                    'producto_id' => trim((string) $row->producto_id),
                    'producto' => trim((string) $row->descripcion) !== '' ? trim((string) $row->descripcion) : 'Sin descripcion',
                    'monto' => (float) $row->monto,
                ]);
            }
        }

        return $filas;
    }

    /** @return array<string, string> */
    private function cargarMapaTiposProductos(): array
    {
        $rows = DB::table('catalogo_juegos')
            ->select(['producto_id', 'tipo', 'descripcion'])
            ->whereNotNull('descripcion')
            ->get();

        $mapa = [];
        foreach ($rows as $row) {
            $tipo = $this->normalizarTipo((string) $row->tipo);
            if ($tipo === 'otros') {
                continue;
            }

            $descripcion = $this->normalizarTextoClave((string) $row->descripcion);
            if ($descripcion !== '') {
                $mapa['desc:'.$descripcion] = $tipo;
            }

            $productoId = trim((string) $row->producto_id);
            if ($productoId !== '' && $productoId !== '0') {
                $mapa['id:'.$productoId] = $tipo;
            }
        }

        return $mapa;
    }

    /** @param array<string, string> $mapaTipos */
    private function resolverTipoCategoria(object $row, array $mapaTipos): string
    {
        $tipoDirecto = $this->normalizarTipo((string) ($row->tipo_normalizado ?? ''));
        if ($tipoDirecto !== 'otros') {
            return $tipoDirecto;
        }

        $productoId = trim((string) ($row->producto_id ?? ''));
        if ($productoId !== '' && $productoId !== '0') {
            $porId = $mapaTipos['id:'.$productoId] ?? null;
            if ($porId !== null) {
                return $porId;
            }
        }

        $descripcionNormalizada = $this->normalizarTextoClave((string) ($row->descripcion ?? ''));
        if ($descripcionNormalizada !== '') {
            $porDescripcion = $mapaTipos['desc:'.$descripcionNormalizada] ?? null;
            if ($porDescripcion !== null) {
                return $porDescripcion;
            }

            if (str_contains($descripcionNormalizada, 'recarga')) {
                return 'otros';
            }

            if (
                str_contains($descripcionNormalizada, 'chance express')
                || str_contains($descripcionNormalizada, 'lotto loteka')
                || str_contains($descripcionNormalizada, 'power lotto')
                || str_contains($descripcionNormalizada, 'extra lotto')
                || str_contains($descripcionNormalizada, 'mega chance')
                || str_contains($descripcionNormalizada, 'repartidera')
                || str_contains($descripcionNormalizada, 'ruleta express')
                || str_contains($descripcionNormalizada, 'toca 3')
            ) {
                return 'no_tradicional';
            }

            if (
                str_contains($descripcionNormalizada, 'quiniela')
                || str_contains($descripcionNormalizada, 'tripleta')
                || str_contains($descripcionNormalizada, 'pale')
                || str_contains($descripcionNormalizada, 'super pale')
            ) {
                return 'tradicional';
            }
        }

        return 'otros';
    }

    private function consultarVentasLotobet(Carbon $fecha, Collection $terminales): Collection
    {
        try {
            $terminalesIndexados = array_fill_keys($terminales->all(), true);
            $apiRows = $this->consultarVentasLotobetApi($fecha)
                ->filter(fn (object $row): bool => isset($terminalesIndexados[$this->normalizarTerminal((string) $row->agencia_id)]))
                ->values();

            $apiTieneTradicional = $apiRows->contains(function (object $row): bool {
                return $this->normalizarTipo((string) $row->tipo_normalizado) === 'tradicional';
            });

            if ($apiTieneTradicional) {
                return $apiRows;
            }

            $tradicionalTabla = $this->consultarVentasLotobetTablaTradicional($fecha, $terminales);

            return $apiRows->concat($tradicionalTabla)->values();
        } catch (\Throwable) {
            return $this->consultarVentasLotobetTablaCompleta($fecha, $terminales);
        }
    }

    private function consultarVentasLotobetApi(Carbon $fecha): Collection
    {
        $respuesta = app(LotobetSessionService::class)->getVentasProducto($fecha->toDateString());
        $contenido = $respuesta['Content'] ?? [];

        if (! is_array($contenido)) {
            return collect();
        }

        return collect($contenido)->map(function (array $item): object {
            return (object) [
                'agencia_id' => (string) ($item['agencia_id'] ?? ''),
                'producto_id' => (string) ($item['producto_id'] ?? ''),
                'descripcion' => (string) ($item['descripcion'] ?? ''),
                'tipo_normalizado' => mb_strtolower(trim((string) ($item['tipo'] ?? ''))),
                'monto' => (float) ($item['monto'] ?? 0),
            ];
        });
    }

    private function consultarVentasLotonetTabla(Carbon $fecha, Collection $terminales): Collection
    {
        return DB::table('vt_usuarios_net')
            ->select(['agencia_id', 'producto_id', 'descripcion'])
            ->selectRaw('LOWER(TRIM(tipo)) as tipo_normalizado')
            ->selectRaw('SUM(COALESCE(monto, 0)) as monto')
            ->whereDate('fecha', $fecha->toDateString())
            ->whereIn('agencia_id', $terminales->all())
            ->groupBy('agencia_id', 'producto_id', 'descripcion', DB::raw('LOWER(TRIM(tipo))'))
            ->get();
    }

    private function consultarVentasLotobetTablaTradicional(Carbon $fecha, Collection $terminales): Collection
    {
        return DB::table('vt_usuarios_bet')
            ->select(['agencia_id', 'producto_id', 'descripcion'])
            ->selectRaw('LOWER(TRIM(tipo)) as tipo_normalizado')
            ->selectRaw('SUM(COALESCE(monto, 0)) as monto')
            ->whereDate('fecha', $fecha->toDateString())
            ->whereIn('agencia_id', $terminales->all())
            ->whereIn(DB::raw('LOWER(TRIM(tipo))'), ['tradicional'])
            ->groupBy('agencia_id', 'producto_id', 'descripcion', DB::raw('LOWER(TRIM(tipo))'))
            ->get();
    }

    private function consultarVentasLotobetTablaCompleta(Carbon $fecha, Collection $terminales): Collection
    {
        return DB::table('vt_usuarios_bet')
            ->select(['agencia_id', 'producto_id', 'descripcion'])
            ->selectRaw('LOWER(TRIM(tipo)) as tipo_normalizado')
            ->selectRaw('SUM(COALESCE(monto, 0)) as monto')
            ->whereDate('fecha', $fecha->toDateString())
            ->whereIn('agencia_id', $terminales->all())
            ->groupBy('agencia_id', 'producto_id', 'descripcion', DB::raw('LOWER(TRIM(tipo))'))
            ->get();
    }

    private function rankingTerminales(Collection $ventas, bool $descendente): Collection
    {
        $items = $ventas
            ->groupBy(fn (array $fila): string => $fila['sistema'].'|'.$fila['terminal'])
            ->map(function (Collection $filas): array {
                $primero = $filas->first();

                return [
                    'terminal' => (string) ($primero['terminal'] ?? '0'),
                    'agencia' => (string) ($primero['agencia'] ?? 'Sin agencia'),
                    'nombre_agencia' => (string) ($primero['nombre_agencia'] ?? 'Sin agencia'),
                    'empresa' => (string) ($primero['empresa'] ?? 'Sin asignar'),
                    'ciudad' => (string) ($primero['ciudad'] ?? 'Sin asignar'),
                    'ruta' => (string) ($primero['ruta'] ?? 'Sin asignar'),
                    'sistema' => strtoupper((string) ($primero['sistema'] ?? 'LOTOBET')),
                    'monto' => (float) $filas->sum('monto'),
                ];
            });

        $ordenado = $descendente
            ? $items->sortBy([['monto', 'desc'], ['nombre_agencia', 'asc']])
            : $items->sortBy([['monto', 'asc'], ['nombre_agencia', 'asc']]);

        return $ordenado->take(10)->values();
    }

    private function rankingProductos(Collection $ventas, bool $descendente): Collection
    {
        $items = $ventas
            ->groupBy(fn (array $fila): string => ($fila['producto_id'] ?: 'sin-id').'|'.$fila['producto'])
            ->map(function (Collection $filas): array {
                $primero = $filas->first();

                return [
                    'producto_id' => (string) ($primero['producto_id'] ?? ''),
                    'producto' => (string) ($primero['producto'] ?? 'Sin descripcion'),
                    'monto' => (float) $filas->sum('monto'),
                    'tradicional' => (float) $filas->where('tipo_categoria', 'tradicional')->sum('monto'),
                    'no_tradicional' => (float) $filas->where('tipo_categoria', 'no_tradicional')->sum('monto'),
                ];
            });

        $ordenado = $descendente
            ? $items->sortBy([['monto', 'desc'], ['producto', 'asc']])
            : $items->sortBy([['monto', 'asc'], ['producto', 'asc']]);

        return $ordenado->take(10)->values();
    }

    private function normalizarSistema(string $sistema): string
    {
        $sistema = mb_strtolower(trim($sistema));

        return str_contains($sistema, 'net') ? 'lotonet' : 'lotobet';
    }

    private function normalizarTerminal(string $terminal): string
    {
        $normalizada = ltrim(trim($terminal), '0');

        return $normalizada !== '' ? $normalizada : '0';
    }

    private function normalizarTipo(string $tipo): string
    {
        $valor = mb_strtolower(trim($tipo));

        return match ($valor) {
            'tradicional' => 'tradicional',
            'no tradicional', 'no_tradicional' => 'no_tradicional',
            default => 'otros',
        };
    }

    private function normalizarTextoClave(string $texto): string
    {
        $valor = mb_strtolower(trim($texto));
        $valor = preg_replace('/\s+/', ' ', $valor) ?? $valor;

        return $valor;
    }

    private function valorMaestro(?string $valor): string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? $valor : 'Sin asignar';
    }
}
