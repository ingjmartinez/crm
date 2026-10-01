<?php

namespace App\Services\Bi;

use App\Models\Agencia;
use App\Models\BiPremioDia;
use App\Models\BiVentaDia;
use App\Models\BiVentaHora;
use App\Models\Premio;
use Carbon\CarbonImmutable;

class IndicadoresBi
{
    /**
     * @return array<string, mixed>
     */
    public function resumir(CarbonImmutable $desde, CarbonImmutable $hasta, ?BiVentaHora $lectura): array
    {
        $ayer = CarbonImmutable::yesterday();
        $diasComparativos = BiVentaDia::query()
            ->whereBetween('fecha', [$ayer->subDays(13)->toDateString(), $ayer->toDateString()])
            ->where('registros', '>', 0)
            ->get(['fecha', 'tradicional', 'no_tradicional', 'externas', 'recargas', 'otros']);
        $semanaActual = $diasComparativos->filter(fn (BiVentaDia $dia): bool => $dia->fecha->greaterThanOrEqualTo($ayer->subDays(6)));
        $semanaAnterior = $diasComparativos->filter(fn (BiVentaDia $dia): bool => $dia->fecha->lessThan($ayer->subDays(6)));
        $totalSemanaAnterior = $semanaAnterior->sum(fn (BiVentaDia $dia): float => $this->totalDia($dia));
        $tendencia = $semanaActual->count() === 7 && $semanaAnterior->count() === 7 && $totalSemanaAnterior > 0
            ? (($semanaActual->sum(fn (BiVentaDia $dia): float => $this->totalDia($dia)) / $totalSemanaAnterior) - 1) * 100
            : null;
        $resumenAyer = $diasComparativos->first(fn (BiVentaDia $dia): bool => $dia->fecha->isSameDay($ayer));

        $ultimoDiaCerrado = $hasta->min($ayer);
        $diasEsperados = $desde->lessThanOrEqualTo($ultimoDiaCerrado)
            ? (int) $desde->diffInDays($ultimoDiaCerrado) + 1
            : 0;
        $diasPeriodo = $diasEsperados > 0
            ? BiVentaDia::query()
                ->whereBetween('fecha', [$desde->toDateString(), $ultimoDiaCerrado->toDateString()])
                ->where('registros', '>', 0)
                ->get(['tradicional', 'no_tradicional', 'externas', 'recargas', 'otros'])
            : collect();
        $diasConDatos = $diasPeriodo->count();
        $terminalesEvaluadas = $lectura?->terminales_evaluadas;
        $terminalesConVenta = $lectura?->terminales_con_venta;
        $terminalesCategoria = $lectura?->terminales_categoria;
        $hoy = CarbonImmutable::today();
        $mesAnterior = $hoy->subMonthNoOverflow();
        $fechasComparativas = [$ayer->toDateString(), $mesAnterior->toDateString()];
        $ventasComparativas = BiVentaDia::query()
            ->whereIn('fecha', $fechasComparativas)
            ->get(['fecha', 'tradicional', 'no_tradicional', 'externas', 'recargas', 'otros', 'registros'])
            ->keyBy(fn (BiVentaDia $dia): string => $dia->fecha->toDateString());
        $premiosComparativos = BiPremioDia::query()
            ->whereIn('fecha', $fechasComparativas)
            ->get(['fecha', 'premios'])
            ->keyBy(fn (BiPremioDia $dia): string => $dia->fecha->toDateString());
        $premiosHoy = Premio::query()
            ->whereDate('fecha', $hoy->toDateString())
            ->selectRaw('COUNT(*) AS registros, COALESCE(SUM(monto), 0) AS premios')
            ->first();
        $comparativo = [
            'hoy' => $this->comparativo($hoy->toDateString(), $lectura !== null ? $this->totalHora($lectura) : null, (int) ($premiosHoy?->registros ?? 0) > 0 ? (float) $premiosHoy->premios : null, Agencia::query()->lotobet()->where('estatus', 1)->count()),
        ];
        foreach (['ayer' => $ayer, 'mesAnterior' => $mesAnterior] as $periodo => $fecha) {
            $dia = $ventasComparativas->get($fecha->toDateString());
            $premio = $premiosComparativos->get($fecha->toDateString());
            $comparativo[$periodo] = $this->comparativo(
                $fecha->toDateString(),
                $dia !== null && $dia->registros > 0 ? $this->totalDia($dia) : null,
                $premio !== null ? (float) $premio->premios : null,
                null,
            );
        }

        return [
            'ventasHoy' => $lectura !== null ? $this->totalHora($lectura) : null,
            'horaLectura' => $lectura?->hora,
            'comparativoDia' => $comparativo,
            'ventasAyer' => $resumenAyer !== null ? $this->totalDia($resumenAyer) : null,
            'terminalesEvaluadas' => $terminalesEvaluadas,
            'terminalesConVenta' => $terminalesConVenta,
            'terminalesSinVenta' => $terminalesEvaluadas !== null && $terminalesConVenta !== null
                ? max(0, $terminalesEvaluadas - $terminalesConVenta)
                : null,
            'coberturaCategorias' => [
                'tradicional' => $terminalesCategoria['tradicional'] ?? null,
                'no_tradicional' => $terminalesCategoria['no_tradicional'] ?? null,
                'externas' => $lectura !== null && (float) $lectura->externas_acumulado > 0 ? ($terminalesCategoria['externas'] ?? null) : null,
                'recargas' => $lectura !== null && (float) $lectura->recargas_acumulado > 0 ? ($terminalesCategoria['recargas'] ?? null) : null,
            ],
            'tendencia7d' => $tendencia,
            'diasTendenciaActual' => $semanaActual->count(),
            'diasTendenciaAnterior' => $semanaAnterior->count(),
            'promedioVentasDiarias' => $diasConDatos > 0
                ? $diasPeriodo->sum(fn (BiVentaDia $dia): float => $this->totalDia($dia)) / $diasConDatos
                : null,
            'diasPromedioConDatos' => $diasConDatos,
            'diasPromedioEsperados' => $diasEsperados,
        ];
    }

    private function totalDia(BiVentaDia $dia): float
    {
        return (float) $dia->tradicional
            + (float) $dia->no_tradicional
            + (float) $dia->externas
            + (float) $dia->recargas
            + (float) $dia->otros;
    }

    private function totalHora(BiVentaHora $lectura): float
    {
        return (float) $lectura->tradicional_acumulado
            + (float) $lectura->no_tradicional_acumulado
            + (float) $lectura->externas_acumulado
            + (float) $lectura->recargas_acumulado
            + (float) $lectura->otros_acumulado;
    }

    /**
     * @return array{fecha: string, ventas: ?float, premios: ?float, resultado: ?float, tasa: ?float, agenciasActivas: ?int}
     */
    private function comparativo(string $fecha, ?float $ventas, ?float $premios, ?int $agenciasActivas): array
    {
        return [
            'fecha' => $fecha,
            'ventas' => $ventas,
            'premios' => $premios,
            'resultado' => $ventas !== null && $premios !== null ? $ventas - $premios : null,
            'tasa' => $ventas !== null && $ventas > 0 && $premios !== null ? ($premios / $ventas) * 100 : null,
            'agenciasActivas' => $agenciasActivas,
        ];
    }
}
