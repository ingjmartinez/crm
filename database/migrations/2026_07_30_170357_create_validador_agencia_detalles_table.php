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
        Schema::create('validador_agencia_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carga_id')->constrained('validador_agencia_cargas')->cascadeOnDelete();
            $table->foreignId('centro_costo_id')->nullable()->constrained('centros_de_costo')->nullOnDelete();
            $table->string('terminal', 150);
            $table->string('terminal_normalizada', 150);
            $table->string('nombre_agencia', 255);
            $table->string('ruta', 255);
            $table->string('sociedad', 255);
            $table->string('company_id', 3);
            $table->string('nombre_centro_costo', 255)->nullable();
            $table->string('estado', 40)->index();
            $table->text('observacion')->nullable();
            $table->timestamp('aplicado_en')->nullable();
            $table->foreignId('aplicado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['carga_id', 'company_id', 'terminal_normalizada'],
                'validador_agencia_detalles_carga_terminal_unique'
            );
            $table->index(['company_id', 'terminal_normalizada']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('validador_agencia_detalles');
    }
};
