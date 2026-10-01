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
        Schema::table('bi_limite_productos', function (Blueprint $table): void {
            $table->string('terminal', 50)->default('');
            $table->dropUnique(['producto_id']);
            $table->unique(['producto_id', 'terminal']);
        });
        Schema::table('bi_venta_horas', function (Blueprint $table): void {
            $table->json('productos_terminales')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (\App\Models\BiLimiteProducto::query()->where('terminal', '<>', '')->exists()) {
            throw new \RuntimeException('Retira los límites por terminal antes de revertir esta migración.');
        }
        Schema::table('bi_limite_productos', function (Blueprint $table): void {
            $table->dropUnique(['producto_id', 'terminal']);
            $table->dropColumn('terminal');
            $table->unique('producto_id');
        });
        Schema::table('bi_venta_horas', function (Blueprint $table): void {
            $table->dropColumn('productos_terminales');
        });
    }
};
