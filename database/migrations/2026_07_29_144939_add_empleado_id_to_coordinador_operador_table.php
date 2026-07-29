<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('coordinador_operador', 'empleado_id')) {
            Schema::table('coordinador_operador', function (Blueprint $table) {
                $table->unsignedBigInteger('empleado_id')->nullable()->after('id');
                $table->unique('empleado_id', 'coordinador_operador_empleado_id_unique');
            });
        }

        Schema::table('coordinador_operador', function (Blueprint $table) {
            $table->string('cedula', 11)->nullable()->change();
        });

        $empleadosPorCedula = DB::table('empleados')
            ->whereNotNull('cedula')
            ->get(['id', 'cedula'])
            ->mapToGroups(fn (object $empleado): array => [
                preg_replace('/\D+/', '', (string) $empleado->cedula) => (int) $empleado->id,
            ])
            ->filter(fn ($ids, string $cedula): bool => $cedula !== '' && $ids->count() === 1);

        DB::table('coordinador_operador')
            ->whereNotNull('cedula')
            ->orderBy('id')
            ->each(function (object $coordinador) use ($empleadosPorCedula): void {
                $cedula = preg_replace('/\D+/', '', (string) $coordinador->cedula);
                $empleadoIds = $empleadosPorCedula->get($cedula);

                if ($empleadoIds?->count() !== 1) {
                    return;
                }

                DB::table('coordinador_operador')
                    ->where('id', $coordinador->id)
                    ->update(['empleado_id' => $empleadoIds->first()]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('coordinador_operador')
            ->whereNull('cedula')
            ->orderBy('id')
            ->each(function (object $coordinador): void {
                DB::table('coordinador_operador')
                    ->where('id', $coordinador->id)
                    ->update(['cedula' => str_pad((string) $coordinador->id, 11, '9', STR_PAD_LEFT)]);
            });

        Schema::table('coordinador_operador', function (Blueprint $table) {
            $table->dropUnique('coordinador_operador_empleado_id_unique');
            $table->dropColumn('empleado_id');
            $table->string('cedula', 11)->nullable(false)->change();
        });
    }
};
