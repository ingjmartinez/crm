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
        Schema::create('solicitud_terminal_codigos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_terminal_id')->constrained('solicitudes_terminales')->cascadeOnDelete();
            $table->char('codigo', 8)->unique();
            $table->string('estado', 20)->default('pendiente');
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_at')->nullable();
            $table->timestamps();

            $table->index(['solicitud_terminal_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitud_terminal_codigos');
    }
};
