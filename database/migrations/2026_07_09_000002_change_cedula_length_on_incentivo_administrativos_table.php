<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('incentivo_administrativos') || !Schema::hasColumn('incentivo_administrativos', 'cedula')) {
            return;
        }

        DB::statement('ALTER TABLE `incentivo_administrativos` MODIFY `cedula` VARCHAR(11) NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('incentivo_administrativos') || !Schema::hasColumn('incentivo_administrativos', 'cedula')) {
            return;
        }

        DB::statement('ALTER TABLE `incentivo_administrativos` MODIFY `cedula` VARCHAR(25) NULL');
    }
};
