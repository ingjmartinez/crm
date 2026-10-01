<?php

namespace App\Services\Bi;

use App\Models\BiLimiteProducto;
use App\Models\BiVentaHora;

class AlertasProductos
{
    /** @return array<int, array<string, mixed>> */
    public function resumir(?BiVentaHora $lectura): array
    {
        return BiLimiteProducto::query()->with('producto')->orderBy('producto_id')->orderBy('terminal')->get()
            ->map(function (BiLimiteProducto $limite) use ($lectura): array {
                $terminal = (string) $limite->terminal;
                $productos = $terminal === '' ? $lectura?->productos : ($lectura?->productos_terminales !== null ? ($lectura->productos_terminales[$terminal] ?? []) : null);
                $ventas = $productos !== null
                    ? (float) ($productos[$limite->producto_id] ?? 0)
                    : null;
                $porcentaje = $ventas !== null ? round($ventas / (float) $limite->monto * 100, 1) : null;

                return [
                    'id' => $limite->id,
                    'terminal' => $terminal,
                    'alcance' => $terminal === '' ? 'global' : 'terminal',
                    'producto_id' => $limite->producto_id,
                    'nombre' => $limite->producto?->descripcion ?: "Producto {$limite->producto_id}",
                    'monto' => (float) $limite->monto,
                    'activo' => $limite->activo,
                    'ventas' => $ventas,
                    'porcentaje' => $porcentaje,
                    'estado' => ! $limite->activo ? 'Pausada' : ($ventas === null ? 'Sin lectura' : ($ventas >= (float) $limite->monto ? 'Límite alcanzado' : 'Dentro del límite')),
                    'alerta' => $limite->activo && $ventas !== null && $ventas >= (float) $limite->monto,
                    'capturadoEn' => $lectura?->capturado_en->toDateTimeString(),
                ];
            })->all();
    }
}
