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
        Schema::create('distribucion_gasto_ruta_mapeos', function (Blueprint $table) {
            $table->id();
            $table->string('ruta_key', 150);
            $table->string('ruta_nombre', 180);
            $table->string('company_id', 20);
            $table->string('id_grupo', 20);
            $table->string('nombre_grupo', 180);
            $table->string('id_sub_grupo', 20);
            $table->string('nombre_socio', 180);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['ruta_key', 'company_id', 'id_grupo', 'id_sub_grupo'], 'dist_gasto_ruta_mapeo_unico');
            $table->index('ruta_key', 'dist_gasto_ruta_key_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distribucion_gasto_ruta_mapeos');
    }
};
