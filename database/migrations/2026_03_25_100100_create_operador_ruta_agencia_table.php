<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operador_ruta_agencia', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operador_ruta_id');
            $table->foreign('operador_ruta_id')
                ->references('id')
                ->on('operador_ruta')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('agencia_id');
            $table->foreign('agencia_id')
                ->references('id')
                ->on('agencias')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['operador_ruta_id', 'agencia_id'], 'operador_ruta_agencia_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operador_ruta_agencia');
    }
};
