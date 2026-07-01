<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('incentivo_administrativos') || !Schema::hasColumn('incentivo_administrativos', 'pct_total')) {
            return;
        }

        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('ALTER TABLE incentivo_administrativos MODIFY pct_total DECIMAL(12,4) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        if (!Schema::hasTable('incentivo_administrativos') || !Schema::hasColumn('incentivo_administrativos', 'pct_total')) {
            return;
        }

        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('ALTER TABLE incentivo_administrativos MODIFY pct_total DECIMAL(8,4) NOT NULL DEFAULT 0');
    }
};
