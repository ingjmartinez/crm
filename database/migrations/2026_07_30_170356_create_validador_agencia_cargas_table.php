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
        Schema::create('validador_agencia_cargas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_archivo');
            $table->string('hash_archivo', 64)->nullable()->index();
            $table->unsignedInteger('filas_leidas')->default(0);
            $table->unsignedInteger('filas_validas')->default(0);
            $table->unsignedInteger('correctas')->default(0);
            $table->unsignedInteger('nuevas')->default(0);
            $table->unsignedInteger('nombres_diferentes')->default(0);
            $table->unsignedInteger('conflictos')->default(0);
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('validador_agencia_cargas');
    }
};
