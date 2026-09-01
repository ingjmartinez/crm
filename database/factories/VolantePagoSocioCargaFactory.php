<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VolantePagoSocioCarga;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolantePagoSocioCarga>
 */
class VolantePagoSocioCargaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre_archivo' => 'TransactionsDetail-export.csv',
            'hash_archivo' => fake()->sha256(),
            'empresa_origen' => fake()->company(),
            'rnc_origen' => fake()->numerify('#########'),
            'cuenta_origen' => fake()->numerify('################'),
            'tipo_transaccion' => 'Pago a Suplidores',
            'estado' => 'Completado',
            'monto_total' => 5000,
            'impuesto_total' => 0,
            'fecha_transaccion' => now(),
            'fecha_correspondiente' => today(),
            'cantidad_transacciones' => 1,
            'usuario_id' => User::factory(),
        ];
    }
}
