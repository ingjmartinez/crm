<?php

namespace App\Services\Bi;

use App\Models\Agencia;
use App\Models\BiVentaSnapshot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AgenciaTerminalCatalogo
{
    public function __construct(private readonly ClasificarVentaBi $clasificador) {}

    /** @return array<int, string> */
    public function zonas(?string $empresa = null): array
    {
        return Agencia::query()->when($empresa, fn (Builder $query): Builder => $query->where('empresa', $empresa))
            ->whereNotNull('zona')->where('zona', '<>', '')
            ->distinct()->orderBy('zona')->pluck('zona')->all();
    }

    /** @return array<int, string> */
    public function rutas(?string $empresa = null, ?string $zona = null): array
    {
        return Agencia::query()->when($empresa, fn (Builder $query): Builder => $query->where('empresa', $empresa))
            ->when($zona, fn (Builder $query): Builder => $query->where('zona', $zona))
            ->whereNotNull('ruta')->where('ruta', '<>', '')
            ->distinct()->orderBy('ruta')->pluck('ruta')->all();
    }

    /** @return array<int, string> */
    public function empresas(): array
    {
        return Agencia::query()->whereNotNull('empresa')->where('empresa', '<>', '')
            ->distinct()->orderBy('empresa')->pluck('empresa')->all();
    }

    /** @return array<int, array{terminal: string, nombre: string}> */
    public function paraAlertas(?string $zona = null, ?string $empresa = null, ?string $ruta = null): array
    {
        return Agencia::lotobet()->when($zona, fn (Builder $query): Builder => $query->where('zona', $zona))
            ->when($empresa, fn (Builder $query): Builder => $query->where('empresa', $empresa))
            ->when($ruta, fn (Builder $query): Builder => $query->where('ruta', $ruta))
            ->whereNotNull('terminal')->where('terminal', '<>', '')
            ->orderBy('id')->get(['terminal', 'nombre_agencia', 'agencia'])
            ->map(fn (Agencia $agencia): array => [
                'terminal' => ltrim(trim((string) $agencia->terminal), '0') ?: '0',
                'nombre' => (string) ($agencia->nombre_agencia ?: $agencia->agencia ?: $agencia->terminal),
            ])->unique('terminal')->sortBy('terminal', SORT_NATURAL)->values()->all();
    }

    public function ultimaActualizacion(): ?string
    {
        $fecha = Agencia::query()->max('updated_at');

        return $fecha ? substr((string) $fecha, 0, 10) : null;
    }

    public function paginar(?string $busqueda, ?string $zona = null, ?string $empresa = null, ?string $ruta = null, ?BiVentaSnapshot $snapshot = null): LengthAwarePaginator
    {
        $query = Agencia::query()
            ->select(['id', 'terminal', 'agencia', 'nombre_agencia', 'empresa', 'ciudad', 'zona', 'estatus'])
            ->whereNotNull('terminal')
            ->where('terminal', '<>', '');

        if ($zona !== null && $zona !== '') {
            $query->where('zona', $zona);
        }

        if ($empresa !== null && $empresa !== '') {
            $query->where('empresa', $empresa);
        }

        if ($ruta !== null && $ruta !== '') {
            $query->where('ruta', $ruta);
        }

        if ($busqueda !== null && $busqueda !== '') {
            $query->where(function (Builder $agencias) use ($busqueda): void {
                $agencias->where('terminal', 'like', "%{$busqueda}%")
                    ->orWhere('agencia', 'like', "%{$busqueda}%")
                    ->orWhere('nombre_agencia', 'like', "%{$busqueda}%");
            });
        }

        $ventasPorTerminal = $snapshot?->productos_terminales;

        return $query->orderBy('terminal')->orderBy('id')->paginate(25)->withQueryString()
            ->through(function (Agencia $agencia) use ($ventasPorTerminal): array {
                $ventas = null;
                if ($ventasPorTerminal !== null) {
                    $terminal = ltrim(trim((string) $agencia->terminal), '0') ?: '0';
                    $ventas = ['tradicional' => 0.0, 'no_tradicional' => 0.0, 'total' => 0.0];
                    foreach ($ventasPorTerminal[$terminal] ?? [] as $producto => $monto) {
                        $monto = (float) $monto;
                        $categoria = $this->clasificador->categoria(['producto_id' => (string) $producto]);
                        if (isset($ventas[$categoria])) {
                            $ventas[$categoria] += $monto;
                        }
                        $ventas['total'] += $monto;
                    }
                }

                return [
                    ...$agencia->toArray(),
                    'venta_tradicional_hoy' => $ventas['tradicional'] ?? null,
                    'venta_no_tradicional_hoy' => $ventas['no_tradicional'] ?? null,
                    'venta_total_hoy' => $ventas['total'] ?? null,
                ];
            });
    }
}
