<?php

namespace App\Http\Controllers\Bi;

use App\Http\Controllers\Controller;
use App\Models\BiVentaHora;
use App\Models\BiVentaSnapshot;
use App\Models\VentaOnlinePromedioHistorico;
use Illuminate\View\View;

class TvDashboardController extends Controller
{
    public function index(): View
    {
        $fecha = today()->toDateString();
        $fechaAnterior = today()->subDays(7)->toDateString();
        $lecturas = BiVentaHora::query()->where('fecha', $fecha)->whereBetween('hora', [6, 22])->orderBy('hora')->get();
        $lecturasAnteriores = BiVentaHora::query()->where('fecha', $fechaAnterior)->whereBetween('hora', [6, 22])->orderBy('hora')->get()->keyBy('hora');
        $ultima = $lecturas->last();
        $total = $ultima ? $this->totalHora($ultima) : 0.0;
        $maximo = max(1.0, $total, ...$lecturasAnteriores->map(fn (BiVentaHora $lectura): float => $this->totalHora($lectura))->all());
        $pasoBase = 10 ** floor(log10($maximo / 5));
        $paso = collect([1, 2, 2.5, 5, 10])->map(fn (float|int $factor): float => $factor * $pasoBase)
            ->first(fn (float $candidato): bool => ceil($maximo / $candidato) <= 5) ?? 10 * $pasoBase;
        $tope = $paso * ceil($maximo / $paso);
        $coordenadas = fn (BiVentaHora $lectura): array => [
            'x' => 70 + (($lectura->hora - 6) * 49),
            'y' => 190 - (($this->totalHora($lectura) / $tope) * 160),
        ];
        $puntosHoy = $lecturas->map(function (BiVentaHora $lectura) use ($coordenadas, $lecturasAnteriores): array {
            $anterior = $lecturasAnteriores->get($lectura->hora);
            $totalAnterior = $anterior ? $this->totalHora($anterior) : 0.0;

            return [
                'hora' => $lectura->hora,
                'total' => $this->totalHora($lectura),
                'porcentaje' => $totalAnterior > 0 ? (($this->totalHora($lectura) / $totalAnterior) - 1) * 100 : null,
                ...$coordenadas($lectura),
            ];
        });
        $puntosAnteriores = $lecturasAnteriores->values()->map(fn (BiVentaHora $lectura): array => [
            'hora' => $lectura->hora,
            'total' => $this->totalHora($lectura),
            ...$coordenadas($lectura),
        ]);
        $promedios = VentaOnlinePromedioHistorico::query()->whereIn('tipo_categoria', ['tradicional', 'no_tradicional'])
            ->get(['tipo_categoria', 'monto_promedio'])->pluck('monto_promedio', 'tipo_categoria');
        $snapshot = $ultima ? BiVentaSnapshot::query()->where('fecha', $fecha)->first(['rutas']) : null;

        return view('bi.tv', [
            'fecha' => $fecha,
            'fechaAnterior' => $fechaAnterior,
            'lecturas' => $lecturas,
            'ultima' => $ultima,
            'total' => $total,
            'tope' => $tope,
            'puntosHoy' => $puntosHoy,
            'puntosAnteriores' => $puntosAnteriores,
            'promedios' => $promedios,
            'rutas' => collect($snapshot?->rutas ?? [])->take(10),
        ]);
    }

    private function totalHora(BiVentaHora $lectura): float
    {
        return (float) $lectura->tradicional_acumulado + (float) $lectura->no_tradicional_acumulado
            + (float) $lectura->externas_acumulado + (float) $lectura->recargas_acumulado
            + (float) $lectura->otros_acumulado;
    }
}
