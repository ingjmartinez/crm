<?php

namespace App\Services\Ventas;

use App\Models\VentaOnlinePromedioHistorico;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Calcula y persiste el promedio de venta diario de los ultimos 3 meses completos (tradicional y no
 * tradicional) a partir de la tabla vt_usuarios_bet: se suma el monto de esos 3 meses y se divide entre
 * todos los dias calendario incluidos en el periodo. El calculo solo se dispara manualmente
 * (boton "Calcular promedio") y el resultado queda guardado hasta la proxima vez que se solicite recalcular.
 */
class PromedioHistoricoVentasOnlineService
{
    private const TABLA_VENTAS = 'vt_usuarios_bet';

    /**
     * @return array<int, Carbon>
     */
    public function mesesAIncluir(?Carbon $referencia = null): array
    {
        $referencia = ($referencia ?? now())->copy()->startOfMonth();

        return [
            $referencia->copy()->subMonthsNoOverflow(3),
            $referencia->copy()->subMonthsNoOverflow(2),
            $referencia->copy()->subMonthsNoOverflow(1),
        ];
    }

    /**
     * @return array<string, VentaOnlinePromedioHistorico>
     */
    public function calcularYGuardar(?Carbon $referencia = null): array
    {
        $meses = $this->mesesAIncluir($referencia);
        $totalesPorTipo = $this->sumarVentasDelPeriodo($meses);
        $diasDelPeriodo = array_sum(array_map(
            fn (Carbon $mes): int => $mes->daysInMonth,
            $meses
        ));
        $mesesIncluidos = array_map(fn (Carbon $mes): string => $mes->format('Y-m'), $meses);
        $calculadoEn = now();
        $calculadoPorId = auth()->id();

        $resultados = [];

        foreach ([ClasificadorTipoProducto::TRADICIONAL, ClasificadorTipoProducto::NO_TRADICIONAL] as $tipo) {
            $promedio = $diasDelPeriodo > 0 ? $totalesPorTipo[$tipo] / $diasDelPeriodo : 0.0;

            $resultados[$tipo] = VentaOnlinePromedioHistorico::query()->updateOrCreate(
                ['tipo_categoria' => $tipo],
                [
                    'monto_promedio' => round($promedio, 2),
                    'meses_incluidos' => $mesesIncluidos,
                    'calculado_en' => $calculadoEn,
                    'calculado_por_id' => $calculadoPorId,
                ]
            );
        }

        return $resultados;
    }

    /**
     * @return array<string, VentaOnlinePromedioHistorico>
     */
    public function obtenerGuardado(): array
    {
        return VentaOnlinePromedioHistorico::query()
            ->get()
            ->keyBy('tipo_categoria')
            ->all();
    }

    /**
     * @param  array<int, Carbon>  $meses
     * @return array<string, float>
     */
    private function sumarVentasDelPeriodo(array $meses): array
    {
        $totales = [
            ClasificadorTipoProducto::TRADICIONAL => 0.0,
            ClasificadorTipoProducto::NO_TRADICIONAL => 0.0,
        ];
        if (! Schema::hasTable(self::TABLA_VENTAS)) {
            return $totales;
        }

        $fila = DB::table(self::TABLA_VENTAS)
            ->whereBetween('fecha', [
                $meses[0]->copy()->startOfMonth()->toDateString(),
                $meses[array_key_last($meses)]->copy()->endOfMonth()->toDateString(),
            ])
            ->selectRaw("SUM(CASE WHEN LOWER(TRIM(COALESCE(tipo, ''))) = 'tradicional' THEN monto ELSE 0 END) as tradicional")
            ->selectRaw("SUM(CASE WHEN LOWER(TRIM(COALESCE(tipo, ''))) IN ('no tradicional', 'no_tradicional') THEN monto ELSE 0 END) as no_tradicional")
            ->first();

        $totales[ClasificadorTipoProducto::TRADICIONAL] = (float) ($fila->tradicional ?? 0);
        $totales[ClasificadorTipoProducto::NO_TRADICIONAL] = (float) ($fila->no_tradicional ?? 0);

        return $totales;
    }

    /**
     * @param  array<string, VentaOnlinePromedioHistorico>  $registros
     * @return array<int, array<string, mixed>>
     */
    public function paraJson(array $registros): array
    {
        return Collection::make($registros)
            ->map(fn (VentaOnlinePromedioHistorico $registro): array => [
                'tipo_categoria' => $registro->tipo_categoria,
                'monto_promedio' => (float) $registro->monto_promedio,
                'meses_incluidos' => $registro->meses_incluidos,
                'dias_promediados' => Collection::make($registro->meses_incluidos)
                    ->sum(fn (string $mes): int => Carbon::createFromFormat('Y-m', $mes)->daysInMonth),
                'calculado_en' => $registro->calculado_en?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
