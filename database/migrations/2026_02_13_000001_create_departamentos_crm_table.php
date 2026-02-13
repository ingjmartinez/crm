<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reestructurar la tabla departamentos para el módulo de tareas.
     * Se agrega id autoincremental, nombre, descripción, color, estado y timestamps.
     */
    public function up(): void
    {
        // Crear nueva tabla con estructura correcta
        Schema::create('departamentos_crm', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('descripcion', 255)->nullable();
            $table->string('color', 7)->default('#405189'); // hex color
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departamentos_crm');
    }
};
