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
            $table->decimal('externas_acumulado', 18, 2)->default(0);
            $table->decimal('recargas_acumulado', 18, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bi_venta_horas', function (Blueprint $table) {
            $table->dropColumn(['externas_acumulado', 'recargas_acumulado']);
        });
    }
};
