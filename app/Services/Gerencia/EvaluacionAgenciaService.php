<?php

namespace App\Services\Gerencia;

use App\Models\Agencia;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EvaluacionAgenciaService
{
    /** @return array{meses: Collection<int, array{clave: string, etiqueta: string}>, filas: Collection<int, array<string, mixed>>} */
    public function evaluar(int $cantidadMeses, float $meta, string $empresa = '', array $productos = ['tradicional', 'no_tradicional', 'recargas']): array
    {
        $fin = now()->startOfMonth()->addMonth();
        $inicio = $fin->copy()->subMonths($cantidadMeses);
        $meses = collect(range(0, $cantidadMeses - 1))->map(function (int $indice) use ($inicio): array {
            $mes = $inicio->copy()->addMonths($indice);

            return ['clave' => $mes->format('Y-m'), 'etiqueta' => ucfirst($mes->locale('es')->translatedFormat('M Y'))];
        });

        $ventas = collect(['vt_usuarios_bet', 'vt_usuarios_net'])
            ->flatMap(fn (string $tabla): Collection => $this->ventasMensuales($tabla, $inicio, $fin, $productos))
            ->groupBy(fn (object $venta): string => $this->normalizarTerminal($venta->terminal).'|'.$venta->mes)
            ->map(fn (Collection $grupo): float => (float) $grupo->sum('venta'));

        $terminales = $ventas->keys()->map(fn (string $clave): string => explode('|', $clave, 2)[0])->unique()->values();
        $agencias = Agencia::query()->whereNotNull('terminal')->get(['terminal', 'nombre_agencia'])
            ->keyBy(fn (Agencia $agencia): string => $this->normalizarTerminal($agencia->terminal));

        if ($empresa !== '') {
            $agencias = Agencia::query()
                ->whereNotNull('terminal')
                ->whereRaw('TRIM(empresa) = ?', [$empresa])
                ->get(['terminal', 'nombre_agencia'])
                ->keyBy(fn (Agencia $agencia): string => $this->normalizarTerminal($agencia->terminal));
            $terminales = $terminales->filter(fn (string $terminal): bool => $agencias->has($terminal))->values();
        }

        $filas = $terminales->map(function (string $terminal) use ($agencias, $meses, $ventas, $meta): array {
            $agencia = $agencias->get($terminal);
            $ventasPorMes = $meses->mapWithKeys(function (array $mes) use ($terminal, $ventas, $meta): array {
                $venta = (float) $ventas->get($terminal.'|'.$mes['clave'], 0);

                return [$mes['clave'] => ['venta' => $venta, 'cumple' => $venta >= $meta]];
            });

            return [
                'terminal' => str_pad($terminal, 6, '0', STR_PAD_LEFT),
                'agencia' => $agencia?->nombre_agencia ?: 'Sin agencia registrada',
                'ventas' => $ventasPorMes,
                'meses_cumple' => $ventasPorMes->where('cumple', true)->count(),
                'meses_no_cumple' => $ventasPorMes->where('cumple', false)->count(),
                'cumple' => $ventasPorMes->every(fn (array $venta): bool => $venta['cumple']),
            ];
        })->sortBy('agencia')->values();

        return ['meses' => $meses, 'filas' => $filas];
    }

    /** @return Collection<int, string> */
    public function empresasDisponibles(): Collection
    {
        return Agencia::query()
            ->whereNotNull('empresa')
            ->whereRaw("TRIM(empresa) <> ''")
            ->orderBy('empresa')
            ->pluck('empresa')
            ->map(fn (mixed $empresa): string => trim((string) $empresa))
            ->unique()
            ->values();
    }

    /** @return Collection<int, object> */
    private function ventasMensuales(string $tabla, Carbon $inicio, Carbon $fin, array $productos): Collection
    {
        $mesSql = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', fecha)"
            : "DATE_FORMAT(fecha, '%Y-%m')";

        $tipos = collect($productos)->flatMap(fn (string $producto): array => match ($producto) {
            'tradicional' => ['tradicional'],
            'no_tradicional' => ['no tradicional', 'no_tradicional'],
            'recargas' => ['recarga', 'recargas', 'paquetico', 'paqueticos'],
        })->all();

        return DB::table($tabla)
            ->where('fecha', '>=', $inicio->toDateString())
            ->where('fecha', '<', $fin->toDateString())
            ->whereIn(DB::raw('LOWER(TRIM(tipo))'), $tipos)
            ->whereNotNull('agencia_id')
            ->where('agencia_id', '<>', '')
            ->selectRaw("agencia_id AS terminal, {$mesSql} AS mes, SUM(COALESCE(monto, 0)) AS venta")
            ->groupBy('agencia_id')
            ->groupByRaw($mesSql)
            ->get();
    }

    private function normalizarTerminal(mixed $terminal): string
    {
        $digitos = preg_replace('/\D+/', '', trim((string) $terminal));

        return ltrim((string) $digitos, '0') ?: '0';
    }
}
