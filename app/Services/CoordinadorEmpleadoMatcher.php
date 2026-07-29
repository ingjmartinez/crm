<?php

namespace App\Services;

use App\Models\CoordinadorOperador;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CoordinadorEmpleadoMatcher
{
    public function vincularPendientesPorCedula(): int
    {
        if (! Schema::hasTable('coordinador_operador') || ! Schema::hasTable('empleados')) {
            return 0;
        }

        $coordinadores = CoordinadorOperador::query()
            ->whereNull('empleado_id')
            ->whereNotNull('cedula')
            ->get(['id', 'cedula']);

        $cedulas = $coordinadores
            ->map(fn (CoordinadorOperador $coordinador): string => $this->normalizarCedula($coordinador->cedula))
            ->filter()
            ->unique()
            ->values();

        if ($cedulas->isEmpty()) {
            return 0;
        }

        $empleadosPorCedula = DB::table('empleados')
            ->whereNotNull('cedula')
            ->whereIn(
                DB::raw("REPLACE(REPLACE(REPLACE(TRIM(CAST(cedula AS CHAR)), '-', ''), ' ', ''), '.', '')"),
                $cedulas
            )
            ->get(['id', 'cedula'])
            ->groupBy(fn (object $empleado): string => $this->normalizarCedula($empleado->cedula))
            ->filter(fn ($empleados): bool => $empleados->count() === 1)
            ->map(fn ($empleados): int => (int) $empleados->first()->id);

        $empleadosYaAsignados = CoordinadorOperador::query()
            ->whereNotNull('empleado_id')
            ->pluck('empleado_id')
            ->mapWithKeys(fn ($empleadoId): array => [(int) $empleadoId => true]);

        $vinculados = 0;

        foreach ($coordinadores as $coordinador) {
            $empleadoId = $empleadosPorCedula->get($this->normalizarCedula($coordinador->cedula));

            if (! $empleadoId || $empleadosYaAsignados->has($empleadoId)) {
                continue;
            }

            $actualizados = CoordinadorOperador::query()
                ->whereKey($coordinador->id)
                ->whereNull('empleado_id')
                ->update(['empleado_id' => $empleadoId]);

            if ($actualizados === 1) {
                $empleadosYaAsignados->put($empleadoId, true);
                $vinculados++;
            }
        }

        return $vinculados;
    }

    private function normalizarCedula(mixed $cedula): string
    {
        return preg_replace('/\D+/', '', (string) $cedula);
    }
}
