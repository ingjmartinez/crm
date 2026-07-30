<?php

namespace App\Services\Contabilidad;

class DistribuidorIncentivoAgencia
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function incentivoGanadoCentavos(array $row): int
    {
        $cumpleMinimo = mb_strtoupper(trim((string) ($row['cumple_minimo'] ?? '')));
        if ($cumpleMinimo !== 'SI') {
            return 0;
        }

        $incentivo = (float) str_replace(',', '', (string) ($row['nuevo_incentivo'] ?? 0));

        return max(0, (int) round($incentivo * 100));
    }

    /**
     * @param  array<int, float|int|string>  $ventas
     * @return array<int, int>
     */
    public function distribuir(int $incentivoCentavos, array $ventas): array
    {
        $ventasNormalizadas = array_map(
            fn ($venta): float => max(0, (float) $venta),
            $ventas
        );
        $totalVentas = array_sum($ventasNormalizadas);

        if ($incentivoCentavos <= 0 || $totalVentas <= 0 || $ventasNormalizadas === []) {
            return array_fill(0, count($ventasNormalizadas), 0);
        }

        $asignaciones = [];
        $residuos = [];
        $centavosAsignados = 0;

        foreach ($ventasNormalizadas as $index => $venta) {
            $asignacionExacta = ($incentivoCentavos * $venta) / $totalVentas;
            $asignacionBase = (int) floor($asignacionExacta);
            $asignaciones[$index] = $asignacionBase;
            $residuos[$index] = $asignacionExacta - $asignacionBase;
            $centavosAsignados += $asignacionBase;
        }

        $centavosPendientes = $incentivoCentavos - $centavosAsignados;
        uksort($residuos, function (int $left, int $right) use ($residuos): int {
            $comparison = $residuos[$right] <=> $residuos[$left];

            return $comparison !== 0 ? $comparison : $left <=> $right;
        });

        foreach (array_keys($residuos) as $index) {
            if ($centavosPendientes === 0) {
                break;
            }

            $asignaciones[$index]++;
            $centavosPendientes--;
        }

        ksort($asignaciones);

        return array_values($asignaciones);
    }
}
