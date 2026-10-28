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
        foreach (['vt_usuarios_bet', 'vt_usuarios_net'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla): void {
                $table->index(['agencia_id', 'fecha', 'tipo', 'monto'], $tabla.'_rent_v2_tipo_index');
            });
        }

        foreach (['premios_bet', 'premios_net', 'pagos_misma_empresa_bet', 'pagos_aotra_empresa_bet', 'pagos_misma_empresa_net', 'pagos_aotra_empresa_net'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla): void {
                $table->index(['agencia_id', 'fecha', 'producto_id', 'monto'], $tabla.'_rent_v2_producto_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['vt_usuarios_bet', 'vt_usuarios_net'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla): void {
                $table->dropIndex($tabla.'_rent_v2_tipo_index');
            });
        }

        foreach (['premios_bet', 'premios_net', 'pagos_misma_empresa_bet', 'pagos_aotra_empresa_bet', 'pagos_misma_empresa_net', 'pagos_aotra_empresa_net'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla): void {
                $table->dropIndex($tabla.'_rent_v2_producto_index');
            });
        }
    }
};
