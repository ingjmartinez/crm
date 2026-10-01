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
        Schema::create('bi_venta_horas', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->unsignedTinyInteger('hora');
            $table->decimal('tradicional_acumulado', 18, 2)->default(0);
            $table->decimal('no_tradicional_acumulado', 18, 2)->default(0);
            $table->decimal('otros_acumulado', 18, 2)->default(0);
            $table->unsignedInteger('registros')->default(0);
            $table->dateTime('capturado_en');
            $table->timestamps();
            $table->unique(['fecha', 'hora']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bi_venta_horas');
    }
};
