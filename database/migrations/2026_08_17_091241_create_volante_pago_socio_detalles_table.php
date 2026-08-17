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
        Schema::create('volante_pago_socio_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carga_id')->constrained('volante_pago_socio_cargas')->cascadeOnDelete();
            $table->unsignedInteger('numero_linea');
            $table->string('nombre');
            $table->string('tipo_identificacion', 60);
            $table->string('identificacion', 60);
            $table->string('cuenta', 60);
            $table->string('tipo_cuenta', 80);
            $table->decimal('monto', 15, 2);
            $table->string('estado', 60);
            $table->timestamps();

            $table->unique(['carga_id', 'numero_linea']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volante_pago_socio_detalles');
    }
};
