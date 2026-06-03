<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cruce_usuario_seguimientos', function (Blueprint $table) {
            $table->id();
            $table->string('cedula', 20);
            $table->string('empleado_id', 50)->nullable();
            $table->string('nombre_completo', 180)->nullable();
            $table->text('detalle')->nullable();
            $table->string('estatus_origen', 80);
            $table->date('ultima_fecha_venta')->nullable();
            $table->date('reporte_fecha_inicio')->nullable();
            $table->date('reporte_fecha_fin')->nullable();
            $table->string('sistema', 30)->default('todos');
            $table->string('empresa', 40)->default('todos');
            $table->string('estado', 20)->default('pendiente');
            $table->timestamp('gestion_inicio_at')->nullable();
            $table->timestamp('finalizado_at')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('gestion_iniciada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['cedula', 'ultima_fecha_venta', 'estatus_origen'], 'cruce_usuario_seguimiento_unique');
            $table->index(['estado', 'created_at']);
            $table->index(['cedula', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cruce_usuario_seguimientos');
    }
};
