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
        Schema::create('bi_premio_dias', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->date('fecha')->unique();
            $table->decimal('premios', 18, 2)->default(0);
            $table->unsignedInteger('registros')->default(0);
            $table->string('origen', 30)->default('premios_bet');
            $table->dateTime('resumido_en');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bi_premio_dias');
    }
};
