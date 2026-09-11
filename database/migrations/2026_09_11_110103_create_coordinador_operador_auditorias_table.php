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
        Schema::create('coordinador_operador_auditorias', function (Blueprint $table) {
            $table->id();
            $table->string('accion', 20);
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario_nombre');
            $table->string('usuario_email');
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->string('empleado_nombre');
            $table->string('cedula', 20)->nullable();
            $table->string('puesto', 30);
            $table->json('datos');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['accion', 'created_at']);
            $table->index(['usuario_id', 'created_at']);
            $table->index(['cedula', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coordinador_operador_auditorias');
    }
};
