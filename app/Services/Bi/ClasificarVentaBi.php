<?php

namespace App\Services\Bi;

use App\Models\CatalogoJuego;

class ClasificarVentaBi
{
    /** @var array<string, string>|null */
    private ?array $tiposPorProducto = null;

    /** @param array<string, mixed> $venta */
    public function categoria(array $venta): string
    {
        $productoId = trim((string) ($venta['producto_id'] ?? ''));
        $tipo = trim((string) ($this->tiposPorProducto()[$productoId] ?? ''));
        if ($tipo === '') {
            $tipo = (string) ($venta['tipo'] ?? '');
        }

        return self::normalizarTipo($tipo);
    }

    public static function normalizarTipo(string $tipo): string
    {
        return match (mb_strtolower(str_replace('_', ' ', trim($tipo)))) {
            'tradicional' => 'tradicional',
            'no tradicional' => 'no_tradicional',
            'externa', 'externas' => 'externas',
            'recarga', 'recargas' => 'recargas',
            default => 'otros',
        };
    }

    /** @return array<string, string> */
    private function tiposPorProducto(): array
    {
        if ($this->tiposPorProducto !== null) {
            return $this->tiposPorProducto;
        }

        $this->tiposPorProducto = [];
        foreach (CatalogoJuego::query()->get(['producto_id', 'tipo']) as $juego) {
            $productoId = trim((string) $juego->producto_id);
            if ($productoId !== '' && ! isset($this->tiposPorProducto[$productoId])) {
                $this->tiposPorProducto[$productoId] = (string) $juego->tipo;
            }
        }

        return $this->tiposPorProducto;
    }
}
