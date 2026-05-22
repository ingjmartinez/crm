<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('contabilidad_electricidad_seguimiento_dia', 'medidor')) {
            Schema::table('contabilidad_electricidad_seguimiento_dia', function (Blueprint $table) {
                $table->string('medidor', 20)->nullable()->after('nic');
            });
        }

        if (!Schema::hasColumn('contabilidad_electricidad_averia_dia', 'medidor')) {
            Schema::table('contabilidad_electricidad_averia_dia', function (Blueprint $table) {
                $table->string('medidor', 20)->nullable()->after('nic');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contabilidad_electricidad_seguimiento_dia', 'medidor')) {
            Schema::table('contabilidad_electricidad_seguimiento_dia', function (Blueprint $table) {
                $table->dropColumn('medidor');
            });
        }

        if (Schema::hasColumn('contabilidad_electricidad_averia_dia', 'medidor')) {
            Schema::table('contabilidad_electricidad_averia_dia', function (Blueprint $table) {
                $table->dropColumn('medidor');
            });
        }
    }
};
