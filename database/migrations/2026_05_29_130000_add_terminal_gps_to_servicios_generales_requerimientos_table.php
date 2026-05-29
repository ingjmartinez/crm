<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('servicios_generales_requerimientos')) {
            return;
        }

        Schema::table('servicios_generales_requerimientos', function (Blueprint $table) {
            if (!Schema::hasColumn('servicios_generales_requerimientos', 'terminal_codigo')) {
                $table->string('terminal_codigo', 80)->nullable()->after('tipo')->index();
            }

            if (!Schema::hasColumn('servicios_generales_requerimientos', 'gps_lat')) {
                $table->decimal('gps_lat', 10, 7)->nullable()->after('terminal_codigo');
            }

            if (!Schema::hasColumn('servicios_generales_requerimientos', 'gps_lng')) {
                $table->decimal('gps_lng', 10, 7)->nullable()->after('gps_lat');
            }

            if (!Schema::hasColumn('servicios_generales_requerimientos', 'gps')) {
                $table->string('gps', 80)->nullable()->after('gps_lng');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('servicios_generales_requerimientos')) {
            return;
        }

        Schema::table('servicios_generales_requerimientos', function (Blueprint $table) {
            foreach (['gps', 'gps_lng', 'gps_lat', 'terminal_codigo'] as $column) {
                if (Schema::hasColumn('servicios_generales_requerimientos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
