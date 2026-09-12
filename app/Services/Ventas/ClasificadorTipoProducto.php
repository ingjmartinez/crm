<?php

namespace App\Services\Ventas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clasifica las ventas por producto en tradicional / no tradicional usando el catalogo de juegos.
 */
class ClasificadorTipoProducto
{
    public const TRADICIONAL = 'tradicional';

    public const NO_TRADICIONAL = 'no_tradicional';

    public const OTROS = 'otros';

    /** @var array<string, string>|null */
    private ?array $mapa = null;

    /**
     * @param  array<string, mixed>  $venta
     */
    public function clasificar(array $venta): string
    {
        $mapa = $this->mapa();

        $productoId = trim((string) ($venta['producto_id'] ?? ''));
        if ($productoId !== '') {
            $porId = $mapa['id:'.$productoId] ?? null;
            if ($porId !== null) {
                return $porId;
            }
        }

        $descripcion = $this->normalizarTexto((string) ($venta['descripcion'] ?? ''));
        if ($descripcion !== '') {
            $porDescripcion = $mapa['desc:'.$descripcion] ?? null;
            if ($porDescripcion !== null) {
                return $porDescripcion;
            }
        }

        return $this->normalizarTipo((string) ($venta['tipo'] ?? ''));
    }

    /**
     * @param  array<int, array<string, mixed>>  $ventas
     * @return array<int, array<string, mixed>>
     */
    public function enriquecer(array $ventas): array
    {
        return array_map(function ($venta): array {
            $venta = is_array($venta) ? $venta : (array) $venta;
            $venta['tipo_categoria'] = $this->clasificar($venta);

            return $venta;
        }, $ventas);
    }

    /**
     * @return array<string, string>
     */
    private function mapa(): array
    {
        if ($this->mapa !== null) {
            return $this->mapa;
        }

        $this->mapa = [];

        if (! Schema::hasTable('catalogo_juegos')) {
            return $this->mapa;
        }

        $filas = DB::table('catalogo_juegos')->select(['producto_id', 'tipo', 'descripcion'])->get();

        foreach ($filas as $fila) {
            $tipo = $this->normalizarTipo((string) ($fila->tipo ?? ''));
            if ($tipo === self::OTROS) {
                continue;
            }

            $productoId = trim((string) ($fila->producto_id ?? ''));
            if ($productoId !== '') {
                $this->mapa['id:'.$productoId] = $tipo;
            }

            $descripcion = $this->normalizarTexto((string) ($fila->descripcion ?? ''));
            if ($descripcion !== '') {
                $this->mapa['desc:'.$descripcion] = $tipo;
            }
        }

        return $this->mapa;
    }

    private function normalizarTipo(string $tipo): string
    {
        return match (mb_strtolower(trim($tipo))) {
            'tradicional' => self::TRADICIONAL,
            'no tradicional', 'no_tradicional' => self::NO_TRADICIONAL,
            default => self::OTROS,
        };
    }

    private function normalizarTexto(string $texto): string
    {
        $valor = mb_strtolower(trim($texto));

        return preg_replace('/\s+/', ' ', $valor) ?? $valor;
    }
}
