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

        DB::statement('ALTER TABLE comision_acuerdos MODIFY cedula VARCHAR(11) NULL');
        DB::statement('ALTER TABLE comision_acuerdos MODIFY telefono VARCHAR(10) NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('comision_acuerdos')) {
            return;
        }

        DB::statement('ALTER TABLE comision_acuerdos MODIFY cedula VARCHAR(11) NOT NULL');
        DB::statement('ALTER TABLE comision_acuerdos MODIFY telefono VARCHAR(10) NOT NULL');
    }
};
