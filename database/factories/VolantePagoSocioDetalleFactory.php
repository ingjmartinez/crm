<?php

namespace Database\Factories;

use App\Models\VolantePagoSocioCarga;
use App\Models\VolantePagoSocioDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolantePagoSocioDetalle>
 */
class VolantePagoSocioDetalleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'carga_id' => VolantePagoSocioCarga::factory(),
            'numero_linea' => 1,
            'nombre' => fake()->name(),
            'tipo_identificacion' => 'Cédula',
            'identificacion' => fake()->numerify('###-#######-#'),
            'cuenta' => '******'.fake()->numerify('####'),
            'tipo_cuenta' => 'Cuenta Corriente',
            'monto' => 5000,
            'estado' => 'Completada',
        ];
    }
}
