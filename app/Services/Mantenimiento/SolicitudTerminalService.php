<?php

namespace App\Services\Mantenimiento;

use App\Models\Agencia;
use App\Models\SolicitudTerminalCodigo;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SolicitudTerminalService
{
    /** @return list<string> */
    public function sugerir(string $prefijoSeleccionado, int $cantidad): array
    {
        $prefijo = '05'.$prefijoSeleccionado;
        $ocupados = $this->codigosOcupados($prefijo)->flip();
        $disponibles = [];

        for ($secuencia = 0; $secuencia <= 9999; $secuencia++) {
            $codigo = $prefijo.str_pad((string) $secuencia, 4, '0', STR_PAD_LEFT);

            if (! $ocupados->has($codigo)) {
                $disponibles[] = $codigo;
            }
        }

        if (count($disponibles) < $cantidad) {
            throw ValidationException::withMessages([
                'cantidad' => "El prefijo {$prefijo} solo tiene ".count($disponibles).' códigos disponibles.',
            ]);
        }

        shuffle($disponibles);

        return array_slice($disponibles, 0, $cantidad);
    }

    /** @param list<string> $codigos */
    public function asegurarDisponibles(array $codigos): void
    {
        $enAgencias = Agencia::query()->whereIn('terminal', $codigos)->exists();
        $enSolicitudes = SolicitudTerminalCodigo::query()->whereIn('codigo', $codigos)->exists();

        if ($enAgencias || $enSolicitudes) {
            throw ValidationException::withMessages([
                'codigos' => 'Uno o más códigos ya fueron utilizados. Genera una sugerencia nueva.',
            ]);
        }
    }

    /** @return Collection<int, string> */
    private function codigosOcupados(string $prefijo): Collection
    {
        $terminales = Agencia::query()
            ->where('terminal', 'like', $prefijo.'%')
            ->pluck('terminal');

        $reservados = SolicitudTerminalCodigo::query()
            ->where('codigo', 'like', $prefijo.'%')
            ->pluck('codigo');

        return $terminales
            ->merge($reservados)
            ->map(fn (mixed $codigo): string => trim((string) $codigo))
            ->filter(fn (string $codigo): bool => preg_match('/^\d{8}$/', $codigo) === 1)
            ->unique()
            ->values();
    }
}
