<?php

namespace Database\Factories;

use App\Models\Agencia;
use App\Models\LegalContrato;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalContrato>
 */
class LegalContratoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agencia_id' => Agencia::query()->inRandomOrder()->value('id') ?? 1,
            'titulo' => fake()->randomElement(['Contrato de alquiler', 'Contrato de servicio']),
            'numero_contrato' => fake()->optional()->bothify('LEG-####'),
            'contraparte' => fake()->name(),
            'fecha_inicio' => today(),
            'fecha_fin' => today()->addYear(),
            'estado' => 'activo',
            'renovacion_automatica' => false,
            'documento_path' => 'legal/contratos/prueba.pdf',
            'documento_nombre_original' => 'contrato-prueba.pdf',
            'observaciones' => fake()->optional()->sentence(),
        ];
    }
}
