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
        Schema::table('movimientos_rutas_v2_transacciones', function (Blueprint $table): void {
            $table->string('origen')->nullable()->after('tipo_etiqueta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movimientos_rutas_v2_transacciones', function (Blueprint $table): void {
            $table->dropColumn('origen');
        });
    }
};
