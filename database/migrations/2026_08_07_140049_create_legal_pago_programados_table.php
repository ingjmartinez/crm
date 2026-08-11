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
        Schema::create('legal_pagos_programados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_obligacion_id')->constrained('legal_obligaciones')->cascadeOnDelete();
            $table->date('periodo');
            $table->date('fecha_vencimiento');
            $table->decimal('monto', 14, 2);
            $table->string('estado', 30)->default('pendiente');
            $table->date('fecha_pago')->nullable();
            $table->string('referencia_pago', 150)->nullable();
            $table->string('comprobante_path', 500)->nullable();
            $table->foreignId('pagado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['legal_obligacion_id', 'fecha_vencimiento'], 'legal_pago_obligacion_vencimiento_unique');
            $table->index(['estado', 'fecha_vencimiento']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_pagos_programados');
    }
};
