<?php

namespace Database\Seeders;

use App\Models\BiLimiteProducto;
use App\Models\CatalogoJuego;
use Illuminate\Database\Seeder;

class BiLimiteProductoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (CatalogoJuego::query()->whereIn('producto_id', ['43', '38'])->get() as $producto) {
            BiLimiteProducto::query()->firstOrCreate(
                ['producto_id' => $producto->producto_id, 'terminal' => ''],
                ['monto' => 100000, 'activo' => false],
            );
        }
    }
}
