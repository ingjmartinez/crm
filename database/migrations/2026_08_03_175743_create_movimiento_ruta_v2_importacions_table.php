<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('movimientos_rutas_v2_importaciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_archivo');
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->unsignedInteger('fechas_reemplazadas');
            $table->unsignedInteger('filas_aceptadas');
            $table->unsignedInteger('filas_descartadas');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['fecha_desde', 'fecha_hasta'], 'mrv2_importaciones_periodo_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_rutas_v2_importaciones');
    }
};
