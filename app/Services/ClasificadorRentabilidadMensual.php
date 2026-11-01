<?php

namespace App\Services;

class ClasificadorRentabilidadMensual
{
    public const UMBRALES = [
        ['minimo' => 100, 'nombre' => 'Rentable', 'color' => 'success'],
        ['minimo' => 70, 'nombre' => 'Buena', 'color' => 'primary'],
        ['minimo' => 45, 'nombre' => 'Para rescatar', 'color' => 'warning'],
        ['minimo' => 0, 'nombre' => 'No rentable', 'color' => 'danger'],
    ];

    /** @param iterable<array<string, mixed>> $meses
     * @return array{nombre: string, color: string, meses_positivos: int, meses_con_ventas: int, porcentaje: ?float}
     */
    public function clasificar(iterable $meses, string $campoBalance): array
    {
        $conVentas = 0;
        $positivos = 0;
        foreach ($meses as $mes) {
            if ((float) $mes['ventas'] <= 0) {
                continue;
            }
            $conVentas++;
            $positivos += (float) $mes[$campoBalance] > 0 ? 1 : 0;
        }
        $resultado = ['nombre' => 'Sin ventas', 'color' => 'secondary', 'meses_positivos' => $positivos, 'meses_con_ventas' => $conVentas, 'porcentaje' => $conVentas > 0 ? $positivos / $conVentas * 100 : null];
        if ($conVentas === 0) {
            return $resultado;
        }
        foreach (self::UMBRALES as $umbral) {
            if ($positivos * 100 >= $conVentas * $umbral['minimo']) {
                return [...$resultado, 'nombre' => $umbral['nombre'], 'color' => $umbral['color']];
            }
        }

        return $resultado;
    }
}
