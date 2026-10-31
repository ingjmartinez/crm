<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuentas_contables', function (Blueprint $table): void {
            $table->string('company_id', 3)->default('168');
            $table->dropUnique(['cuenta']);
            $table->unique(['company_id', 'cuenta']);
        });
        Schema::table('detalle_cuentas', function (Blueprint $table): void {
            $table->string('company_id', 3)->default('168');
            $table->index(['company_id', 'cuenta', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::table('detalle_cuentas', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'cuenta', 'fecha']);
            $table->dropColumn('company_id');
        });
        Schema::table('cuentas_contables', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'cuenta']);
            $table->unique('cuenta');
            $table->dropColumn('company_id');
        });
    }
};
