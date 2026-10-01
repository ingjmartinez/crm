<?php

namespace App\Services\Bi;

use App\Models\Agencia;
use App\Models\BiVentaHora;
use App\Services\Lotobet\LotobetSessionService;
use Carbon\CarbonImmutable;
use RuntimeException;

class CapturarVentasHora
{
    public function __construct(
        private readonly LotobetSessionService $lotobet,
        private readonly ClasificarVentaBi $clasificador,
    ) {}

    public function capturar(CarbonImmutable $momento): BiVentaHora
    {
        $respuesta = $this->lotobet->getVentasProducto($momento->toDateString());
        $contenido = $respuesta['Content'] ?? null;

        if (! is_array($contenido)) {
            throw new RuntimeException('La API de LotoBet no devolvió un listado de ventas válido.');
        }

        $totales = [
            'tradicional' => 0.0,
            'no_tradicional' => 0.0,
            'externas' => 0.0,
            'recargas' => 0.0,
            'otros' => 0.0,
        ];
        $rutasPorTerminal = [];
        foreach (Agencia::query()->whereNotNull('terminal')->whereNotNull('ruta')->get(['terminal', 'ruta']) as $agencia) {
            $terminal = $this->normalizarTerminal((string) $agencia->terminal);
            $ruta = trim((string) $agencia->ruta);
            if ($terminal !== '' && $ruta !== '' && ! isset($rutasPorTerminal[$terminal])) {
                $rutasPorTerminal[$terminal] = $ruta;
            }
        }
        $terminalesEvaluadas = [];
        foreach (Agencia::lotobet()->where('estatus', 1)->whereNotNull('terminal')->get(['terminal']) as $agencia) {
            $terminal = $this->normalizarTerminal((string) $agencia->terminal);
            if ($terminal !== '') {
                $terminalesEvaluadas[$terminal] = true;
            }
        }
        $ventasPorRuta = [];
        $ventasPorTerminal = [];
        $quinielaLoteka = 0.0;
        $megaChance = 0.0;
        $productos = [];
        $productosTerminales = [];

        foreach ($contenido as $venta) {
            if (! is_array($venta) || ! is_numeric($venta['monto'] ?? null)) {
                throw new RuntimeException('La API de LotoBet devolvió una venta sin monto válido.');
            }

            if (isset($venta['fecha']) && $venta['fecha'] !== $momento->toDateString()) {
                throw new RuntimeException('La API de LotoBet devolvió ventas de otra fecha.');
            }

            $categoria = $this->clasificador->categoria($venta);
            $totales[$categoria] += (float) $venta['monto'];
            $productoId = trim((string) ($venta['producto_id'] ?? ''));
            if ($productoId !== '') {
                $productos[$productoId] = ($productos[$productoId] ?? 0) + (float) $venta['monto'];
            }
            if (trim((string) ($venta['producto_id'] ?? '')) === '43') {
                $quinielaLoteka += (float) $venta['monto'];
            }
            if (trim((string) ($venta['producto_id'] ?? '')) === '38') {
                $megaChance += (float) $venta['monto'];
            }

            $terminal = $this->normalizarTerminal((string) ($venta['agencia_id'] ?? ''));
            if ($terminal !== '' && $productoId !== '') {
                $productosTerminales[$terminal][$productoId] = ($productosTerminales[$terminal][$productoId] ?? 0) + (float) $venta['monto'];
            }
            if (isset($terminalesEvaluadas[$terminal])) {
                $ventasPorTerminal[$terminal][$categoria] = ($ventasPorTerminal[$terminal][$categoria] ?? 0) + (float) $venta['monto'];
            }
            $ruta = $rutasPorTerminal[$terminal] ?? null;
            if ($ruta !== null) {
                $ventasPorRuta[$ruta] = ($ventasPorRuta[$ruta] ?? 0) + (float) $venta['monto'];
            }
        }

        $terminalesConVenta = 0;
        $terminalesCategoria = array_fill_keys(array_keys($totales), 0);
        foreach ($ventasPorTerminal as $categorias) {
            $ventaLoterias = ($categorias['tradicional'] ?? 0)
                + ($categorias['no_tradicional'] ?? 0)
                + ($categorias['externas'] ?? 0);
            if ($ventaLoterias > 0) {
                $terminalesConVenta++;
            }

            foreach ($categorias as $categoria => $monto) {
                if ($monto > 0) {
                    $terminalesCategoria[$categoria]++;
                }
            }
        }

        arsort($ventasPorRuta, SORT_NUMERIC);
        $rutas = [];
        foreach ($ventasPorRuta as $nombre => $monto) {
            $rutas[] = ['nombre' => $nombre, 'monto' => round($monto, 2)];
        }

        $lectura = BiVentaHora::query()
            ->whereDate('fecha', $momento->toDateString())
            ->where('hora', $momento->hour)
            ->first() ?? new BiVentaHora(['fecha' => $momento->toDateString(), 'hora' => $momento->hour]);

        $lectura->fill([
            'tradicional_acumulado' => round($totales['tradicional'], 2),
            'no_tradicional_acumulado' => round($totales['no_tradicional'], 2),
            'externas_acumulado' => round($totales['externas'], 2),
            'recargas_acumulado' => round($totales['recargas'], 2),
            'otros_acumulado' => round($totales['otros'], 2),
            'registros' => count($contenido),
            'capturado_en' => $momento->toDateTimeString(),
            'rutas' => $rutas,
            'terminales_evaluadas' => count($terminalesEvaluadas),
            'terminales_con_venta' => $terminalesConVenta,
            'terminales_categoria' => $terminalesCategoria,
            'quiniela_loteka_acumulado' => round($quinielaLoteka, 2),
            'mega_chance_acumulado' => round($megaChance, 2),
            'productos' => array_map(fn (float $monto): float => round($monto, 2), $productos),
            'productos_terminales' => array_map(
                fn (array $ventas): array => array_map(fn (float $monto): float => round($monto, 2), $ventas),
                $productosTerminales,
            ),
        ]);
        $lectura->save();

        BiVentaHora::query()->whereDate('fecha', '!=', $momento->toDateString())->delete();

        return $lectura;
    }

    private function normalizarTerminal(string $terminal): string
    {
        $terminal = trim($terminal);
        if ($terminal === '') {
            return '';
        }

        return ltrim($terminal, '0') ?: '0';
    }
}
