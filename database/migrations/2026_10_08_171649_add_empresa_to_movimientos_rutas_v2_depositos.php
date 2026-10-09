<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('movimientos_rutas_v2_depositos', function (Blueprint $table): void {
            $table->string('empresa_id', 3)->nullable()->after('banco');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movimientos_rutas_v2_depositos', function (Blueprint $table): void {
            $table->dropColumn('empresa_id');
        });
    }
};
