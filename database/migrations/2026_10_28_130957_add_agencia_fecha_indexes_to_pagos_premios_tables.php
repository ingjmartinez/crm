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
        foreach (['pagos_misma_empresa_bet', 'pagos_aotra_empresa_bet', 'pagos_misma_empresa_net', 'pagos_aotra_empresa_net'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla): void {
                $table->index(['agencia_id', 'fecha', 'monto'], $tabla.'_agencia_fecha_monto_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['pagos_misma_empresa_bet', 'pagos_aotra_empresa_bet', 'pagos_misma_empresa_net', 'pagos_aotra_empresa_net'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla): void {
                $table->dropIndex($tabla.'_agencia_fecha_monto_index');
            });
        }
    }
};
