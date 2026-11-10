<?php

namespace Database\Factories;

use App\Models\SelectorAgenciaSeleccion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SelectorAgenciaSeleccion>
 */
class SelectorAgenciaSeleccionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'terminales' => [],
        ];
    }
}
