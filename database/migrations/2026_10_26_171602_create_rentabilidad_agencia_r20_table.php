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
        Schema::create('rentabilidad_agencia_r20', function (Blueprint $table) {
            $table->id();
            $table->string('terminal', 50);
            $table->string('mes', 7);
            $table->decimal('monto', 15, 2);
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['terminal', 'mes']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rentabilidad_agencia_r20');
    }
};
