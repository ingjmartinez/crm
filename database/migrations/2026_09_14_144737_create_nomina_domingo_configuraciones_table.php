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
        Schema::create('nomina_domingo_configuraciones', function (Blueprint $table) {
            $table->id();
            $table->decimal('horas_requeridas', 5, 2)->default(8);
            $table->decimal('monto_fijo', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomina_domingo_configuraciones');
    }
};
