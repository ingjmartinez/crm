<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comision_acuerdo_agencia')) {
            return;
        }

        Schema::create('comision_acuerdo_agencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comision_acuerdo_id')
                ->constrained('comision_acuerdos')
                ->cascadeOnDelete();
            $table->foreignId('agencia_id')
                ->constrained('agencias')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['comision_acuerdo_id', 'agencia_id'], 'comision_acuerdo_agencia_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comision_acuerdo_agencia');
    }
};
