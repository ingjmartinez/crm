<?php

namespace Database\Factories;

use App\Models\LegalContrato;
use App\Models\LegalObligacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalObligacion>
 */
class LegalObligacionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legal_contrato_id' => LegalContrato::factory(),
            'tipo' => fake()->randomElement(array_keys(LegalObligacion::TIPOS)),
            'descripcion' => fake()->sentence(4),
            'monto' => fake()->randomFloat(2, 500, 50000),
            'frecuencia' => 'mensual',
            'fecha_primer_pago' => today(),
            'fecha_fin' => today()->addMonths(11),
            'activa' => true,
        ];
    }
}
