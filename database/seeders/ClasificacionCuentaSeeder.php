<?php

namespace Database\Seeders;

use App\Models\ClasificacionCuenta;
use Illuminate\Database\Seeder;

class ClasificacionCuentaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (config('clasificacion_cuentas.cuentas', []) as $cuenta => $clasificacion) {
            ClasificacionCuenta::query()->firstOrCreate(
                ['cuenta' => (string) $cuenta],
                [
                    'clasificacion' => $clasificacion,
                    'criterio' => config("clasificacion_cuentas.criterios.{$cuenta}", $clasificacion),
                    'observacion' => config("clasificacion_cuentas.observaciones.{$cuenta}"),
                    'activo' => true,
                ],
            );
        }
    }
}
