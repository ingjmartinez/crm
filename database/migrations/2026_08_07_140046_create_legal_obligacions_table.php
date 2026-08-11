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
        Schema::create('legal_obligaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_contrato_id')->constrained('legal_contratos')->cascadeOnDelete();
            $table->string('tipo', 40);
            $table->string('descripcion', 180)->nullable();
            $table->decimal('monto', 14, 2);
            $table->string('frecuencia', 30);
            $table->date('fecha_primer_pago');
            $table->date('fecha_fin')->nullable();
            $table->boolean('activa')->default(true);
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['legal_contrato_id', 'activa']);
            $table->index(['tipo', 'activa']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_obligaciones');
    }
};
