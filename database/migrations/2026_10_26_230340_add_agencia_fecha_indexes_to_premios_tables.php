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
        Schema::table('premios_bet', function (Blueprint $table): void {
            $table->index(['agencia_id', 'fecha'], 'premios_bet_agencia_fecha_index');
        });
        Schema::table('premios_net', function (Blueprint $table): void {
            $table->index(['agencia_id', 'fecha'], 'premios_net_agencia_fecha_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('premios_bet', function (Blueprint $table): void {
            $table->dropIndex('premios_bet_agencia_fecha_index');
        });
        Schema::table('premios_net', function (Blueprint $table): void {
            $table->dropIndex('premios_net_agencia_fecha_index');
        });
    }
};
