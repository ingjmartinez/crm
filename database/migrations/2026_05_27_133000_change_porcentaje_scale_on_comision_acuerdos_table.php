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

        DB::statement('ALTER TABLE comision_acuerdos MODIFY porcentaje DECIMAL(8, 2) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        if (! Schema::hasTable('comision_acuerdos')) {
            return;
        }

        DB::statement('ALTER TABLE comision_acuerdos MODIFY porcentaje DECIMAL(8, 4) NOT NULL DEFAULT 0');
    }
};
