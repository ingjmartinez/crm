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
        Schema::create('bi_horarios', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique();
            $table->string('tipo', 20);
            $table->string('cedula', 11)->nullable();
            $table->string('terminal')->nullable();
            $table->time('entrada');
            $table->time('salida');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bi_horarios');
    }
};
