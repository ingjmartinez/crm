<?php

namespace Database\Factories;

use App\Models\CentroCostoCuentaRuta;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CentroCostoCuentaRuta> */
class CentroCostoCuentaRutaFactory extends Factory
{
    public function definition(): array
    {
        return ['company_id' => '168', 'ruta_key' => fake()->unique()->numerify('#####'), 'cuenta_ruta_id' => null];
    }
}
