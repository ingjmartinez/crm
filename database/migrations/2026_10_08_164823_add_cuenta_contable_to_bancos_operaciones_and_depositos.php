<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancos_operaciones', function (Blueprint $table): void {
            $table->foreignId('cuenta_contable_id')->nullable()->constrained('cuentas_contables')->nullOnDelete();
        });

        Schema::table('movimientos_rutas_v2_depositos', function (Blueprint $table): void {
            $table->string('cuenta_banco', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_rutas_v2_depositos', function (Blueprint $table): void {
            $table->dropColumn('cuenta_banco');
        });

        Schema::table('bancos_operaciones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cuenta_contable_id');
        });
    }
};
