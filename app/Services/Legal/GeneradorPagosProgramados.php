<?php

namespace App\Services\Legal;

use App\Models\LegalObligacion;
use Carbon\Carbon;

class GeneradorPagosProgramados
{
    public function generar(LegalObligacion $obligacion): int
    {
        $obligacion->loadMissing('contrato');
        $fechaInicial = $obligacion->fecha_primer_pago->copy();
        $fechaLimite = $obligacion->fecha_fin
            ?? $obligacion->contrato?->fecha_fin
            ?? $fechaInicial->copy()->addMonths(11);

        if ($obligacion->fecha_fin && $obligacion->contrato?->fecha_fin) {
            $fechaLimite = $obligacion->fecha_fin->min($obligacion->contrato->fecha_fin);
        }
        $mesesPorPeriodo = match ($obligacion->frecuencia) {
            'mensual' => 1,
            'trimestral' => 3,
            'semestral' => 6,
            'anual' => 12,
            default => null,
        };
        $creados = 0;

        for ($periodo = 0; $periodo < 240; $periodo++) {
            $fechaVencimiento = $mesesPorPeriodo === null
                ? $fechaInicial->copy()
                : $fechaInicial->copy()->addMonthsNoOverflow($periodo * $mesesPorPeriodo);

            if ($fechaVencimiento->greaterThan($fechaLimite)) {
                break;
            }

            $pago = $obligacion->pagosProgramados()->firstOrCreate(
                ['fecha_vencimiento' => $fechaVencimiento->toDateString()],
                [
                    'periodo' => Carbon::parse($fechaVencimiento)->startOfMonth()->toDateString(),
                    'monto' => $obligacion->monto,
                    'estado' => 'pendiente',
                ],
            );

            if ($pago->wasRecentlyCreated) {
                $creados++;
            }

            if ($mesesPorPeriodo === null) {
                break;
            }
        }

        return $creados;
    }
}
