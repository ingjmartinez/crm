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
        Schema::create('movimientos_rutas_v2_transacciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('importacion_id')->constrained('movimientos_rutas_v2_importaciones')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('ruta_key', 180);
            $table->string('ruta', 180);
            $table->string('id_trans', 120);
            $table->string('terminal', 50)->nullable();
            $table->string('nombre_agencia')->nullable();
            $table->string('tipo', 20);
            $table->string('tipo_etiqueta', 40);
            $table->decimal('monto', 15, 2);
            $table->decimal('monto_original', 15, 2);
            $table->timestamps();

            $table->unique(['fecha', 'id_trans'], 'mrv2_transacciones_fecha_id_unique');
            $table->index(['fecha', 'ruta_key'], 'mrv2_transacciones_fecha_ruta_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_rutas_v2_transacciones');
    }
};
