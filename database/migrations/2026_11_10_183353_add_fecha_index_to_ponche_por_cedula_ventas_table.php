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
        if (! Schema::hasIndex('ponche_por_cedula_ventas', 'ponche_ventas_fecha_index')) {
            Schema::table('ponche_por_cedula_ventas', function (Blueprint $table): void {
                $table->index('fecha_transaccion', 'ponche_ventas_fecha_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('ponche_por_cedula_ventas', 'ponche_ventas_fecha_index')) {
            Schema::table('ponche_por_cedula_ventas', function (Blueprint $table): void {
                $table->dropIndex('ponche_ventas_fecha_index');
            });
        }
    }
};
