<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $agenciaIdColumn = $this->agenciaIdColumnDefinition();

        if (!Schema::hasTable('agencia_horarios')) {
            Schema::create('agencia_horarios', function (Blueprint $table) use ($agenciaIdColumn) {
                $table->id();
                $this->addAgenciaIdColumn($table, $agenciaIdColumn);
                $table->unsignedTinyInteger('dia_semana');
                $table->string('horario_am', 35)->nullable();
                $table->string('horario_pm', 35)->nullable();
                $table->timestamps();

                $table->unique(['agencia_id', 'dia_semana']);
                $table->index('dia_semana');
            });
        } else {
            $this->ensureAgenciaIdColumnIsCompatible($agenciaIdColumn);
        }

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
                    DB::table('agencia_horarios')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('agencia_horarios');
    }

    private function agenciaIdColumnDefinition(): string
    {
        $column = DB::selectOne("SHOW COLUMNS FROM agencias WHERE Field = 'id'");
        $type = strtolower((string) ($column->Type ?? ''));
        $unsigned = str_contains($type, 'unsigned') ? ' unsigned' : '';

        if (str_contains($type, 'bigint')) {
            return 'bigint' . $unsigned;
        }

        return 'int' . $unsigned;
    }

    private function addAgenciaIdColumn(Blueprint $table, string $agenciaIdColumn): void
    {
        if ($agenciaIdColumn === 'bigint unsigned') {
            $table->unsignedBigInteger('agencia_id');
            return;
        }

        if ($agenciaIdColumn === 'bigint') {
            $table->bigInteger('agencia_id');
            return;
        }

        if ($agenciaIdColumn === 'int unsigned') {
            $table->unsignedInteger('agencia_id');
            return;
        }

        $table->integer('agencia_id');
    }

    private function ensureAgenciaIdColumnIsCompatible(string $agenciaIdColumn): void
    {
        $column = DB::selectOne("SHOW COLUMNS FROM agencia_horarios WHERE Field = 'agencia_id'");
        $type = strtolower((string) ($column->Type ?? ''));
        $isCompatible = match ($agenciaIdColumn) {
            'bigint unsigned' => str_contains($type, 'bigint') && str_contains($type, 'unsigned'),
            'bigint' => str_contains($type, 'bigint') && !str_contains($type, 'unsigned'),
            'int unsigned' => str_contains($type, 'int') && !str_contains($type, 'bigint') && str_contains($type, 'unsigned'),
            default => str_contains($type, 'int') && !str_contains($type, 'bigint') && !str_contains($type, 'unsigned'),
        };

        if ($isCompatible) {
            return;
        }

        $definition = strtoupper($agenciaIdColumn);
        DB::statement("ALTER TABLE agencia_horarios MODIFY agencia_id {$definition} NOT NULL");
    }
};
