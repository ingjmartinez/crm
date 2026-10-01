<?php

namespace Database\Factories;

use App\Models\BiVentaDia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BiVentaDia>
 */
class BiVentaDiaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fecha' => fake()->unique()->dateTimeBetween('-2 years', '-1 day')->format('Y-m-d'),
            'tradicional' => fake()->randomFloat(2, 0, 100000),
            'no_tradicional' => fake()->randomFloat(2, 0, 100000),
            'externas' => 0,
            'recargas' => 0,
            'otros' => 0,
            'registros' => fake()->numberBetween(1, 100),
            'origen' => 'vt_usuarios_bet',
            'resumido_en' => now(),
        ];
    }
}
