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
        Schema::create('ventas_online_promedios_historicos', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo_categoria')->unique();
            $table->decimal('monto_promedio', 14, 2)->default(0);
            $table->json('meses_incluidos');
            $table->timestamp('calculado_en');
            $table->foreignId('calculado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas_online_promedios_historicos');
    }
};
