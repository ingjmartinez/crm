<?php

namespace Database\Seeders;

use App\Models\Agencia;
use App\Models\MonitoreoTerminalComentario;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MonitoreoTerminalComentarioSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        Agencia::query()
            ->orderBy('id')
            ->limit(3)
            ->get(['id'])
            ->each(function (Agencia $agencia): void {
                MonitoreoTerminalComentario::factory()->create([
                    'agencia_id' => $agencia->id,
                ]);
            });
    }
}
