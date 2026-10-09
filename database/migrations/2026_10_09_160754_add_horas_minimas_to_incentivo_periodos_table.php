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
        Schema::table('incentivo_periodos', function (Blueprint $table) {
            $table->decimal('horas_minimas', 8, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incentivo_periodos', function (Blueprint $table) {
            $table->dropColumn('horas_minimas');
        });
    }
};
