<?php

namespace App\Services;

class ClasificacionRentabilidadAgenciaV11
{
    public const CATEGORIAS = [
        'Rentables' => ['fondo' => '#d1e7dd', 'color' => '#0f5132'],
        'Buenas' => ['fondo' => '#cfe2ff', 'color' => '#084298'],
        'Para trabajar' => ['fondo' => '#ffe5d0', 'color' => '#984c0c'],
        'Atención prioritaria' => ['fondo' => '#f8d7da', 'color' => '#842029'],
        'Sin datos' => ['fondo' => '#e9ecef', 'color' => '#495057'],
    ];

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array{etiqueta: string, fondo: string, color: string, cantidad: int}>
     */
    public function resumir(array $filas): array
    {
        $conteo = collect($filas)->countBy(fn (array $fila): string => $fila['clasificacion']['etiqueta']);
        $resumen = [];
        foreach (self::CATEGORIAS as $etiqueta => $colores) {
            $resumen[] = ['etiqueta' => $etiqueta, ...$colores, 'cantidad' => $conteo->get($etiqueta, 0)];
        }

        return $resumen;
    }

    /**
     * @param  list<array<string, mixed>>  $periodos
     * @return array{etiqueta: string, fondo: string, color: string, margen: ?float, meses_ventas: int, provisional: bool, deterioro: bool}
     */
    public function clasificar(array $periodos): array
    {
        $meses = collect($periodos)->sortBy('mes')->values();
        $mesesVentas = $meses->filter(fn (array $mes): bool => (float) ($mes['ventas'] ?? 0) > 0)->count();
        $ventas = (float) $meses->sum('ventas_tradicionales');
        $incompleto = $meses->isEmpty() || $meses->contains(fn (array $mes): bool => ($mes['balance_general'] ?? null) === null);
        $margen = $incompleto || $ventas <= 0 ? null : round($meses->sum('balance_general') / $ventas * 100, 2);
        $etiqueta = match (true) {
            $margen === null => 'Sin datos',
            $margen >= 15 => 'Rentables',
            $margen >= 5 => 'Buenas',
            $margen >= -35 => 'Para trabajar',
            default => 'Atención prioritaria',
        };

        return [
            'etiqueta' => $etiqueta, ...self::CATEGORIAS[$etiqueta],
            'margen' => $margen, 'meses_ventas' => $mesesVentas,
            'provisional' => $mesesVentas < 3,
            'deterioro' => $meses->count() >= 2 && $meses->take(-2)->every(fn (array $mes): bool => ($mes['balance_general'] ?? null) !== null && $mes['balance_general'] < 0),
        ];
    }
}
