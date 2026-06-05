<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('operaciones_deposito_rutas')) {
            return;
        }

        Schema::table('operaciones_deposito_rutas', function (Blueprint $table) {
            if (!Schema::hasColumn('operaciones_deposito_rutas', 'monto_depositado')) {
                $table->decimal('monto_depositado', 14, 2)->default(0)->after('ruta_nombre');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('operaciones_deposito_rutas')) {
            return;
        }

        Schema::table('operaciones_deposito_rutas', function (Blueprint $table) {
            if (Schema::hasColumn('operaciones_deposito_rutas', 'monto_depositado')) {
                $table->dropColumn('monto_depositado');
            }
        });
    }
};
