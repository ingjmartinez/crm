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
        Schema::table('movimiento_ruta_v2_deposito_centrals', function (Blueprint $table): void {
            $table->string('id_trans')->nullable()->after('ruta');
            $table->unique(['fecha', 'id_trans'], 'mrv2_depositos_central_fecha_trans_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movimiento_ruta_v2_deposito_centrals', function (Blueprint $table): void {
            $table->dropUnique('mrv2_depositos_central_fecha_trans_unique');
            $table->dropColumn('id_trans');
        });
    }
};
