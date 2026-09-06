<?php

namespace Database\Seeders;

use App\Models\SolicitudTerminal;
use Illuminate\Database\Seeder;

class SolicitudTerminalCodigoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $solicitud = SolicitudTerminal::factory()->create([
            'prefijo_seleccionado' => '77',
            'cantidad' => 20,
        ]);

        $solicitud->codigos()->createMany(
            collect(range(1, 20))->map(fn (int $secuencia): array => [
                'codigo' => '0577'.str_pad((string) $secuencia, 4, '0', STR_PAD_LEFT),
                'estado' => 'pendiente',
            ])->all()
        );
    }
}
