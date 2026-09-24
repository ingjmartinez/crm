<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zonas_geograficas', function (Blueprint $table): void {
            $table->id();
            $table->string('region', 80);
            $table->string('provincia', 100);
            $table->string('municipio', 120);
            $table->string('ciudad_seccion', 160);
            $table->string('sector_barrio_paraje', 190);
            $table->char('jerarquia_hash', 64)->unique();
            $table->timestamps();
        });

        $archivo = database_path('data/zonas_geograficas.csv');

        if (! is_file($archivo) || ($handle = fopen($archivo, 'rb')) === false) {
            return;
        }

        $ahora = now();
        $filas = [];

        try {
            while (($fila = fgetcsv($handle)) !== false) {
                if (count($fila) !== 5) {
                    continue;
                }

                $filas[] = [
                    'region' => trim($fila[0]),
                    'provincia' => trim($fila[1]),
                    'municipio' => trim($fila[2]),
                    'ciudad_seccion' => trim($fila[3]),
                    'sector_barrio_paraje' => trim($fila[4]),
                    'jerarquia_hash' => hash('sha256', implode("\x1F", array_map('trim', $fila))),
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];

                if (count($filas) === 500) {
                    DB::table('zonas_geograficas')->insertOrIgnore($filas);
                    $filas = [];
                }
            }

            if ($filas !== []) {
                DB::table('zonas_geograficas')->insertOrIgnore($filas);
            }
        } finally {
            fclose($handle);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('zonas_geograficas');
    }
};
