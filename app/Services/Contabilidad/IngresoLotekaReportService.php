<?php

namespace App\Services\Contabilidad;

use App\Models\Agencia;
use App\Models\CentroDeCosto;
use App\Models\VtUsuarioBet;
use Illuminate\Support\Collection;

class IngresoLotekaReportService
{
    /**
     * @return Collection<int, array{terminal: string, empresa_id: string, empresa: string, centro_costo: string, centro_costo_descripcion: string, monto: float, centro_costo_encontrado: bool}>
     */
    public function generate(string $fechaInicio, string $fechaFin, string $empresa = 'todas'): Collection
    {
        $ventas = $this->sales($fechaInicio, $fechaFin)
            ->groupBy(fn (object $venta): string => $this->normalizeTerminal($venta->terminal))
            ->map(function (Collection $ventasTerminal, string $terminal): array {
                return [
                    'terminal' => $this->displayTerminal($ventasTerminal->pluck('terminal')),
                    'terminal_normalizada' => $terminal,
                    'monto' => round((float) $ventasTerminal->sum('monto'), 2),
                ];
            })
            ->filter(fn (array $venta): bool => $venta['monto'] > 0)
            ->values();

        if ($ventas->isEmpty()) {
            return collect();
        }

        $terminales = $ventas->pluck('terminal_normalizada')->flip();
        $empresasPorTerminal = Agencia::query()
            ->whereNotNull('terminal')
            ->get(['terminal', 'empresa'])
            ->filter(fn (Agencia $agencia): bool => $terminales->has(
                $this->normalizeTerminal($agencia->terminal)
            ))
            ->groupBy(fn (Agencia $agencia): string => $this->normalizeTerminal($agencia->terminal))
            ->map(fn (Collection $agencias): ?string => $this->companyFromAgencies($agencias));

        $centrosPorTerminal = CentroDeCosto::query()
            ->where('inactivo', false)
            ->whereNotNull('id_viejo')
            ->get(['id_centro_costo', 'company_id', 'descripcion', 'id_viejo'])
            ->filter(fn (CentroDeCosto $centro): bool => $terminales->has(
                $this->normalizeTerminal($centro->id_viejo)
            ))
            ->groupBy(fn (CentroDeCosto $centro): string => $this->normalizeTerminal($centro->id_viejo));

        return $ventas
            ->map(function (array $venta) use ($centrosPorTerminal, $empresasPorTerminal): array {
                $centros = $centrosPorTerminal->get($venta['terminal_normalizada'], collect());
                $empresaId = $empresasPorTerminal->get($venta['terminal_normalizada'])
                    ?? $this->companyFromCostCenters($centros);
                $centro = $this->companyCostCenter($centros, $empresaId);

                return [
                    'terminal' => $venta['terminal'],
                    'empresa_id' => $empresaId,
                    'empresa' => $this->companyName($empresaId),
                    'centro_costo' => $centro?->id_centro_costo !== null
                        ? (string) $centro->id_centro_costo
                        : 'Sin centro de costo',
                    'centro_costo_descripcion' => trim((string) ($centro?->descripcion ?? '')),
                    'monto' => $venta['monto'],
                    'centro_costo_encontrado' => $centro !== null,
                ];
            })
            ->when($empresa !== 'todas', fn (Collection $registros): Collection => $registros
                ->where('empresa_id', $empresa))
            ->sortBy([
                ['empresa_id', 'asc'],
                [fn (array $venta): string => str_pad($venta['terminal'], 20, '0', STR_PAD_LEFT), 'asc'],
            ])
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $registros
     * @return Collection<int, array<string, mixed>>
     */
    public function distribute(Collection $registros, float $montoLoteka): Collection
    {
        $totalVentas = (float) $registros->sum('monto');

        if ($registros->isEmpty() || $totalVentas <= 0) {
            return $registros->map(function (array $registro): array {
                $registro['participacion'] = 0.0;
                $registro['monto_distribuido'] = 0.0;

                return $registro;
            });
        }

        $montoCentavos = (int) round($montoLoteka * 100);
        $asignaciones = $registros->map(function (array $registro) use ($totalVentas, $montoCentavos): array {
            $participacion = ((float) $registro['monto'] / $totalVentas) * 100;
            $centavosExactos = ($participacion / 100) * $montoCentavos;
            $centavosBase = (int) floor($centavosExactos);

            return [
                'centavos' => $centavosBase,
                'fraccion' => $centavosExactos - $centavosBase,
                'participacion' => round($participacion, 4),
            ];
        });

        $centavosRestantes = $montoCentavos - (int) $asignaciones->sum('centavos');
        $prioridades = $asignaciones->sortByDesc('fraccion')->keys()->values();

        for ($indice = 0; $indice < $centavosRestantes; $indice++) {
            $registroIndice = $prioridades[$indice % $prioridades->count()];
            $asignacion = $asignaciones->get($registroIndice);
            $asignacion['centavos']++;
            $asignaciones->put($registroIndice, $asignacion);
        }

        return $registros->map(function (array $registro, int $indice) use ($asignaciones): array {
            $registro['participacion'] = $asignaciones[$indice]['participacion'];
            $registro['monto_distribuido'] = $asignaciones[$indice]['centavos'] / 100;

            return $registro;
        });
    }

    /** @return Collection<int, object> */
    private function sales(string $fechaInicio, string $fechaFin): Collection
    {
        return VtUsuarioBet::query()
            ->from('vt_usuarios_bet as ventas')
            ->join('catalogo_juegos as juegos', 'juegos.producto_id', '=', 'ventas.producto_id')
            ->whereBetween('ventas.fecha', [$fechaInicio, $fechaFin])
            ->whereRaw("LOWER(TRIM(juegos.tipo)) = 'no tradicional'")
            ->whereNotNull('ventas.agencia_id')
            ->whereRaw("TRIM(CAST(ventas.agencia_id AS CHAR)) <> ''")
            ->selectRaw('TRIM(CAST(ventas.agencia_id AS CHAR)) AS terminal, SUM(ventas.monto) AS monto')
            ->groupByRaw('TRIM(CAST(ventas.agencia_id AS CHAR))')
            ->havingRaw('SUM(ventas.monto) > 0')
            ->get();
    }

    private function companyCostCenter(Collection $centros, string $empresaId): ?CentroDeCosto
    {
        $centrosEmpresa = $centros->filter(
            fn (CentroDeCosto $centro): bool => $this->companyFromCostCenter($centro) === $empresaId
        );

        return $centrosEmpresa->sortByDesc('id_centro_costo')->first();
    }

    private function companyFromAgencies(Collection $agencias): ?string
    {
        $empresas = $agencias
            ->pluck('empresa')
            ->map(fn (mixed $empresa): string => mb_strtoupper(trim((string) $empresa)))
            ->filter();

        if ($empresas->contains(
            fn (string $empresa): bool => $empresa === '169' || str_contains($empresa, 'NEGOSUR')
        )) {
            return '169';
        }

        return $empresas->isNotEmpty() ? '168' : null;
    }

    private function companyFromCostCenters(Collection $centros): string
    {
        $empresas = $centros
            ->map(fn (CentroDeCosto $centro): ?string => $this->companyFromCostCenter($centro))
            ->filter()
            ->unique()
            ->values();

        return $empresas->count() === 1 ? $empresas->first() : '168';
    }

    private function companyFromCostCenter(CentroDeCosto $centro): ?string
    {
        $empresa = substr(trim((string) $centro->company_id), 0, 3);

        return match ($empresa) {
            '168', '100' => '168',
            '169' => '169',
            default => null,
        };
    }

    private function companyName(string $empresaId): string
    {
        return $empresaId === '169' ? 'Negosur' : 'Grupo Joselito';
    }

    private function normalizeTerminal(mixed $terminal): string
    {
        $terminal = trim((string) $terminal);
        $normalized = ltrim($terminal, '0');

        return $normalized === '' ? '0' : $normalized;
    }

    private function displayTerminal(Collection $terminales): string
    {
        return $terminales
            ->map(fn (mixed $terminal): string => trim((string) $terminal))
            ->filter()
            ->sortByDesc(fn (string $terminal): int => strlen($terminal))
            ->first() ?? '';
    }
}
