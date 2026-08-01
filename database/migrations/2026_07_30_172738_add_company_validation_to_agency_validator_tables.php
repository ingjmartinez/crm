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
        Schema::table('validador_agencia_cargas', function (Blueprint $table) {
            $table->unsignedInteger('sociedades_diferentes')->default(0)->after('rutas_diferentes');
        });

        Schema::table('validador_agencia_detalles', function (Blueprint $table) {
            $table->string('sociedad_centro_costo', 255)->nullable()->after('ruta_centro_costo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('validador_agencia_detalles', function (Blueprint $table) {
            $table->dropColumn('sociedad_centro_costo');
        });

        Schema::table('validador_agencia_cargas', function (Blueprint $table) {
            $table->dropColumn('sociedades_diferentes');
        });
    }
};
