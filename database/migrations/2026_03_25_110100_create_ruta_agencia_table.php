<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ruta_agencia', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ruta_id');
            $table->foreign('ruta_id')
                ->references('id')
                ->on('rutas')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('agencia_id');
            $table->foreign('agencia_id')
                ->references('id')
                ->on('agencias')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ruta_id', 'agencia_id'], 'ruta_agencia_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruta_agencia');
    }
};
