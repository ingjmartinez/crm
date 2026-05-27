<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comision_acuerdos')) {
            return;
        }

        $indexExists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'comision_acuerdos')
            ->where('index_name', 'comision_acuerdos_cedula_unique')
            ->exists();

        if ($indexExists) {
            DB::statement('ALTER TABLE comision_acuerdos DROP INDEX comision_acuerdos_cedula_unique');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('comision_acuerdos')) {
            return;
        }

        $indexExists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'comision_acuerdos')
            ->where('index_name', 'comision_acuerdos_cedula_unique')
            ->exists();

        if (! $indexExists) {
            DB::statement('ALTER TABLE comision_acuerdos ADD UNIQUE comision_acuerdos_cedula_unique (cedula)');
        }
    }
};
