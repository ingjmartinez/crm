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
        Schema::table('entradas_diario', function (Blueprint $table) {
            $table->index('id_viejo', 'entradas_diario_id_viejo_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entradas_diario', function (Blueprint $table) {
            $table->dropIndex('entradas_diario_id_viejo_index');
        });
    }
};
