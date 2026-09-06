<?php

namespace Database\Factories;

use App\Models\SolicitudTerminal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SolicitudTerminal>
 */
class SolicitudTerminalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'solicitado_por' => User::factory(),
            'prefijo_empresa' => '05',
            'prefijo_seleccionado' => fake()->numerify('##'),
            'cantidad' => 20,
            'estado' => 'pendiente',
        ];
    }
}
