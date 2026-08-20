<?php

namespace App\Services\Incentivos;

use App\Models\IncentivoPeriodo;
use App\Models\IncentivoPeriodoDetalle;

class DesglosePagoCedulaReportService
{
    /** @return array<string, mixed> */
    public function build(IncentivoPeriodoDetalle $detalle): array
    {
        $detalle->loadMissing('periodo');
        $periodo = $detalle->periodo;
        $tiposPago = collect($detalle->tipos_pago_detalle ?? [])
            ->map(function (array $tipo) use ($periodo): array {
                $tipoPago = (string) ($tipo['tipo_pago'] ?? 'tramos_60');
                $ventas = (int) round((float) ($tipo['ventas'] ?? 0));
                $ventasBase = (int) round((float) ($tipo['ventas_base_escala'] ?? 0));
                $incentivo = (int) round((float) ($tipo['incentivo'] ?? 0));

                return [
                    'tipo_pago' => $tipoPago,
                    'etiqueta' => str_replace('tramos_', '', $tipoPago),
                    'ventas' => $ventas,
                    'ventas_base_escala' => $ventasBase,
                    'porcentaje' => $ventasBase > 0 ? round(($ventas / $ventasBase) * 100, 2) : 0,
                    'premio_escala' => $this->scaleAward($periodo, $tipoPago, $ventasBase),
                    'incentivo' => $incentivo,
                    'dias' => (int) ($tipo['dias'] ?? 0),
                    'terminales' => (int) ($tipo['terminales'] ?? 0),
                ];
            })
            ->values();

        return [
            'detalle' => $detalle,
            'periodo' => $periodo,
            'tiposPago' => $tiposPago,
            'resumen' => [
                'ventas_desglosadas' => (int) $tiposPago->sum('ventas'),
                'incentivo_desglosado' => (int) $tiposPago->sum('incentivo'),
                'porcentaje_total' => round((float) $tiposPago->sum('porcentaje'), 2),
            ],
        ];
    }

    private function scaleAward(IncentivoPeriodo $periodo, string $tipoPago, int $ventas): int
    {
        $rangos = $periodo->rangos_pago_por_tipo[$tipoPago] ?? $this->defaultRanges()[$tipoPago] ?? [];

        foreach ($rangos as $rango) {
            $desde = (int) round((float) ($rango['desde'] ?? 0));
            $hasta = isset($rango['hasta']) && $rango['hasta'] !== ''
                ? (int) round((float) $rango['hasta'])
                : null;

            if ($ventas >= $desde && ($hasta === null || $ventas <= $hasta)) {
                $pago = (float) ($rango['pago'] ?? 0);

                return (int) round(($rango['tipo'] ?? 'fijo') === 'porcentaje'
                    ? $ventas * ($pago / 100)
                    : $pago);
            }
        }

        return 0;
    }

    /** @return array<string, array<int, array<string, int|float|string|null>>> */
    private function defaultRanges(): array
    {
        return [
            'tramos_60' => $this->buildRanges(1, [1000, 2000, 4000, 6000, 8000, 9000]),
            'tramos_70' => $this->buildRanges(0.75, [750, 1500, 3000, 4500, 6000, 6750]),
            'tramos_80' => $this->buildRanges(0.5, [500, 1000, 2000, 3000, 4000, 4500]),
        ];
    }

    /**
     * @param  array<int, int>  $payments
     * @return array<int, array<string, int|float|string|null>>
     */
    private function buildRanges(float $percentage, array $payments): array
    {
        return [
            ['desde' => 100001, 'hasta' => 250000, 'pago' => $payments[0], 'tipo' => 'fijo'],
            ['desde' => 250001, 'hasta' => 400000, 'pago' => $payments[1], 'tipo' => 'fijo'],
            ['desde' => 400001, 'hasta' => 550000, 'pago' => $payments[2], 'tipo' => 'fijo'],
            ['desde' => 550001, 'hasta' => 700000, 'pago' => $payments[3], 'tipo' => 'fijo'],
            ['desde' => 700001, 'hasta' => 850000, 'pago' => $payments[4], 'tipo' => 'fijo'],
            ['desde' => 850001, 'hasta' => 1000000, 'pago' => $payments[5], 'tipo' => 'fijo'],
            ['desde' => 1000001, 'hasta' => 5000000, 'pago' => $percentage, 'tipo' => 'porcentaje'],
            ['desde' => 5000001, 'hasta' => null, 'pago' => $percentage, 'tipo' => 'porcentaje'],
        ];
    }
}
