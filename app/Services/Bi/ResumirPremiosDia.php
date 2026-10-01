<?php

namespace App\Services\Bi;

use App\Models\BiPremioDia;
use App\Models\Premio;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ResumirPremiosDia
{
    public function resumir(CarbonImmutable $fecha): BiPremioDia
    {
        $dia = $fecha->toDateString();
        $totales = Premio::query()
            ->whereDate('fecha', $dia)
            ->selectRaw('COALESCE(SUM(monto), 0) AS premios, COUNT(*) AS registros')
            ->firstOrFail();

        $resumen = BiPremioDia::query()->whereDate('fecha', $dia)->first() ?? new BiPremioDia(['fecha' => $dia]);
        $resumen->fill([
            'premios' => (float) $totales->premios,
            'registros' => (int) $totales->registros,
            'origen' => 'premios_bet',
            'resumido_en' => now(),
        ])->save();

        return $resumen;
    }

    public function resumirRango(CarbonImmutable $desde, CarbonImmutable $hasta): int
    {
        $premiosPorFecha = Premio::query()
            ->where('fecha', '>=', $desde->toDateString())
            ->where('fecha', '<', $hasta->addDay()->toDateString())
            ->select('fecha')
            ->selectRaw('COALESCE(SUM(monto), 0) AS premios, COUNT(*) AS registros')
            ->groupBy('fecha')
            ->get()
            ->keyBy(fn (Premio $premio): string => $premio->fecha->toDateString());
        $ahora = now()->toDateTimeString();
        $resumenes = [];

        for ($fecha = $desde; $fecha->lessThanOrEqualTo($hasta); $fecha = $fecha->addDay()) {
            $dia = $fecha->toDateString();
            $premio = $premiosPorFecha->get($dia);
            $resumenes[] = [
                'fecha' => $dia,
                'premios' => (float) ($premio?->premios ?? 0),
                'registros' => (int) ($premio?->registros ?? 0),
                'origen' => 'premios_bet',
                'resumido_en' => $ahora,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        collect($resumenes)->chunk(500)->each(fn ($bloque) => DB::table('bi_premio_dias')->upsert(
            $bloque->all(),
            ['fecha'],
            ['premios', 'registros', 'origen', 'resumido_en', 'updated_at'],
        ));

        return count($resumenes);
    }
}
