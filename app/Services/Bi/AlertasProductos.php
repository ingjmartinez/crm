<?php

namespace App\Services\Bi;

use App\Models\BiLimiteProducto;
use App\Models\BiVentaHora;
use App\Models\BiVentaSnapshot;

class AlertasProductos
{
    public function __construct(private ClasificarVentaBi $clasificador) {}

    /** @return array<int, array<string, mixed>> */
    public function resumir(?BiVentaHora $lectura, ?BiVentaSnapshot $snapshot = null): array
    {
        $productosTerminales = $snapshot?->productos_terminales;

        return BiLimiteProducto::query()->with('producto')->orderBy('producto_id')->orderBy('terminal')->get()
            ->map(function (BiLimiteProducto $limite) use ($lectura, $productosTerminales): array {
                $terminal = (string) $limite->terminal;
                $productos = $terminal === '' ? $lectura?->productos : ($productosTerminales !== null ? ($productosTerminales[$terminal] ?? []) : null);
                $grupo = BiLimiteProducto::GRUPOS[$limite->producto_id] ?? null;
                if ($grupo !== null) {
                    $categoria = substr($limite->producto_id, strlen('grupo:'));
                    $ventas = $terminal === ''
                        ? ($lectura !== null ? (float) $lectura->getAttribute($categoria.'_acumulado') : null)
                        : ($productos !== null ? $this->sumarCategoria($productos, $categoria) : null);
                } else {
                    $ventas = $productos !== null ? (float) ($productos[$limite->producto_id] ?? 0) : null;
                }
                $porcentaje = $ventas !== null ? round($ventas / (float) $limite->monto * 100, 1) : null;

                return [
                    'id' => $limite->id,
                    'eliminarUrl' => route('bi.limites-productos.eliminar', $limite),
                    'terminal' => $terminal,
                    'alcance' => $terminal === '' ? 'global' : 'terminal',
                    'producto_id' => $limite->producto_id,
                    'esGrupo' => $grupo !== null,
                    'nombre' => $grupo ?? ($limite->producto?->descripcion ?: "Producto {$limite->producto_id}"),
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

    /** @param array<string, int|float> $productos */
    private function sumarCategoria(array $productos, string $categoria): float
    {
        $total = 0.0;
        foreach ($productos as $productoId => $monto) {
            if ($this->clasificador->categoria(['producto_id' => $productoId]) === $categoria) {
                $total += (float) $monto;
            }
        }

        return round($total, 2);
    }
}
