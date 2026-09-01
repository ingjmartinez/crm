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
        Schema::table('volante_pago_socio_cargas', function (Blueprint $table) {
            $table->string('banco', 30)
                ->default('santa_cruz')
                ->after('hash_archivo')
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('volante_pago_socio_cargas', function (Blueprint $table) {
            $table->dropColumn('banco');
        });
    }
};
