<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bi_venta_dias', function (Blueprint $table) {
            $table->string('origen', 20)->default('vt_usuarios_bet');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE bi_venta_dias ENGINE=InnoDB');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bi_venta_dias', function (Blueprint $table) {
            $table->dropColumn('origen');
        });
    }
};
