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
        foreach (['pagos_misma_empresa_bet', 'pagos_misma_empresa_net', 'pagos_porotra_empresa_bet', 'pagos_porotra_empresa_net'] as $tabla) {
            $columnaTerminal = str_starts_with($tabla, 'pagos_misma_empresa_') ? 'pagado_agencia_id' : 'agencia_id';

            Schema::table($tabla, function (Blueprint $table) use ($tabla, $columnaTerminal): void {
                $table->index([$columnaTerminal, 'fecha', 'producto_id', 'monto'], $tabla.'_rent_v5_propios_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['pagos_misma_empresa_bet', 'pagos_misma_empresa_net', 'pagos_porotra_empresa_bet', 'pagos_porotra_empresa_net'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla): void {
                $table->dropIndex($tabla.'_rent_v5_propios_index');
            });
        }
    }
};
