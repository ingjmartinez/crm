<?php

namespace Database\Factories;

use App\Models\BiVentaHora;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BiVentaHora>
 */
class BiVentaHoraFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fecha' => now()->toDateString(),
            'hora' => now()->hour,
            'tradicional_acumulado' => fake()->randomFloat(2, 0, 100000),
            'no_tradicional_acumulado' => fake()->randomFloat(2, 0, 100000),
            'otros_acumulado' => 0,
            'externas_acumulado' => 0,
            'recargas_acumulado' => 0,
            'registros' => fake()->numberBetween(1, 100),
            'capturado_en' => now(),
            'rutas' => [],
        ];
    }
}
