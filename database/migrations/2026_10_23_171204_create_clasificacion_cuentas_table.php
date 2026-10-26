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
        Schema::create('clasificacion_cuentas', function (Blueprint $table) {
            $table->id();
            $table->string('cuenta', 50)->unique();
            $table->string('clasificacion', 10)->nullable();
            $table->string('criterio', 10)->nullable();
            $table->text('observacion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clasificacion_cuentas');
    }
};
