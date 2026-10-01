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
        Schema::create('bi_producto_dias', function (Blueprint $table) {
            $table->id();
            $table->string('producto_id', 30);
            $table->date('fecha');
            $table->decimal('monto', 18, 2)->default(0);
            $table->unsignedInteger('registros')->default(0);
            $table->timestamps();
            $table->unique(['producto_id', 'fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bi_producto_dias');
    }
};
