<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserFavorito;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserFavorito>
 */
class UserFavoritoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'favorito_key' => 'contabilidad:contabilidad/reportes/estado-resultado',
            'orden' => 1,
        ];
    }
}
