<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('servicio_general_mantenimiento_equipos');

        Schema::create('servicio_general_mantenimiento_equipos', function (Blueprint $table) {
            $table->id();
            $table->string('terminal_codigo', 80)->index();
            $table->unsignedInteger('agencia_id')->nullable()->index();
            $table->string('nombre_agencia', 180)->nullable();
            $table->string('equipo_tipo', 80);
            $table->string('equipo_codigo', 120)->nullable();
            $table->text('descripcion')->nullable();
            $table->date('fecha_mantenimiento');
            $table->string('estado', 20)->default('programado');
            $table->timestamp('realizado_at')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('realizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['estado', 'fecha_mantenimiento'], 'sg_mant_eq_estado_fecha_idx');
            $table->index(['terminal_codigo', 'equipo_tipo'], 'sg_mant_eq_terminal_tipo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_general_mantenimiento_equipos');
    }
};
