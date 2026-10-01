<?php

namespace Database\Factories;

use App\Models\BiPremioDia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BiPremioDia>
 */
class BiPremioDiaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fecha' => fake()->unique()->date(),
            'premios' => fake()->randomFloat(2, 0, 10_000_000),
            'registros' => fake()->numberBetween(0, 10_000),
            'origen' => 'premios_bet',
            'resumido_en' => now(),
        ];
    }
}
