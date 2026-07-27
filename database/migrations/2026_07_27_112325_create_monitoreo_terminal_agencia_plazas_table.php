<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoreo_terminal_agencia_plazas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('agencia_id')->unique();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoreo_terminal_agencia_plazas');
    }
};
