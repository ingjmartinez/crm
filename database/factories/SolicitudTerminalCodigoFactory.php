<?php

namespace Database\Factories;

use App\Models\SolicitudTerminal;
use App\Models\SolicitudTerminalCodigo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SolicitudTerminalCodigo>
 */
class SolicitudTerminalCodigoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'solicitud_terminal_id' => SolicitudTerminal::factory(),
            'codigo' => fake()->unique()->numerify('05######'),
            'estado' => 'pendiente',
            'aprobado_por' => null,
            'aprobado_at' => null,
        ];
    }
}
