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

                if ($agenciaIdColumn === 'unsignedBigInteger') {
                    $table->unsignedBigInteger('agencia_id');
                } else {
                    $table->unsignedInteger('agencia_id');
                }

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

        $this->ensureForeignKeyExists();

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

        return str_contains($type, 'bigint') ? 'unsignedBigInteger' : 'unsignedInteger';
    }

    private function ensureAgenciaIdColumnIsCompatible(string $agenciaIdColumn): void
    {
        $column = DB::selectOne("SHOW COLUMNS FROM agencia_horarios WHERE Field = 'agencia_id'");
        $type = strtolower((string) ($column->Type ?? ''));
        $needsBigInteger = $agenciaIdColumn === 'unsignedBigInteger';
        $isBigInteger = str_contains($type, 'bigint');

        if ($needsBigInteger === $isBigInteger) {
            return;
        }

        $definition = $needsBigInteger ? 'BIGINT UNSIGNED' : 'INT UNSIGNED';
        DB::statement("ALTER TABLE agencia_horarios MODIFY agencia_id {$definition} NOT NULL");
    }

    private function ensureForeignKeyExists(): void
    {
        $database = DB::getDatabaseName();
        $foreignKey = DB::selectOne(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = 'agencia_horarios'
               AND COLUMN_NAME = 'agencia_id'
               AND REFERENCED_TABLE_NAME = 'agencias'
               AND REFERENCED_COLUMN_NAME = 'id'
             LIMIT 1",
            [$database]
        );

        if ($foreignKey) {
            return;
        }

        Schema::table('agencia_horarios', function (Blueprint $table) {
            $table->foreign('agencia_id')
                ->references('id')
                ->on('agencias')
                ->cascadeOnDelete();
        });
    }
};
