<?php

namespace Database\Factories;

use App\Models\LegalObligacion;
use App\Models\LegalPagoProgramado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalPagoProgramado>
 */
class LegalPagoProgramadoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legal_obligacion_id' => LegalObligacion::factory(),
            'periodo' => today()->startOfMonth(),
            'fecha_vencimiento' => today(),
            'monto' => fake()->randomFloat(2, 500, 50000),
            'estado' => 'pendiente',
        ];
    }
}
