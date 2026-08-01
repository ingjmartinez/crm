<?php

namespace Database\Factories;

use App\Models\ValidadorAgenciaCarga;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ValidadorAgenciaCarga>
 */
class ValidadorAgenciaCargaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre_archivo' => fake()->word().'.csv',
            'hash_archivo' => fake()->sha256(),
            'filas_leidas' => 1,
            'filas_validas' => 1,
            'correctas' => 0,
            'nuevas' => 1,
            'nombres_diferentes' => 0,
            'rutas_diferentes' => 0,
            'sociedades_diferentes' => 0,
            'conflictos' => 0,
            'usuario_id' => null,
        ];
    }
}
