<?php

namespace Database\Factories;

use App\Models\ValidadorAgenciaCarga;
use App\Models\ValidadorAgenciaDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ValidadorAgenciaDetalle>
 */
class ValidadorAgenciaDetalleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'carga_id' => ValidadorAgenciaCarga::factory(),
            'centro_costo_id' => null,
            'terminal' => (string) fake()->unique()->numberBetween(1000000, 9999999),
            'terminal_normalizada' => fn (array $attributes): string => ltrim($attributes['terminal'], '0'),
            'nombre_agencia' => fake()->company(),
            'ruta' => 'RUTA '.fake()->numberBetween(1, 50),
            'sociedad' => 'GRUPO JOSELITO',
            'company_id' => '168',
            'nombre_centro_costo' => null,
            'ruta_centro_costo' => null,
            'sociedad_centro_costo' => null,
            'estado' => 'nuevo',
            'observacion' => 'La terminal no existe en Centros de Costo.',
            'aplicado_en' => null,
            'aplicado_por' => null,
        ];
    }
}
