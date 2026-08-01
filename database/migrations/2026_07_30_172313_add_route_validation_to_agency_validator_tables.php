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
            $table->unsignedInteger('rutas_diferentes')->default(0)->after('nombres_diferentes');
        });

        Schema::table('validador_agencia_detalles', function (Blueprint $table) {
            $table->string('ruta_centro_costo', 255)->nullable()->after('nombre_centro_costo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('validador_agencia_detalles', function (Blueprint $table) {
            $table->dropColumn('ruta_centro_costo');
        });

        Schema::table('validador_agencia_cargas', function (Blueprint $table) {
            $table->dropColumn('rutas_diferentes');
        });
    }
};
