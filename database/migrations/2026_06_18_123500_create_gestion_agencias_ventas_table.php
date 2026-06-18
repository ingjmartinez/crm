<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gestion_agencias_ventas', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 30);
            $table->dateTime('fecha_transaccion')->nullable();
            $table->string('fecha_texto', 40)->nullable();
            $table->string('agencia')->nullable();
            $table->string('terminal', 50)->nullable();
            $table->string('terminal_clave', 50)->nullable();
            $table->string('usuario_venta', 80)->nullable();
            $table->decimal('total_apostado', 15, 2)->default(0);
            $table->string('estatus', 30)->default('Validos');
            $table->timestamps();

            $table->index(['terminal_clave', 'tipo']);
            $table->index(['tipo', 'fecha_transaccion']);
            $table->index('agencia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gestion_agencias_ventas');
    }
};
