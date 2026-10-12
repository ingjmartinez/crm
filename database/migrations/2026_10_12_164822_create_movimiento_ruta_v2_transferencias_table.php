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
        Schema::create('movimiento_ruta_v2_transferencias', function (Blueprint $table): void {
            $table->id();
            $table->date('fecha');
            $table->string('ruta_origen_key', 180);
            $table->string('ruta_origen', 180);
            $table->string('ruta_destino_key', 180);
            $table->string('ruta_destino', 180);
            $table->decimal('monto', 15, 2);
            $table->string('recibo_path');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->index(['fecha', 'ruta_origen_key']);
            $table->index(['fecha', 'ruta_destino_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimiento_ruta_v2_transferencias');
    }
};
