<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitoreoTerminalAgenciaPlaza>
 */
class MonitoreoTerminalAgenciaPlazaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agencia_id' => fake()->unique()->numberBetween(1, 1000000),
            'usuario_id' => null,
        ];
    }
}
