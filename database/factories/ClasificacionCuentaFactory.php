<?php

namespace Database\Factories;

use App\Models\ClasificacionCuenta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClasificacionCuenta>
 */
class ClasificacionCuentaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cuenta' => fake()->unique()->numerify('#########'),
            'clasificacion' => 'VARIABLE',
            'criterio' => 'VARIABLE',
            'observacion' => null,
            'activo' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}
