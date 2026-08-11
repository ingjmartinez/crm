<?php

namespace Database\Seeders;

use App\Models\LegalContrato;
use App\Models\LegalObligacion;
use Illuminate\Database\Seeder;

class LegalObligacionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $contrato = LegalContrato::query()->first();

        if ($contrato === null) {
            return;
        }

        LegalObligacion::factory()->for($contrato, 'contrato')->create();
    }
}
