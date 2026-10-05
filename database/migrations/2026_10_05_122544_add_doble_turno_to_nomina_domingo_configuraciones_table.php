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
        Schema::table('nomina_domingo_configuraciones', function (Blueprint $table) {
            $table->unsignedSmallInteger('minutos_requeridos_doble_turno')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nomina_domingo_configuraciones', function (Blueprint $table) {
            $table->dropColumn('minutos_requeridos_doble_turno');
        });
    }
};
