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
            $table->unsignedInteger('terminales_evaluadas')->nullable();
            $table->unsignedInteger('terminales_con_venta')->nullable();
            $table->json('terminales_categoria')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bi_venta_horas', function (Blueprint $table) {
            $table->dropColumn(['terminales_evaluadas', 'terminales_con_venta', 'terminales_categoria']);
        });
    }
};
