<?php

namespace App\Services\Operaciones;

use App\Models\CentroDeCosto;
use App\Models\CuentaContable;
use App\Models\DistribucionGastoRutaMapeo;
use App\Models\MovimientoRutaV2Gasto;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClasificacionGastoRutaService
{
    /** @return Collection<int, array{codigo: string, descripcion: string, distribucion: string}> */
    public function cuentas(): Collection
    {
        $cuentas = CuentaContable::query()
            ->whereIn('cuenta', MovimientoRutaV2Gasto::CUENTAS_DISTRIBUCION)
            ->get(['cuenta', 'descripcion'])
            ->keyBy('cuenta');

        return collect(MovimientoRutaV2Gasto::CUENTAS_DISTRIBUCION)
            ->map(function (string $codigo) use ($cuentas): ?array {
                $cuenta = $cuentas->get($codigo);

                return $cuenta ? [
                    'codigo' => $codigo,
                    'descripcion' => (string) $cuenta->descripcion,
                    'distribucion' => $codigo === MovimientoRutaV2Gasto::CUENTA_COMBUSTIBLE ? 'ruta' : 'terminal',
                ] : null;
            })
            ->filter()
            ->values();
    }

    /** @return Collection<int, array<string, int|string>> */
    public function terminales(string $rutaKey): Collection
    {
        $mapeos = DistribucionGastoRutaMapeo::query()
            ->where('ruta_key', $this->normalizarRuta($rutaKey))
            ->get();
        $centros = CentroDeCosto::query()
            ->where('inactivo', false)
            ->where('ocultar', false)
            ->whereNotNull('id_viejo')
            ->get(['id', 'id_viejo', 'descripcion', 'company_id', 'id_grupo', 'id_sub_grupo']);

        return $mapeos->flatMap(function (DistribucionGastoRutaMapeo $mapeo) use ($centros): Collection {
            return $centros
                ->filter(fn (CentroDeCosto $centro): bool => $this->codigoCampo($centro->company_id) === $mapeo->company_id
                    && $this->codigoCampo($centro->id_grupo) === $mapeo->id_grupo
                    && $this->codigoCampo($centro->id_sub_grupo) === $mapeo->id_sub_grupo)
                ->map(fn (CentroDeCosto $centro): array => [
                    'centro_costo_id' => (int) $centro->id,
                    'terminal' => trim((string) $centro->id_viejo),
                    'agencia' => trim((string) $centro->descripcion) ?: 'Agencia sin nombre',
                    'socio_codigo' => (string) $mapeo->id_sub_grupo,
                    'socio' => (string) $mapeo->nombre_socio,
                ]);
        })->unique(fn (array $terminal): string => $this->normalizarTerminal($terminal['terminal']))
            ->sortBy(fn (array $terminal): string => str_pad($this->normalizarTerminal($terminal['terminal']), 20, '0', STR_PAD_LEFT))
            ->values();
    }

    /** @param array{cuenta_codigo: string, distribucion_tipo: string, centro_costo_id?: int|null} $datos @return array<string, mixed> */
    public function resolver(string $rutaKey, array $datos, int|string|null $usuarioId): array
    {
        $cuenta = $this->cuentas()->firstWhere('codigo', $datos['cuenta_codigo']);

        if ($cuenta === null) {
            throw ValidationException::withMessages(['cuenta_codigo' => 'La cuenta seleccionada no está disponible.']);
        }

        $esCombustible = $datos['cuenta_codigo'] === MovimientoRutaV2Gasto::CUENTA_COMBUSTIBLE;
        $tipo = $esCombustible ? 'ruta' : $datos['distribucion_tipo'];

        if (! $esCombustible && $tipo !== 'terminal') {
            throw ValidationException::withMessages([
                'distribucion_tipo' => 'Esta cuenta debe asignarse directamente a una terminal.',
            ]);
        }

        $terminal = null;
        if ($tipo === 'terminal') {
            $terminal = $this->terminales($rutaKey)->firstWhere('centro_costo_id', (int) ($datos['centro_costo_id'] ?? 0));

            if ($terminal === null) {
                throw ValidationException::withMessages([
                    'centro_costo_id' => 'La terminal seleccionada no pertenece a los socios configurados para esta ruta.',
                ]);
            }
        }

        return [
            'cuenta_codigo' => $cuenta['codigo'],
            'cuenta_descripcion' => $cuenta['descripcion'],
            'distribucion_tipo' => $tipo,
            'centro_costo_id' => $terminal['centro_costo_id'] ?? null,
            'terminal_destino' => $terminal['terminal'] ?? null,
            'agencia_destino' => $terminal['agencia'] ?? null,
            'socio_codigo' => $terminal['socio_codigo'] ?? null,
            'socio_nombre' => $terminal['socio'] ?? null,
            'clasificado_por_id' => is_numeric($usuarioId) ? (int) $usuarioId : null,
            'clasificado_at' => now(),
        ];
    }

    private function codigoCampo(mixed $valor): string
    {
        $codigo = trim((string) preg_split('/\s*-\s*/u', trim((string) $valor), 2)[0]);

        return preg_replace('/\D/u', '', $codigo) ?? '';
    }

    private function normalizarRuta(string $ruta): string
    {
        return Str::upper(trim(preg_replace('/\s+/u', ' ', $ruta) ?? $ruta));
    }

    private function normalizarTerminal(string $terminal): string
    {
        return ltrim(trim($terminal), '0');
    }
}
