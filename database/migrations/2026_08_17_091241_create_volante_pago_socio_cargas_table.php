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
        Schema::create('volante_pago_socio_cargas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_archivo');
            $table->string('hash_archivo', 64)->nullable()->index();
            $table->string('empresa_origen');
            $table->string('rnc_origen', 30)->nullable();
            $table->string('cuenta_origen', 60);
            $table->string('tipo_transaccion');
            $table->string('estado', 60);
            $table->decimal('monto_total', 15, 2);
            $table->dateTime('fecha_transaccion');
            $table->unsignedInteger('cantidad_transacciones');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volante_pago_socio_cargas');
    }
};
