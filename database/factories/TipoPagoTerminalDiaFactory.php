<?php

namespace Database\Factories;

use App\Models\TipoPagoTerminalDia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoPagoTerminalDia>
 */
class TipoPagoTerminalDiaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fecha' => fake()->date(),
            'terminal' => (string) fake()->unique()->numberBetween(10000, 99999),
            'tipo_pago' => 60,
            'tipo_pago_original' => '60-08-04 P1,000 T20,000',
            'archivo_origen' => 'LTKBancas.csv',
        ];
    }
}
