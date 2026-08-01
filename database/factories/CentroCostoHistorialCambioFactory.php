<?php

namespace Database\Factories;

use App\Models\CentroCostoHistorialCambio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CentroCostoHistorialCambio>
 */
class CentroCostoHistorialCambioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'centro_costo_id' => null,
            'carga_id' => null,
            'detalle_id' => null,
            'terminal' => (string) fake()->numberBetween(1000000, 9999999),
            'terminal_normalizada' => fn (array $attributes): string => ltrim($attributes['terminal'], '0'),
            'company_id' => '168',
            'accion' => 'actualizacion',
            'campo' => 'descripcion',
            'valor_anterior' => fake()->company(),
            'valor_nuevo' => fake()->company(),
            'archivo_origen' => fake()->word().'.csv',
            'observacion' => fake()->sentence(),
            'usuario_id' => null,
        ];
    }
}
