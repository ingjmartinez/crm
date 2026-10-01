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
        Schema::create('tipo_pago_terminal_dias', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('terminal', 50);
            $table->unsignedTinyInteger('tipo_pago');
            $table->string('tipo_pago_original', 255);
            $table->string('archivo_origen', 255);
            $table->foreignId('cargado_por_id')->nullable();
            $table->timestamps();

            $table->unique(['fecha', 'terminal']);
            $table->index(['terminal', 'fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_pago_terminal_dias');
    }
};
