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
        Schema::create('centro_costo_historial_cambios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centro_costo_id')->nullable()->constrained('centros_de_costo')->nullOnDelete();
            $table->foreignId('carga_id')->nullable()->constrained('validador_agencia_cargas')->nullOnDelete();
            $table->foreignId('detalle_id')->nullable()->constrained('validador_agencia_detalles')->nullOnDelete();
            $table->string('terminal', 150);
            $table->string('terminal_normalizada', 150);
            $table->string('company_id', 3);
            $table->string('accion', 40);
            $table->string('campo', 100);
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->string('archivo_origen')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'terminal_normalizada', 'created_at'], 'cc_historial_terminal_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('centro_costo_historial_cambios');
    }
};
