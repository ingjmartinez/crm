<?php

namespace App\Services\Gerencia;

use App\Models\Agencia;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SeguimientoAgenciaService
{
    private const PRODUCTOS = [
        'tradicional' => 'Tradicional',
        'no_tradicional' => 'No Tradicional',
        'recargas' => 'Recargas',
    ];

    /** @param array<string, mixed> $filtros */
    public function generar(Carbon $fechaInicio, Carbon $fechaFin, array $filtros, array $metas): array
    {
        $agencias = $this->consultarAgencias($filtros);
        $diasAnalizados = (int) $fechaInicio->diffInDays($fechaFin) + 1;
        $diasMes = $fechaFin->daysInMonth;
        $ventas = $this->consultarVentas(
            $fechaInicio,
            $fechaFin,
            (string) ($filtros['sistema'] ?? 'todos'),
            $agencias,
            $metas
        );
        $ventasPorAgencia = $this->indexarVentas($ventas);
        $filas = collect();

        foreach ($agencias as $agencia) {
            $sistema = $this->normalizarSistema((string) $agencia->sistema);
            $terminal = $this->normalizarTerminal((string) $agencia->terminal);
            $ventasAgencia = $ventasPorAgencia->get($sistema.'|'.$terminal, collect());

            foreach (self::PRODUCTOS as $producto => $productoNombre) {
                $ventasProducto = $ventasAgencia->where('producto', $producto);
                $venta = (float) $ventasProducto->sum('venta');
                $metaDiaria = max(0, (float) ($metas[$producto] ?? 0));
                $metaAcumulada = $metaDiaria * $diasAnalizados;
                $cumplimiento = $metaAcumulada > 0 ? ($venta / $metaAcumulada) * 100 : 0;
                $promedioDiario = $diasAnalizados > 0 ? $venta / $diasAnalizados : 0;
                $diasCumplidos = $metaDiaria > 0 ? (int) $ventasProducto->sum('dias_cumplidos') : 0;

                $filas->push([
                    'empresa' => $this->valorMaestro($agencia->empresa),
                    'ciudad' => $this->valorMaestro($agencia->ciudad),
                    'coordinador' => $this->valorMaestro($agencia->coordinador),
                    'ruta' => $this->valorMaestro($agencia->ruta),
                    'agencia' => $this->nombreAgencia($agencia),
                    'terminal' => (string) $agencia->terminal,
                    'sistema' => strtoupper($sistema),
                    'producto' => $productoNombre,
                    'producto_key' => $producto,
                    'meta_diaria' => $metaDiaria,
                    'meta_acumulada' => $metaAcumulada,
                    'venta' => $venta,
                    'cumplimiento' => $cumplimiento,
                    'brecha' => $venta - $metaAcumulada,
                    'promedio_diario' => $promedioDiario,
                    'proyeccion' => $promedioDiario * $diasMes,
                    'dias_cumplidos' => $diasCumplidos,
                    'dias_no_cumplidos' => max(0, $diasAnalizados - $diasCumplidos),
                    'estado' => $this->estado($cumplimiento),
                ]);
            }
        }

        $metaAcumulada = (float) $filas->sum('meta_acumulada');
        $ventaTotal = (float) $filas->sum('venta');
        $cumplimiento = $metaAcumulada > 0 ? ($ventaTotal / $metaAcumulada) * 100 : 0;

        return [
            'filas' => $filas->sortBy([
                ['empresa', 'asc'], ['ciudad', 'asc'], ['coordinador', 'asc'],
                ['ruta', 'asc'], ['agencia', 'asc'], ['producto', 'asc'],
            ])->values(),
            'resumen' => [
                'agencias' => $agencias->count(),
                'meta_acumulada' => $metaAcumulada,
                'venta' => $ventaTotal,
                'cumplimiento' => $cumplimiento,
                'brecha' => $ventaTotal - $metaAcumulada,
                'promedio_diario' => $diasAnalizados > 0 ? $ventaTotal / $diasAnalizados : 0,
                'proyeccion' => $diasAnalizados > 0 ? ($ventaTotal / $diasAnalizados) * $diasMes : 0,
                'en_meta' => $filas->where('estado', 'Cumple')->count(),
                'en_seguimiento' => $filas->where('estado', 'En seguimiento')->count(),
                'criticos' => $filas->where('estado', 'Crítica')->count(),
            ],
            'por_producto' => collect(self::PRODUCTOS)->map(function (string $nombre, string $key) use ($filas): array {
                $items = $filas->where('producto_key', $key);
                $meta = (float) $items->sum('meta_acumulada');
                $venta = (float) $items->sum('venta');

                return [
                    'nombre' => $nombre,
                    'meta' => $meta,
                    'venta' => $venta,
                    'brecha' => $venta - $meta,
                    'cumplimiento' => $meta > 0 ? ($venta / $meta) * 100 : 0,
                ];
            })->values(),
            'dias_analizados' => $diasAnalizados,
        ];
    }

    /** @return array<string, Collection<int, string>> */
    public function opcionesFiltros(): array
    {
        $agencias = Agencia::query()->where('estatus', 1)->get(['empresa', 'ciudad', 'coordinador', 'ruta', 'nombre_agencia', 'agencia', 'terminal']);
        $valores = fn (string $campo): Collection => $agencias->pluck($campo)->map(fn ($valor) => trim((string) $valor))->filter()->unique()->sort()->values();

        return [
            'empresas' => $valores('empresa'),
            'ciudades' => $valores('ciudad'),
            'coordinadores' => $valores('coordinador'),
            'rutas' => $valores('ruta'),
            'agencias' => $agencias->map(fn ($agencia): string => $this->nombreAgencia($agencia))->filter()->unique()->sort()->values(),
        ];
    }

    /** @param array<string, float|int> $metas */
    public function detalleDiario(
        string $terminal,
        string $sistema,
        Carbon $fechaInicio,
        Carbon $fechaFin,
        array $metas
    ): array {
        $tabla = $sistema === 'lotonet' ? 'vt_usuarios_net' : 'vt_usuarios_bet';
        $terminalOriginal = trim($terminal);
        $terminalNormalizada = $this->normalizarTerminal($terminalOriginal);
        $terminales = collect([$terminalOriginal, $terminalNormalizada])->filter()->uniqueStrict()->values()->all();

        $ventas = DB::table($tabla)
            ->select(['fecha'])
            ->selectRaw('LOWER(TRIM(tipo)) AS tipo_normalizado')
            ->selectRaw('SUM(COALESCE(monto, 0)) AS venta')
            ->whereIn('agencia_id', $terminales)
            ->whereBetween('fecha', [$fechaInicio->toDateString(), $fechaFin->toDateString()])
            ->whereIn(DB::raw('LOWER(TRIM(tipo))'), ['tradicional', 'no tradicional', 'no_tradicional', 'recarga', 'recargas'])
            ->groupBy('fecha', DB::raw('LOWER(TRIM(tipo))'))
            ->get();

        $ventasPorDia = [];
        foreach ($ventas as $venta) {
            $producto = $this->normalizarProducto((string) $venta->tipo_normalizado);
            if ($producto !== null) {
                $fecha = Carbon::parse((string) $venta->fecha)->toDateString();
                $ventasPorDia[$fecha][$producto] = ($ventasPorDia[$fecha][$producto] ?? 0) + (float) $venta->venta;
            }
        }

        $dias = collect(CarbonPeriod::create($fechaInicio, $fechaFin))
            ->map(fn (Carbon $fecha): Carbon => $fecha->copy());
        $productos = collect(self::PRODUCTOS)->map(function (string $nombre, string $key) use ($dias, $ventasPorDia, $metas): array {
            $meta = max(0, (float) ($metas[$key] ?? 0));
            $detalle = $dias->map(function (Carbon $fecha) use ($key, $ventasPorDia, $meta): array {
                $fechaKey = $fecha->toDateString();
                $venta = (float) ($ventasPorDia[$fechaKey][$key] ?? 0);

                return [
                    'fecha' => $fechaKey,
                    'etiqueta' => $fecha->locale('es')->translatedFormat('D d'),
                    'venta' => $venta,
                    'cumple' => $meta > 0 && $venta >= $meta,
                ];
            })->values();

            return [
                'key' => $key,
                'nombre' => $nombre,
                'meta_diaria' => $meta,
                'ventas' => $detalle->pluck('venta')->all(),
                'dias' => $detalle->all(),
                'dias_cumplidos' => $detalle->where('cumple', true)->count(),
                'dias_no_cumplidos' => $detalle->where('cumple', false)->count(),
            ];
        })->values();

        return [
            'labels' => $dias->map(fn (Carbon $fecha): string => $fecha->format('d/m'))->all(),
            'productos' => $productos->all(),
            'periodo' => [
                'inicio' => $fechaInicio->toDateString(),
                'fin' => $fechaFin->toDateString(),
            ],
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
            ->when($filtros['coordinador'] ?? null, fn (Builder $query, string $valor) => $query->where('coordinador', $valor))
            ->when($filtros['ruta'] ?? null, fn (Builder $query, string $valor) => $query->where('ruta', $valor))
            ->when($filtros['agencia'] ?? null, function (Builder $query, string $valor): void {
                $query->where(function (Builder $subquery) use ($valor): void {
                    $subquery->where('nombre_agencia', $valor)->orWhere('agencia', $valor)->orWhere('terminal', $valor);
                });
            })
            ->get(['id', 'empresa', 'ciudad', 'coordinador', 'ruta', 'nombre_agencia', 'agencia', 'terminal', 'sistema']);
    }

    /** @param array<string, float|int> $metas */
    private function consultarVentas(
        Carbon $fechaInicio,
        Carbon $fechaFin,
        string $sistema,
        Collection $agencias,
        array $metas
    ): Collection {
        $tablas = match ($sistema) {
            'lotobet' => ['lotobet' => 'vt_usuarios_bet'],
            'lotonet' => ['lotonet' => 'vt_usuarios_net'],
            default => ['lotobet' => 'vt_usuarios_bet', 'lotonet' => 'vt_usuarios_net'],
        };
        $ventas = collect();

        foreach ($tablas as $nombreSistema => $tabla) {
            $terminales = $agencias
                ->filter(fn ($agencia): bool => $this->normalizarSistema((string) $agencia->sistema) === $nombreSistema)
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

            $cacheKey = 'gerencia:seguimiento-agencia:compacto:v2:'.sha1(implode('|', [
                $tabla,
                $fechaInicio->toDateString(),
                $fechaFin->toDateString(),
                implode(',', $terminales->all()),
                (string) ($metas['tradicional'] ?? 0),
                (string) ($metas['no_tradicional'] ?? 0),
                (string) ($metas['recargas'] ?? 0),
            ]));
            $filasCache = Cache::get($cacheKey);

            if (is_array($filasCache)) {
                $filas = collect($filasCache)->map(fn (array $fila): object => (object) $fila);
            } else {
                $filas = $this->consultarVentasAgrupadas(
                    $tabla,
                    $fechaInicio,
                    $fechaFin,
                    $terminales,
                    $metas
                );
                $filasParaCache = $filas->map(fn (object $fila): array => (array) $fila)->all();

                if (strlen(serialize($filasParaCache)) <= 8 * 1024 * 1024) {
                    $duracionCache = $fechaFin->isBefore(now()->startOfMonth())
                        ? now()->addHours(6)
                        : now()->addMinutes(15);
                    Cache::put($cacheKey, $filasParaCache, $duracionCache);
                }
            }

            foreach ($filas as $fila) {
                $producto = $this->normalizarProducto((string) $fila->tipo_normalizado);
                if ($producto === null) {
                    continue;
                }

                $ventas->push([
                    'sistema' => $nombreSistema,
                    'terminal' => $this->normalizarTerminal((string) $fila->agencia_id),
                    'producto' => $producto,
                    'venta' => (float) $fila->venta,
                    'dias_cumplidos' => (int) $fila->dias_cumplidos,
                ]);
            }
        }

        return $ventas;
    }

    /** @param array<string, float|int> $metas */
    private function consultarVentasAgrupadas(
        string $tabla,
        Carbon $fechaInicio,
        Carbon $fechaFin,
        Collection $terminales,
        array $metas
    ): Collection {

        $ventasDiarias = DB::table($tabla)
            ->select(['agencia_id', 'fecha'])
            ->selectRaw('LOWER(TRIM(tipo)) AS tipo_normalizado')
            ->selectRaw('SUM(COALESCE(monto, 0)) AS venta_diaria')
            ->whereNotNull('agencia_id')
            ->whereBetween('fecha', [$fechaInicio->toDateString(), $fechaFin->toDateString()])
            ->whereIn('agencia_id', $terminales->all())
            ->whereIn(DB::raw('LOWER(TRIM(tipo))'), ['tradicional', 'no tradicional', 'no_tradicional', 'recarga', 'recargas'])
            ->groupBy('agencia_id', 'fecha', DB::raw('LOWER(TRIM(tipo))'));

        return DB::query()
            ->fromSub($ventasDiarias, 'ventas_diarias')
            ->select(['agencia_id', 'tipo_normalizado'])
            ->selectRaw('SUM(venta_diaria) AS venta')
            ->selectRaw(
                "SUM(CASE
                        WHEN tipo_normalizado = 'tradicional' AND venta_diaria >= CAST(? AS DECIMAL(14, 2)) THEN 1
                        WHEN tipo_normalizado IN ('no tradicional', 'no_tradicional') AND venta_diaria >= CAST(? AS DECIMAL(14, 2)) THEN 1
                        WHEN tipo_normalizado IN ('recarga', 'recargas') AND venta_diaria >= CAST(? AS DECIMAL(14, 2)) THEN 1
                        ELSE 0
                    END) AS dias_cumplidos",
                [
                    max(0, (float) ($metas['tradicional'] ?? 0)),
                    max(0, (float) ($metas['no_tradicional'] ?? 0)),
                    max(0, (float) ($metas['recargas'] ?? 0)),
                ]
            )
            ->groupBy('agencia_id', 'tipo_normalizado')
            ->get();
    }

    private function indexarVentas(Collection $ventas): Collection
    {
        return $ventas->groupBy(fn (array $venta): string => $venta['sistema'].'|'.$venta['terminal']);
    }

    private function normalizarTerminal(string $terminal): string
    {
        $normalizado = ltrim(trim($terminal), '0');

        return $normalizado !== '' ? $normalizado : '0';
    }

    private function normalizarSistema(string $sistema): string
    {
        $sistema = mb_strtolower(trim($sistema));

        return str_contains($sistema, 'net') ? 'lotonet' : 'lotobet';
    }

    private function normalizarProducto(string $tipo): ?string
    {
        return match (mb_strtolower(trim($tipo))) {
            'tradicional' => 'tradicional',
            'no tradicional', 'no_tradicional' => 'no_tradicional',
            'recarga', 'recargas' => 'recargas',
            default => null,
        };
    }

    private function estado(float $cumplimiento): string
    {
        return match (true) {
            $cumplimiento >= 100 => 'Cumple',
            $cumplimiento >= 85 => 'En seguimiento',
            default => 'Crítica',
        };
    }

    private function nombreAgencia(object $agencia): string
    {
        foreach (['nombre_agencia', 'agencia', 'terminal'] as $campo) {
            $valor = trim((string) ($agencia->{$campo} ?? ''));
            if ($valor !== '') {
                return $valor;
            }
        }

        return 'Sin nombre';
    }

    private function valorMaestro(?string $valor): string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? $valor : 'Sin asignar';
    }
}
