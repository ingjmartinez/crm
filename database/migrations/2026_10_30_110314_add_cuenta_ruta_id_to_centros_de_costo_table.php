<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centros_de_costo', function (Blueprint $table): void {
            $table->foreignId('cuenta_ruta_id')->nullable()->constrained('cuentas_contables')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('centros_de_costo', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cuenta_ruta_id');
        });
    }
};
