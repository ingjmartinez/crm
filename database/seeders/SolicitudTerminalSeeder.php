<?php

namespace Database\Seeders;

use App\Models\SolicitudTerminal;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SolicitudTerminalSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SolicitudTerminal::factory()
            ->count(3)
            ->create()
            ->each(function (SolicitudTerminal $solicitud): void {
                $solicitud->codigos()->createMany(
                    collect(range(1, $solicitud->cantidad))->map(fn (int $secuencia): array => [
                        'codigo' => '05'.$solicitud->prefijo_seleccionado.str_pad((string) $secuencia, 4, '0', STR_PAD_LEFT),
                        'estado' => 'pendiente',
                    ])->all()
                );
            });
    }
}
