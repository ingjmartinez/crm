<?php

namespace App\Services\Operaciones;

use App\Models\Agencia;
use Illuminate\Database\Query\Expression;

class MovimientosRutasAgenciaService
{
    /**
     * @param  array<int, array<string, float|string>>  $transacciones
     * @return array<int, array<string, float|string>>
     */
    public function enriquecer(array $transacciones): array
    {
        $terminales = collect($transacciones)
            ->pluck('terminal')
            ->map(fn (mixed $terminal): string => $this->normalizarTerminal($terminal))
            ->filter(fn (string $terminal): bool => $terminal !== '0')
            ->unique()
            ->values();

        if ($terminales->isEmpty()) {
            return $this->aplicarMapa($transacciones, []);
        }

        $terminalNormalizada = new Expression(
            "COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(CAST(terminal AS CHAR))), ''), '0')"
        );
        $agencias = Agencia::query()
            ->select('terminal', 'nombre_agencia')
            ->whereNotNull('terminal')
            ->whereIn($terminalNormalizada, $terminales->all())
            ->get()
            ->mapWithKeys(fn (Agencia $agencia): array => [
                $this->normalizarTerminal($agencia->terminal) => trim((string) $agencia->nombre_agencia),
            ])
            ->all();

        return $this->aplicarMapa($transacciones, $agencias);
    }

    /**
     * @param  array<int, array<string, float|string>>  $transacciones
     * @param  array<string, string>  $agencias
     * @return array<int, array<string, float|string>>
     */
    public function aplicarMapa(array $transacciones, array $agencias): array
    {
        return array_map(function (array $transaccion) use ($agencias): array {
            $terminal = $this->normalizarTerminal($transaccion['terminal'] ?? '');

            if (! array_key_exists($terminal, $agencias)) {
                $transaccion['nombre_agencia'] = 'Terminal no registrada';

                return $transaccion;
            }

            $nombreAgencia = trim($agencias[$terminal]);
            $transaccion['nombre_agencia'] = $nombreAgencia !== ''
                ? $nombreAgencia
                : 'Sin nombre registrado';

            return $transaccion;
        }, $transacciones);
    }

    private function normalizarTerminal(mixed $terminal): string
    {
        $terminal = ltrim(trim((string) $terminal), '0');

        return $terminal === '' ? '0' : $terminal;
    }
}
