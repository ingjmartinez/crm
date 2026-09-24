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
        Schema::table('solicitud_terminal_codigos', function (Blueprint $table) {
            $table->string('nombre_banca')->nullable()->after('estado');
            $table->string('region', 80)->nullable()->after('nombre_banca');
            $table->string('provincia', 100)->nullable()->after('region');
            $table->string('municipio', 120)->nullable()->after('provincia');
            $table->string('ciudad', 160)->nullable()->after('municipio');
            $table->string('sector', 190)->nullable()->after('ciudad');
            $table->string('calle')->nullable()->after('sector');
            $table->string('direccion_local')->nullable()->after('calle');
            $table->decimal('latitud', 10, 7)->nullable()->after('direccion_local');
            $table->decimal('longitud', 10, 7)->nullable()->after('latitud');
            $table->string('rja', 100)->nullable()->after('longitud');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitud_terminal_codigos', function (Blueprint $table) {
            $table->dropColumn([
                'nombre_banca',
                'region',
                'provincia',
                'municipio',
                'ciudad',
                'sector',
                'calle',
                'direccion_local',
                'latitud',
                'longitud',
                'rja',
            ]);
        });
    }
};
