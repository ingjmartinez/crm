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
            if (!Schema::hasColumn('operaciones_deposito_rutas', 'ruta_nombre')) {
                $table->string('ruta_nombre', 150)->nullable()->after('banco')->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('operaciones_deposito_rutas')) {
            return;
        }

        Schema::table('operaciones_deposito_rutas', function (Blueprint $table) {
            if (Schema::hasColumn('operaciones_deposito_rutas', 'ruta_nombre')) {
                $table->dropColumn('ruta_nombre');
            }
        });
    }
};
