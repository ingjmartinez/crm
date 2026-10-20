<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bi_venta_horas', 'productos_terminales')) {
            Schema::table('bi_venta_horas', function (Blueprint $table): void {
                $table->json('productos_terminales')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bi_venta_horas', 'productos_terminales')) {
            Schema::table('bi_venta_horas', function (Blueprint $table): void {
                $table->dropColumn('productos_terminales');
            });
        }
    }
};
