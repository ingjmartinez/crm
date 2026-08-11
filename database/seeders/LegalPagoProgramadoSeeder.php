<?php

namespace Database\Seeders;

use App\Models\LegalObligacion;
use App\Models\LegalPagoProgramado;
use Illuminate\Database\Seeder;

class LegalPagoProgramadoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $obligacion = LegalObligacion::query()->first();

        if ($obligacion === null) {
            return;
        }

        LegalPagoProgramado::factory()->for($obligacion, 'obligacion')->create();
    }
}
