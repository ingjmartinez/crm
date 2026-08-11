<?php

namespace Database\Seeders;

use App\Models\Agencia;
use App\Models\LegalContrato;
use Illuminate\Database\Seeder;

class LegalContratoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $agencia = Agencia::query()->first();

        if ($agencia === null) {
            return;
        }

        LegalContrato::factory()->for($agencia)->create();
    }
}
