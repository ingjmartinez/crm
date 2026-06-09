<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cruce_usuario_seguimientos')) {
            return;
        }

        $this->deduplicateByCedula();

        Schema::table('cruce_usuario_seguimientos', function (Blueprint $table) {
            if ($this->indexExists('cruce_usuario_seguimientos', 'cruce_usuario_seguimiento_unique')) {
                $table->dropUnique('cruce_usuario_seguimiento_unique');
            }

            if (!$this->indexExists('cruce_usuario_seguimientos', 'cruce_usuario_seguimientos_cedula_unique')) {
                $table->unique('cedula', 'cruce_usuario_seguimientos_cedula_unique');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('cruce_usuario_seguimientos')) {
            return;
        }

        Schema::table('cruce_usuario_seguimientos', function (Blueprint $table) {
            if ($this->indexExists('cruce_usuario_seguimientos', 'cruce_usuario_seguimientos_cedula_unique')) {
                $table->dropUnique('cruce_usuario_seguimientos_cedula_unique');
            }

            if (!$this->indexExists('cruce_usuario_seguimientos', 'cruce_usuario_seguimiento_unique')) {
                $table->unique(['cedula', 'ultima_fecha_venta', 'estatus_origen'], 'cruce_usuario_seguimiento_unique');
            }
        });
    }

    private function deduplicateByCedula(): void
    {
        $duplicadas = DB::table('cruce_usuario_seguimientos')
            ->select('cedula')
            ->groupBy('cedula')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('cedula');

        foreach ($duplicadas as $cedula) {
            $filas = DB::table('cruce_usuario_seguimientos')
                ->where('cedula', $cedula)
                ->orderByRaw("FIELD(estado, 'finalizado', 'en_gestion', 'pendiente') ASC")
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get();

            $principal = $filas->first();

            if (!$principal) {
                continue;
            }

            DB::table('cruce_usuario_seguimientos')
                ->where('cedula', $cedula)
                ->where('id', '<>', $principal->id)
                ->delete();
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }
};
