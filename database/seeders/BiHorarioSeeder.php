<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BiHorarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\BiHorario::factory()->count(10)->create();
    }
}
