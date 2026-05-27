<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agencia_horarios')) {
            return;
        }

        Schema::create('agencia_horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agencia_id')->constrained('agencias')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana');
            $table->string('horario_am', 35)->nullable();
            $table->string('horario_pm', 35)->nullable();
            $table->timestamps();

            $table->unique(['agencia_id', 'dia_semana']);
            $table->index('dia_semana');
        });

        DB::table('agencias')
            ->select('id', 'horario_am', 'horario_pm')
            ->where(function ($query) {
                $query->whereNotNull('horario_am')
                    ->orWhereNotNull('horario_pm');
            })
            ->orderBy('id')
            ->chunk(500, function ($agencias) {
                $now = now();
                $rows = [];

                foreach ($agencias as $agencia) {
                    for ($dia = 1; $dia <= 7; $dia++) {
                        $rows[] = [
                            'agencia_id' => $agencia->id,
                            'dia_semana' => $dia,
                            'horario_am' => $agencia->horario_am,
                            'horario_pm' => $agencia->horario_pm,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if (!empty($rows)) {
                    DB::table('agencia_horarios')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('agencia_horarios');
    }
};
