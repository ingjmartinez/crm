<?php

namespace Database\Factories;

use App\Models\BiLimiteProducto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BiLimiteProducto>
 */
class BiLimiteProductoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'producto_id' => (string) fake()->unique()->numberBetween(1, 9999),
            'monto' => fake()->randomFloat(2, 100, 100000),
            'activo' => true,
            'terminal' => '',
        ];
    }
}
