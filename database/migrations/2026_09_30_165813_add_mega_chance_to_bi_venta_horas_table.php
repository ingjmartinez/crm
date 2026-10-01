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
        Schema::table('bi_venta_horas', function (Blueprint $table) {
            $table->decimal('mega_chance_acumulado', 18, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bi_venta_horas', function (Blueprint $table) {
            $table->dropColumn('mega_chance_acumulado');
        });
    }
};
