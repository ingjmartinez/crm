<?php

namespace Database\Factories;

use App\Models\MonitoreoTerminalHorario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitoreoTerminalHorario>
 */
class MonitoreoTerminalHorarioFactory extends Factory
{
    protected $model = MonitoreoTerminalHorario::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hora' => fake()->unique()->time('H:i'),
            'tipo_horario' => fake()->randomElement(['AM', 'PM']),
            'activo' => true,
        ];
    }
}
