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
        Schema::create('nomina_domingo_auditorias', function (Blueprint $table) {
            $table->id();
            $table->string('accion', 30);
            $table->string('categoria', 30);
            $table->string('terminal', 50)->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario_nombre');
            $table->string('usuario_email');
            $table->json('antes')->nullable();
            $table->json('despues')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['categoria', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomina_domingo_auditorias');
    }
};
