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
        Schema::create('solicitudes_terminales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->char('prefijo_empresa', 2)->default('05');
            $table->char('prefijo_seleccionado', 2);
            $table->unsignedSmallInteger('cantidad');
            $table->string('estado', 20)->default('pendiente');
            $table->timestamps();

            $table->index(['estado', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_terminales');
    }
};
