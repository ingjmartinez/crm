<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitoreo_terminal_horarios', function (Blueprint $table): void {
            $table->string('tipo_horario', 2)->default('AM')->after('hora');
        });

        DB::table('monitoreo_terminal_horarios')
            ->where('hora', '>=', '14:30')
            ->update(['tipo_horario' => 'PM']);

        Schema::table('monitoreo_terminal_horarios', function (Blueprint $table): void {
            $table->dropUnique('monitoreo_terminal_horarios_hora_unique');
            $table->unique(['hora', 'tipo_horario']);
        });
    }

    public function down(): void
    {
        Schema::table('monitoreo_terminal_horarios', function (Blueprint $table): void {
            $table->dropUnique(['hora', 'tipo_horario']);
            $table->dropColumn('tipo_horario');
            $table->unique('hora');
        });
    }
};
