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
        Schema::table('bancos_operaciones', function (Blueprint $table): void {
            $table->dropUnique('bancos_operaciones_nombre_unique');
            $table->string('empresa_id', 3)->nullable()->after('nombre');
            $table->string('cuenta_codigo', 50)->nullable()->after('empresa_id');
            $table->string('cuenta_descripcion', 255)->nullable()->after('cuenta_codigo');
            $table->unique(['empresa_id', 'nombre']);
            $table->unique(['empresa_id', 'cuenta_codigo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bancos_operaciones', function (Blueprint $table): void {
            $table->dropUnique(['empresa_id', 'nombre']);
            $table->dropUnique(['empresa_id', 'cuenta_codigo']);
            $table->dropColumn(['empresa_id', 'cuenta_codigo', 'cuenta_descripcion']);
            $table->unique('nombre');
        });
    }
};
