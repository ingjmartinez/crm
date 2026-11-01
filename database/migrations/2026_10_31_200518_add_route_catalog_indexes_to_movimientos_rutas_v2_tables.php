<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array{nombre: string, columnas: list<string>}> */
    private const INDICES = [
        'movimientos_rutas_v2_transacciones' => ['nombre' => 'mrv2_transacciones_catalogo_ruta_idx', 'columnas' => ['ruta_key']],
        'movimientos_rutas_v2_gastos' => ['nombre' => 'mrv2_gastos_catalogo_ruta_idx', 'columnas' => ['estado', 'ruta_key']],
    ];

    public function up(): void
    {
        foreach (self::INDICES as $tabla => $indice) {
            if (Schema::hasIndex($tabla, $indice['nombre'])) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) use ($indice): void {
                $table->index($indice['columnas'], $indice['nombre']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse(self::INDICES, true) as $tabla => $indice) {
            if (! Schema::hasIndex($tabla, $indice['nombre'])) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) use ($indice): void {
                $table->dropIndex($indice['nombre']);
            });
        }
    }
};
