<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('incentivo_administrativos') || Schema::hasColumn('incentivo_administrativos', 'cedula')) {
            return;
        }

        Schema::table('incentivo_administrativos', function (Blueprint $table) {
            $table->string('cedula', 11)->nullable()->after('nombre')->index();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('incentivo_administrativos') || !Schema::hasColumn('incentivo_administrativos', 'cedula')) {
            return;
        }

        Schema::table('incentivo_administrativos', function (Blueprint $table) {
            $table->dropColumn('cedula');
        });
    }
};
