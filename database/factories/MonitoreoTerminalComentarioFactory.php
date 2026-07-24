<?php

namespace Database\Factories;

use App\Models\MonitoreoTerminalComentario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitoreoTerminalComentario>
 */
class MonitoreoTerminalComentarioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agencia_id' => fake()->numberBetween(1, 1000),
            'usuario_id' => null,
            'comentario' => fake()->sentence(),
            'fecha' => today(),
        ];
    }
}
