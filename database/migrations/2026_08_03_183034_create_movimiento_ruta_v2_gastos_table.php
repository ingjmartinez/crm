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
        Schema::create('movimientos_rutas_v2_gastos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('ruta_key', 180);
            $table->string('ruta', 180);
            $table->decimal('monto', 15, 2);
            $table->string('concepto', 150);
            $table->string('comprobante_path', 500)->nullable();
            $table->text('observacion')->nullable();
            $table->string('estado', 20)->default('aplicado');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['fecha', 'ruta_key', 'estado'], 'mrv2_gastos_fecha_ruta_estado_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_rutas_v2_gastos');
    }
};
