<?php

namespace App\Services\Bi;

use App\Models\BiVentaDia;
use App\Models\BiVentaHora;
use Carbon\CarbonImmutable;

class VentasBetPorCategoria
{
    /** @return array{desde: ?string, hasta: ?string} */
    public function rangoDisponible(): array
    {
        $rango = BiVentaDia::query()
            ->selectRaw('MIN(fecha) AS desde, MAX(fecha) AS hasta')
            ->first();
        $hoy = today()->toDateString();
        $hayLecturaHoy = BiVentaHora::query()->whereDate('fecha', $hoy)->exists();

        return [
            'desde' => $rango?->desde ? substr((string) $rango->desde, 0, 10) : ($hayLecturaHoy ? $hoy : null),
            'hasta' => $hayLecturaHoy ? $hoy : ($rango?->hasta ? substr((string) $rango->hasta, 0, 10) : null),
        ];
    }

    /**
     * @return array{
     *     total: float, tradicional: float, no_tradicional: float, externas: float, recargas: float, otros: float,
     *     desde: ?string, hasta: ?string, diasSinDatos: int,
     *     serie: array<int, array{fecha: string, tradicional: float, no_tradicional: float, externas: float, recargas: float, otros: float, total: float}>
     * }
     */
    public function resumir(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $resumen = [
            'total' => 0.0,
            'tradicional' => 0.0,
            'no_tradicional' => 0.0,
            'externas' => 0.0,
            'recargas' => 0.0,
            'otros' => 0.0,
            'desde' => null,
            'hasta' => null,
            'diasSinDatos' => 0,
            'serie' => [],
        ];
        $ultimoDiaCerrado = min($hasta->toDateString(), today()->subDay()->toDateString());

        if ($desde->toDateString() <= $ultimoDiaCerrado) {
            $resumen['desde'] = $desde->toDateString();
            $resumen['hasta'] = $ultimoDiaCerrado;
            $dias = BiVentaDia::query()
                ->where('fecha', '>=', $desde->toDateString())
                ->where('fecha', '<', CarbonImmutable::parse($ultimoDiaCerrado)->addDay()->toDateString())
                ->orderBy('fecha')
                ->get(['fecha', 'tradicional', 'no_tradicional', 'externas', 'recargas', 'otros', 'registros']);

            $diasConDatos = 0;
            foreach ($dias as $dia) {
                $valores = [];
                foreach (['tradicional', 'no_tradicional', 'externas', 'recargas', 'otros'] as $categoria) {
                    $valores[$categoria] = (float) $dia->{$categoria};
                    $resumen[$categoria] += $valores[$categoria];
                }
                $resumen['serie'][] = [
                    'fecha' => $dia->fecha->toDateString(),
                    ...$valores,
                    'total' => array_sum($valores),
                ];
                if ($dia->registros > 0) {
                    $diasConDatos++;
                }
            }

            $diasEsperados = (int) $desde->diffInDays(CarbonImmutable::parse($ultimoDiaCerrado)) + 1;
            $resumen['diasSinDatos'] = max(0, $diasEsperados - $diasConDatos);
        }

        $resumen['total'] = $resumen['tradicional'] + $resumen['no_tradicional'] + $resumen['externas'] + $resumen['recargas'] + $resumen['otros'];

        return $resumen;
    }
}
