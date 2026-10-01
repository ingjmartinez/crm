<?php

namespace App\Services\Bi;

use App\Models\BiPremioDia;
use App\Models\BiVentaDia;
use Carbon\CarbonImmutable;

class VentasPremiosPeriodo
{
    /**
     * @return array{
     *     ventas: float,
     *     premios: float,
     *     utilidad: float,
     *     tasaPremiacion: float,
     *     desde: ?string,
     *     hasta: ?string,
     *     serie: array<int, array{fecha: string, etiqueta: string, ventas: float, premios: float, utilidad: float, tasaPremiacion: float}>
     * }
     */
    public function resumir(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $ultimoDiaCerrado = CarbonImmutable::yesterday();
        $hastaEfectivo = $hasta->min($ultimoDiaCerrado);

        if ($desde->greaterThan($hastaEfectivo)) {
            return $this->vacio();
        }

        $ventasPorFecha = BiVentaDia::query()
            ->where('fecha', '>=', $desde->toDateString())
            ->where('fecha', '<', $hastaEfectivo->addDay()->toDateString())
            ->get(['fecha', 'tradicional', 'no_tradicional', 'externas', 'recargas', 'otros'])
            ->mapWithKeys(fn (BiVentaDia $dia): array => [
                $dia->fecha->toDateString() => (float) $dia->tradicional
                    + (float) $dia->no_tradicional
                    + (float) $dia->externas
                    + (float) $dia->recargas
                    + (float) $dia->otros,
            ]);

        $premiosPorFecha = BiPremioDia::query()
            ->where('fecha', '>=', $desde->toDateString())
            ->where('fecha', '<', $hastaEfectivo->addDay()->toDateString())
            ->get(['fecha', 'premios'])
            ->mapWithKeys(fn (BiPremioDia $dia): array => [
                $dia->fecha->toDateString() => (float) $dia->premios,
            ]);

        $serie = [];
        for ($fecha = $desde; $fecha->lessThanOrEqualTo($hastaEfectivo); $fecha = $fecha->addDay()) {
            $dia = $fecha->toDateString();
            $ventas = (float) ($ventasPorFecha[$dia] ?? 0);
            $premios = (float) ($premiosPorFecha[$dia] ?? 0);

            $serie[] = [
                'fecha' => $dia,
                'etiqueta' => $fecha->format('m-d'),
                'ventas' => $ventas,
                'premios' => $premios,
                'utilidad' => $ventas - $premios,
                'tasaPremiacion' => $ventas > 0 ? ($premios / $ventas) * 100 : 0.0,
            ];
        }

        $ventas = array_sum(array_column($serie, 'ventas'));
        $premios = array_sum(array_column($serie, 'premios'));

        return [
            'ventas' => $ventas,
            'premios' => $premios,
            'utilidad' => $ventas - $premios,
            'tasaPremiacion' => $ventas > 0 ? ($premios / $ventas) * 100 : 0.0,
            'desde' => $desde->toDateString(),
            'hasta' => $hastaEfectivo->toDateString(),
            'serie' => $serie,
        ];
    }

    /** @return array{ventas: float, premios: float, utilidad: float, tasaPremiacion: float, desde: null, hasta: null, serie: array<int, never>} */
    private function vacio(): array
    {
        return [
            'ventas' => 0.0,
            'premios' => 0.0,
            'utilidad' => 0.0,
            'tasaPremiacion' => 0.0,
            'desde' => null,
            'hasta' => null,
            'serie' => [],
        ];
    }
}
