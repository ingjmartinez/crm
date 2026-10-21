<?php

namespace Database\Factories;

use App\Models\BiHorario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BiHorario>
 */
class BiHorarioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cedula = fake()->unique()->numerify('###########');

        return [
            'clave' => 'empleado:'.$cedula,
            'tipo' => 'empleado',
            'cedula' => $cedula,
            'terminal' => null,
            'entrada' => '08:00',
            'salida' => '17:00',
        ];
    }
}
