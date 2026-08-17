<?php

namespace App\Services\Contabilidad;

use App\Models\Agencia;
use App\Models\CentroDeCosto;
use App\Models\DistribucionGastoRutaMapeo;
use App\Models\MovimientoRutaV2Gasto;
use App\Models\Ruta;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DistribucionGastoRutaService
{
    /**
     * @param  array{ruta_key: string, id_grupo: string, id_sub_grupo: string, company_id: string}  $datos
     * @return array{mapeo: DistribucionGastoRutaMapeo, terminales: int}
     */
    public function guardarMapeo(array $datos, int|string|null $userId): array
    {
        $gasto = MovimientoRutaV2Gasto::query()
            ->where('estado', 'aplicado')
            ->get(['ruta_key', 'ruta'])
            ->first(fn (MovimientoRutaV2Gasto $movimiento): bool => $this->normalizarRuta($movimiento->ruta_key) === $this->normalizarRuta($datos['ruta_key']));

        if (! $gasto instanceof MovimientoRutaV2Gasto) {
            throw ValidationException::withMessages(['ruta_key' => 'La ruta seleccionada no tiene gastos aplicados.']);
        }

        $centros = $this->centrosActivos()->filter(function (CentroDeCosto $centro) use ($datos): bool {
            return $this->codigoCampo($centro->id_grupo) === $datos['id_grupo']
                && $this->codigoCampo($centro->id_sub_grupo) === $datos['id_sub_grupo']
                && (($datos['company_id'] ?? null) === null || $this->codigoCampo($centro->company_id) === $datos['company_id']);
        });

        if ($centros->isEmpty()) {
            throw ValidationException::withMessages([
                'id_sub_grupo' => 'No existen terminales activas con esa combinacion de Ruta empresa (id_grupo) y socio (id_sub_grupo).',
            ]);
        }

        $empresas = $centros->map(fn (CentroDeCosto $centro): string => $this->codigoCampo($centro->company_id))
            ->filter()->unique()->values();
        if (($datos['company_id'] ?? null) === null && $empresas->count() > 1) {
            throw ValidationException::withMessages([
                'company_id' => 'La combinacion existe en varias empresas. Indique uno de estos ID: '.$empresas->implode(', ').'.',
            ]);
        }

        $companyId = (string) (($datos['company_id'] ?? null) ?: $empresas->first());
        $centros = $centros->filter(fn (CentroDeCosto $centro): bool => $this->codigoCampo($centro->company_id) === $companyId);
        $primerCentro = $centros->first();
        $grupo = $this->parsearCodigoNombre($primerCentro->id_grupo);
        $socio = $this->parsearCodigoNombre($primerCentro->id_sub_grupo);

        $mapeo = DistribucionGastoRutaMapeo::query()->updateOrCreate(
            [
                'ruta_key' => $this->normalizarRuta($gasto->ruta_key),
                'company_id' => $companyId,
                'id_grupo' => $datos['id_grupo'],
                'id_sub_grupo' => $datos['id_sub_grupo'],
            ],
            [
                'ruta_nombre' => trim((string) $gasto->ruta),
                'nombre_grupo' => $grupo['nombre'],
                'nombre_socio' => $socio['nombre'],
                'user_id' => is_numeric($userId) ? (int) $userId : null,
            ],
        );

        return [
            'mapeo' => $mapeo,
            'terminales' => $centros->pluck('id_viejo')->map(fn (mixed $terminal): string => $this->normalizarTerminal($terminal))->unique()->count(),
        ];
    }

    /**
     * @return array{
     *   data: array<int, array<string, mixed>>,
     *   detalle: array<int, array<string, mixed>>,
     *   rutas: array<int, array<string, mixed>>,
     *   incidencias: array<int, array<string, mixed>>,
     *   meta: array<string, float|int|string>
     * }
     */
    public function generar(string $fechaInicio, string $fechaFin, ?string $empresa = null, ?string $rutaKey = null): array
    {
        $gastos = $this->gastosPorRuta($fechaInicio, $fechaFin, $rutaKey);
        $gastosPendientesClasificacion = $this->gastosPendientesClasificacion($fechaInicio, $fechaFin, $rutaKey);
        $rutas = Ruta::query()->with('agencias')->get();
        $centrosActivos = $this->centrosActivos();
        $centrosPorTerminal = $centrosActivos->groupBy(fn (CentroDeCosto $centro): string => $this->normalizarTerminal($centro->id_viejo));
        $mapeosPorRuta = DistribucionGastoRutaMapeo::query()->get()
            ->groupBy(fn (DistribucionGastoRutaMapeo $mapeo): string => $this->normalizarRuta($mapeo->ruta_key));
        $gastosPendientesClasificacion = $gastosPendientesClasificacion->filter(function (MovimientoRutaV2Gasto $gasto) use ($empresa, $mapeosPorRuta): bool {
            if ($empresa === null) {
                return true;
            }

            $mapeos = $mapeosPorRuta->get($this->normalizarRuta($gasto->ruta_key), collect());

            return ($this->empresaDesdeMapeos($mapeos) ?? $this->empresaDesdeNombre($gasto->ruta)) === $empresa;
        })->values();
        $agenciasPorTerminal = Agencia::query()->get(['terminal', 'nombre_agencia', 'agencia'])
            ->filter(fn (Agencia $agencia): bool => $this->normalizarTerminal($agencia->terminal) !== '')
            ->keyBy(fn (Agencia $agencia): string => $this->normalizarTerminal($agencia->terminal));
        $detalle = collect();
        $resumenRutas = collect();
        $incidencias = collect();
        $totalGastosCentavos = 0;

        foreach ($gastosPendientesClasificacion as $gastoPendiente) {
            $incidencias->push([
                'tipo' => 'pendiente_clasificacion',
                'ruta' => $gastoPendiente->ruta,
                'terminal' => '',
                'agencia' => '',
                'detalle' => 'El gasto debe clasificarse desde Movimientos por Ruta V2 antes de distribuirse.',
                'monto_pendiente' => (float) $gastoPendiente->monto,
            ]);
        }

        foreach ($gastos as $gasto) {
            $empresaGasto = $this->empresaDesdeNombre((string) $gasto['ruta']);
            $mapeos = $mapeosPorRuta->get($this->normalizarRuta((string) $gasto['ruta_key']), collect());

            if ($gasto['distribucion_tipo'] === 'terminal') {
                $empresaDestino = $this->empresaDesdeMapeos($mapeos) ?? $empresaGasto;
                if ($empresa !== null && $empresaDestino !== $empresa) {
                    continue;
                }

                $montoRutaCentavos = (int) round(((float) $gasto['monto']) * 100);
                $totalGastosCentavos += $montoRutaCentavos;
                $fila = [
                    'ruta_key' => (string) $gasto['ruta_key'],
                    'ruta' => (string) $gasto['ruta'],
                    'empresa' => $this->nombreEmpresa($empresaDestino),
                    'terminal' => (string) $gasto['terminal_destino'],
                    'agencia' => (string) ($gasto['agencia_destino'] ?: 'Agencia sin nombre'),
                    'socio_key' => (string) $gasto['socio_codigo'],
                    'socio' => (string) $gasto['socio_nombre'],
                    'estado' => 'asignado',
                    'cuenta_codigo' => (string) $gasto['cuenta_codigo'],
                    'cuenta_descripcion' => (string) $gasto['cuenta_descripcion'],
                    'gasto_ruta' => $montoRutaCentavos / 100,
                    'total_agencias_ruta' => 1,
                    'participacion' => 100,
                    'gasto_agencia' => $montoRutaCentavos / 100,
                ];
                $detalle->push($fila);
                $resumenRutas->push([
                    'ruta_key' => (string) $gasto['ruta_key'],
                    'ruta' => (string) $gasto['ruta'],
                    'empresa' => $this->nombreEmpresa($empresaDestino),
                    'gastos' => 1,
                    'agencias' => 1,
                    'socios' => 1,
                    'gasto_ruta' => $montoRutaCentavos / 100,
                    'asignado_socios' => $montoRutaCentavos / 100,
                    'pendiente' => 0,
                    'estado' => 'distribuida',
                ]);

                continue;
            }

            if ($mapeos->isNotEmpty()) {
                $empresaMapeo = $this->empresaDesdeMapeos($mapeos) ?? $empresaGasto;
                if ($empresa !== null && $empresaMapeo !== $empresa) {
                    continue;
                }

                $montoRutaCentavos = (int) round(((float) $gasto['monto']) * 100);
                $totalGastosCentavos += $montoRutaCentavos;
                $this->agregarDistribucionManual(
                    $gasto,
                    $mapeos,
                    $centrosActivos,
                    $agenciasPorTerminal,
                    $montoRutaCentavos,
                    $empresaMapeo,
                    $detalle,
                    $resumenRutas,
                    $incidencias,
                );

                continue;
            }

            [$ruta, $estadoRuta] = $this->buscarRuta($rutas, (string) $gasto['ruta'], $empresaGasto);
            $empresaRuta = $ruta instanceof Ruta ? $this->empresaDesdeRuta($ruta) : $empresaGasto;

            if ($empresa !== null && $empresaRuta !== $empresa) {
                continue;
            }

            $montoRutaCentavos = (int) round(((float) $gasto['monto']) * 100);
            $totalGastosCentavos += $montoRutaCentavos;

            if (! $ruta instanceof Ruta) {
                $incidencias->push([
                    'tipo' => $estadoRuta,
                    'ruta' => $gasto['ruta'],
                    'terminal' => '',
                    'agencia' => '',
                    'detalle' => $estadoRuta === 'ruta_ambigua'
                        ? 'El nombre coincide con mas de una ruta del maestro.'
                        : 'La ruta del gasto no existe en el maestro de rutas.',
                    'monto_pendiente' => $montoRutaCentavos / 100,
                ]);
                $resumenRutas->push($this->resumenRutaSinDistribuir($gasto, $empresaRuta, $montoRutaCentavos, $estadoRuta));

                continue;
            }

            $agencias = $ruta->agencias
                ->unique(fn ($agencia): string => (string) $agencia->getKey())
                ->sortBy(fn ($agencia): string => $this->normalizarTerminal($agencia->terminal).'-'.str_pad((string) $agencia->getKey(), 12, '0', STR_PAD_LEFT))
                ->values();

            if ($agencias->isEmpty()) {
                $incidencias->push([
                    'tipo' => 'ruta_sin_agencias',
                    'ruta' => $gasto['ruta'],
                    'terminal' => '',
                    'agencia' => '',
                    'detalle' => 'La ruta no tiene agencias asignadas.',
                    'monto_pendiente' => $montoRutaCentavos / 100,
                ]);
                $resumenRutas->push($this->resumenRutaSinDistribuir($gasto, $empresaRuta, $montoRutaCentavos, 'ruta_sin_agencias'));

                continue;
            }

            $asignaciones = $this->distribuirCentavos($montoRutaCentavos, $agencias->count());
            $filasRuta = collect();

            foreach ($agencias as $index => $agencia) {
                $terminalOriginal = trim((string) $agencia->terminal);
                $terminal = $this->normalizarTerminal($terminalOriginal);
                $resolucion = $this->resolverSocio($centrosPorTerminal, $terminal, $empresaRuta);
                $montoAgenciaCentavos = $asignaciones[$index];
                $nombreAgencia = trim((string) ($agencia->nombre_agencia ?? $agencia->nombre ?? $agencia->agencia ?? ''));
                $estado = $terminal === '' ? 'sin_terminal' : $resolucion['estado'];
                $socio = $estado === 'asignado' ? $resolucion['nombre'] : 'Sin socio asignado';
                $socioKey = $estado === 'asignado' ? $resolucion['key'] : "pendiente:{$estado}";

                $fila = [
                    'ruta_key' => (string) $gasto['ruta_key'],
                    'ruta' => (string) ($ruta->nombre_ruta ?? $gasto['ruta']),
                    'empresa' => $this->nombreEmpresa($empresaRuta),
                    'terminal' => $terminalOriginal,
                    'agencia' => $nombreAgencia !== '' ? $nombreAgencia : 'Agencia sin nombre',
                    'socio_key' => $socioKey,
                    'socio' => $socio,
                    'estado' => $estado,
                    'cuenta_codigo' => (string) $gasto['cuenta_codigo'],
                    'cuenta_descripcion' => (string) $gasto['cuenta_descripcion'],
                    'gasto_ruta' => $montoRutaCentavos / 100,
                    'total_agencias_ruta' => $agencias->count(),
                    'participacion' => round(100 / $agencias->count(), 6),
                    'gasto_agencia' => $montoAgenciaCentavos / 100,
                ];
                $detalle->push($fila);
                $filasRuta->push($fila);

                if ($estado !== 'asignado') {
                    $incidencias->push([
                        'tipo' => $estado,
                        'ruta' => $fila['ruta'],
                        'terminal' => $terminalOriginal,
                        'agencia' => $fila['agencia'],
                        'detalle' => $this->detalleIncidenciaSocio($estado),
                        'monto_pendiente' => $fila['gasto_agencia'],
                    ]);
                }
            }

            $montoAsignado = (float) $filasRuta->where('estado', 'asignado')->sum('gasto_agencia');
            $resumenRutas->push([
                'ruta_key' => (string) $gasto['ruta_key'],
                'ruta' => (string) ($ruta->nombre_ruta ?? $gasto['ruta']),
                'empresa' => $this->nombreEmpresa($empresaRuta),
                'gastos' => (int) $gasto['cantidad_gastos'],
                'agencias' => $agencias->count(),
                'socios' => $filasRuta->where('estado', 'asignado')->pluck('socio_key')->unique()->count(),
                'gasto_ruta' => $montoRutaCentavos / 100,
                'asignado_socios' => round($montoAsignado, 2),
                'pendiente' => round(($montoRutaCentavos / 100) - $montoAsignado, 2),
                'estado' => $filasRuta->every(fn (array $fila): bool => $fila['estado'] === 'asignado') ? 'distribuida' : 'con_incidencias',
            ]);
        }

        $totalesPorCuentaRuta = $gastos
            ->groupBy(fn (array $gasto): string => $gasto['ruta_key'].'|'.$gasto['cuenta_codigo'])
            ->map(fn (Collection $gastosCuenta): float => round((float) $gastosCuenta->sum('monto'), 2));
        $resumenSocios = $detalle
            ->groupBy(fn (array $fila): string => $fila['ruta_key'].'|'.$fila['cuenta_codigo'].'|'.$fila['socio_key'])
            ->map(function (Collection $filas) use ($totalesPorCuentaRuta): array {
                $primera = $filas->first();
                $gastoSocio = (float) $filas->sum('gasto_agencia');
                $gastoRuta = (float) $totalesPorCuentaRuta->get(
                    $primera['ruta_key'].'|'.$primera['cuenta_codigo'],
                    $primera['gasto_ruta'],
                );

                return [
                    'ruta' => $primera['ruta'],
                    'empresa' => $primera['empresa'],
                    'cuenta_codigo' => $primera['cuenta_codigo'],
                    'cuenta_descripcion' => $primera['cuenta_descripcion'],
                    'socio' => $primera['socio'],
                    'estado' => $primera['estado'] === 'asignado' ? 'asignado' : 'pendiente',
                    'agencias' => $filas->pluck('terminal')->unique()->count(),
                    'gasto_ruta' => $gastoRuta,
                    'participacion' => $gastoRuta !== 0.0 ? round(($gastoSocio / $gastoRuta) * 100, 6) : 0,
                    'gasto_socio' => round($gastoSocio, 2),
                ];
            })
            ->sortBy([
                ['ruta', 'asc'],
                ['cuenta_codigo', 'asc'],
                ['estado', 'asc'],
                ['socio', 'asc'],
            ])
            ->values();

        $resumenRutas = $resumenRutas
            ->groupBy('ruta_key')
            ->map(function (Collection $filasRuta) use ($detalle): array {
                $primera = $filasRuta->first();
                $detalleRuta = $detalle->where('ruta_key', $primera['ruta_key']);
                $pendiente = (float) $filasRuta->sum('pendiente');

                return [
                    'ruta_key' => $primera['ruta_key'],
                    'ruta' => $primera['ruta'],
                    'empresa' => $primera['empresa'],
                    'gastos' => (int) $filasRuta->sum('gastos'),
                    'agencias' => $detalleRuta->pluck('terminal')->unique()->count(),
                    'socios' => $detalleRuta->where('estado', 'asignado')->pluck('socio_key')->unique()->count(),
                    'gasto_ruta' => round((float) $filasRuta->sum('gasto_ruta'), 2),
                    'asignado_socios' => round((float) $filasRuta->sum('asignado_socios'), 2),
                    'pendiente' => round($pendiente, 2),
                    'estado' => $pendiente === 0.0 ? 'distribuida' : 'con_incidencias',
                ];
            })->values();

        $totalAsignado = (float) $detalle->where('estado', 'asignado')->sum('gasto_agencia');
        $totalDistribuidoAgencias = (float) $detalle->sum('gasto_agencia');
        $totalGastos = $totalGastosCentavos / 100;

        return [
            'data' => $resumenSocios->all(),
            'detalle' => $detalle->sortBy([['ruta', 'asc'], ['socio', 'asc'], ['terminal', 'asc']])->values()->all(),
            'rutas' => $resumenRutas->sortBy('ruta')->values()->all(),
            'incidencias' => $incidencias->sortBy([['ruta', 'asc'], ['terminal', 'asc']])->values()->all(),
            'meta' => [
                'fecha_ini' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'empresa' => $empresa ?? 'todas',
                'total_gastos' => round($totalGastos, 2),
                'total_asignado_socios' => round($totalAsignado, 2),
                'total_distribuido_agencias' => round($totalDistribuidoAgencias, 2),
                'total_pendiente' => round($totalGastos - $totalAsignado, 2),
                'total_rutas' => $resumenRutas->count(),
                'total_agencias' => $detalle->unique(fn (array $fila): string => $fila['ruta_key'].'|'.$fila['terminal'])->count(),
                'total_socios' => $detalle->where('estado', 'asignado')->pluck('socio_key')->unique()->count(),
                'total_incidencias' => $incidencias->count(),
                'total_pendiente_clasificar' => round((float) $gastosPendientesClasificacion->sum('monto'), 2),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $gasto
     * @param  Collection<int, DistribucionGastoRutaMapeo>  $mapeos
     * @param  Collection<int, CentroDeCosto>  $centros
     * @param  Collection<string, Agencia>  $agenciasPorTerminal
     * @param  Collection<int, array<string, mixed>>  $detalle
     * @param  Collection<int, array<string, mixed>>  $resumenRutas
     * @param  Collection<int, array<string, mixed>>  $incidencias
     */
    private function agregarDistribucionManual(
        array $gasto,
        Collection $mapeos,
        Collection $centros,
        Collection $agenciasPorTerminal,
        int $montoRutaCentavos,
        ?string $empresa,
        Collection $detalle,
        Collection $resumenRutas,
        Collection $incidencias,
    ): void {
        $terminales = collect();

        foreach ($mapeos as $mapeo) {
            $coincidencias = $centros->filter(fn (CentroDeCosto $centro): bool => $this->codigoCampo($centro->company_id) === $mapeo->company_id
                && $this->codigoCampo($centro->id_grupo) === $mapeo->id_grupo
                && $this->codigoCampo($centro->id_sub_grupo) === $mapeo->id_sub_grupo
            );

            foreach ($coincidencias as $centro) {
                $terminal = $this->normalizarTerminal($centro->id_viejo);
                $asignaciones = $terminales->get($terminal, collect());
                $asignaciones->push([
                    'terminal_original' => trim((string) $centro->id_viejo),
                    'socio_key' => (string) $mapeo->id_sub_grupo,
                    'socio' => (string) $mapeo->nombre_socio,
                    'descripcion' => trim((string) $centro->descripcion),
                ]);
                $terminales->put($terminal, $asignaciones);
            }
        }

        $terminales = $terminales->sortKeys();
        if ($terminales->isEmpty()) {
            $incidencias->push([
                'tipo' => 'mapeo_sin_terminales', 'ruta' => $gasto['ruta'], 'terminal' => '', 'agencia' => '',
                'detalle' => 'Las relaciones manuales ya no tienen terminales activas en Centro de Costo.',
                'monto_pendiente' => $montoRutaCentavos / 100,
            ]);
            $resumenRutas->push($this->resumenRutaSinDistribuir($gasto, $empresa, $montoRutaCentavos, 'mapeo_sin_terminales'));

            return;
        }

        $montos = $this->distribuirCentavos($montoRutaCentavos, $terminales->count());
        $filasRuta = collect();

        foreach ($terminales->values() as $index => $asignacionesTerminal) {
            $socios = $asignacionesTerminal->unique('socio_key')->values();
            $asignacion = $socios->first();
            $terminalNormalizada = $this->normalizarTerminal($asignacion['terminal_original']);
            $agencia = $agenciasPorTerminal->get($terminalNormalizada);
            $estado = $socios->count() === 1 ? 'asignado' : 'conflicto_socio';
            $nombreAgencia = trim((string) ($agencia?->nombre_agencia ?: $agencia?->agencia ?: $asignacion['descripcion']));
            $fila = [
                'ruta_key' => (string) $gasto['ruta_key'],
                'ruta' => (string) $gasto['ruta'],
                'empresa' => $this->nombreEmpresa($empresa),
                'terminal' => $asignacion['terminal_original'],
                'agencia' => $nombreAgencia !== '' ? $nombreAgencia : 'Agencia sin nombre',
                'socio_key' => $estado === 'asignado' ? $asignacion['socio_key'] : 'pendiente:conflicto_socio',
                'socio' => $estado === 'asignado' ? $asignacion['socio'] : 'Sin socio asignado',
                'estado' => $estado,
                'cuenta_codigo' => (string) $gasto['cuenta_codigo'],
                'cuenta_descripcion' => (string) $gasto['cuenta_descripcion'],
                'gasto_ruta' => $montoRutaCentavos / 100,
                'total_agencias_ruta' => $terminales->count(),
                'participacion' => round(100 / $terminales->count(), 6),
                'gasto_agencia' => $montos[$index] / 100,
            ];
            $detalle->push($fila);
            $filasRuta->push($fila);

            if ($estado !== 'asignado') {
                $incidencias->push([
                    'tipo' => $estado, 'ruta' => $fila['ruta'], 'terminal' => $fila['terminal'], 'agencia' => $fila['agencia'],
                    'detalle' => 'La terminal fue incluida en mas de un socio de la configuracion manual.',
                    'monto_pendiente' => $fila['gasto_agencia'],
                ]);
            }
        }

        $montoAsignado = (float) $filasRuta->where('estado', 'asignado')->sum('gasto_agencia');
        $resumenRutas->push([
            'ruta_key' => (string) $gasto['ruta_key'], 'ruta' => (string) $gasto['ruta'], 'empresa' => $this->nombreEmpresa($empresa),
            'gastos' => (int) $gasto['cantidad_gastos'], 'agencias' => $terminales->count(),
            'socios' => $filasRuta->where('estado', 'asignado')->pluck('socio_key')->unique()->count(),
            'gasto_ruta' => $montoRutaCentavos / 100, 'asignado_socios' => round($montoAsignado, 2),
            'pendiente' => round(($montoRutaCentavos / 100) - $montoAsignado, 2),
            'estado' => $filasRuta->every(fn (array $fila): bool => $fila['estado'] === 'asignado') ? 'distribuida' : 'con_incidencias',
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function gastosPorRuta(string $fechaInicio, string $fechaFin, ?string $rutaKey = null): Collection
    {
        return MovimientoRutaV2Gasto::query()
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->where('estado', 'aplicado')
            ->whereNotNull('cuenta_codigo')
            ->whereNotNull('distribucion_tipo')
            ->when($rutaKey !== null, fn ($query) => $query->where('ruta_key', $rutaKey))
            ->get([
                'ruta_key', 'ruta', 'monto', 'cuenta_codigo', 'cuenta_descripcion', 'distribucion_tipo',
                'terminal_destino', 'agencia_destino', 'socio_codigo', 'socio_nombre',
            ])
            ->map(fn (MovimientoRutaV2Gasto $gasto): array => [
                ...$gasto->toArray(),
                'ruta_key' => $this->normalizarRuta($gasto->ruta_key ?: $gasto->ruta),
                'monto' => round((float) $gasto->monto, 2),
                'cantidad_gastos' => 1,
            ]);
    }

    /** @return Collection<int, MovimientoRutaV2Gasto> */
    private function gastosPendientesClasificacion(string $fechaInicio, string $fechaFin, ?string $rutaKey = null): Collection
    {
        return MovimientoRutaV2Gasto::query()
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->where('estado', 'aplicado')
            ->where(function ($query): void {
                $query->whereNull('cuenta_codigo')->orWhereNull('distribucion_tipo');
            })
            ->when($rutaKey !== null, fn ($query) => $query->where('ruta_key', $rutaKey))
            ->get(['ruta_key', 'ruta', 'monto']);
    }

    /** @return Collection<int, CentroDeCosto> */
    private function centrosActivos(): Collection
    {
        return CentroDeCosto::query()
            ->where('inactivo', false)
            ->where('ocultar', false)
            ->whereNotNull('id_viejo')
            ->whereNotNull('id_sub_grupo')
            ->get(['id_viejo', 'id_grupo', 'id_sub_grupo', 'company_id', 'descripcion'])
            ->filter(fn (CentroDeCosto $centro): bool => $this->normalizarTerminal($centro->id_viejo) !== '' && trim((string) $centro->id_sub_grupo) !== '')
            ->values();
    }

    /** @param Collection<int, Ruta> $rutas @return array{0: ?Ruta, 1: string} */
    private function buscarRuta(Collection $rutas, string $nombreGasto, ?string $empresaGasto): array
    {
        $nombreNormalizado = $this->normalizarRuta($nombreGasto);
        $base = $this->normalizarRutaBase($nombreGasto);
        $candidatas = $rutas->filter(function (Ruta $ruta) use ($nombreNormalizado, $base): bool {
            $nombreRuta = (string) ($ruta->nombre_ruta ?? '');

            return $this->normalizarRuta($nombreRuta) === $nombreNormalizado
                || $this->normalizarRutaBase($nombreRuta) === $base;
        });

        if ($empresaGasto !== null) {
            $mismaEmpresa = $candidatas->filter(fn (Ruta $ruta): bool => $this->empresaDesdeRuta($ruta) === $empresaGasto);
            if ($mismaEmpresa->isNotEmpty()) {
                $candidatas = $mismaEmpresa;
            }
        }

        if ($candidatas->count() === 1) {
            return [$candidatas->first(), 'asignada'];
        }

        return [null, $candidatas->isEmpty() ? 'ruta_no_encontrada' : 'ruta_ambigua'];
    }

    /** @param Collection<string, Collection<int, CentroDeCosto>> $centrosPorTerminal @return array{estado: string, key: string, nombre: string} */
    private function resolverSocio(Collection $centrosPorTerminal, string $terminal, ?string $empresa): array
    {
        if ($terminal === '' || ! $centrosPorTerminal->has($terminal)) {
            return ['estado' => 'sin_socio', 'key' => '', 'nombre' => ''];
        }

        $centros = $centrosPorTerminal->get($terminal, collect());
        $companyId = match ($empresa) {
            'GJ' => '168',
            'NG' => '169',
            default => null,
        };

        if ($companyId !== null) {
            $mismaEmpresa = $centros->filter(fn (CentroDeCosto $centro): bool => substr(trim((string) $centro->company_id), 0, 3) === $companyId);
            if ($mismaEmpresa->isNotEmpty()) {
                $centros = $mismaEmpresa;
            }
        }

        $socios = $centros
            ->map(fn (CentroDeCosto $centro): array => $this->parsearSocio($centro->id_sub_grupo))
            ->filter(fn (array $socio): bool => $socio['key'] !== '' && $socio['nombre'] !== '')
            ->unique('key')
            ->values();

        if ($socios->count() !== 1) {
            return ['estado' => $socios->isEmpty() ? 'sin_socio' : 'conflicto_socio', 'key' => '', 'nombre' => ''];
        }

        return ['estado' => 'asignado', 'key' => $socios->first()['key'], 'nombre' => $socios->first()['nombre']];
    }

    /** @return array{key: string, nombre: string} */
    private function parsearSocio(?string $valor): array
    {
        $socio = $this->parsearCodigoNombre($valor);

        return [
            'key' => $socio['codigo'] !== '' ? Str::upper($socio['codigo']) : $this->normalizarRuta($socio['nombre']),
            'nombre' => $socio['nombre'],
        ];
    }

    /** @return array{codigo: string, nombre: string} */
    private function parsearCodigoNombre(mixed $valor): array
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return ['codigo' => '', 'nombre' => ''];
        }

        $partes = preg_split('/\s*-\s*/u', $texto, 2);
        $codigo = trim((string) ($partes[0] ?? ''));
        $nombre = trim((string) ($partes[1] ?? $texto));

        return ['codigo' => $codigo, 'nombre' => $nombre !== '' ? $nombre : $texto];
    }

    private function codigoCampo(mixed $valor): string
    {
        return preg_replace('/\D/u', '', $this->parsearCodigoNombre($valor)['codigo']) ?? '';
    }

    /** @return array<int, int> */
    private function distribuirCentavos(int $montoCentavos, int $agencias): array
    {
        if ($agencias <= 0) {
            return [];
        }

        $base = intdiv($montoCentavos, $agencias);
        $residuo = $montoCentavos % $agencias;

        return array_map(
            fn (int $index): int => $base + ($index < $residuo ? 1 : 0),
            range(0, $agencias - 1),
        );
    }

    /** @param array<string, mixed> $gasto @return array<string, mixed> */
    private function resumenRutaSinDistribuir(array $gasto, ?string $empresa, int $montoCentavos, string $estado): array
    {
        return [
            'ruta_key' => (string) $gasto['ruta_key'],
            'ruta' => (string) $gasto['ruta'],
            'empresa' => $this->nombreEmpresa($empresa),
            'gastos' => (int) $gasto['cantidad_gastos'],
            'agencias' => 0,
            'socios' => 0,
            'gasto_ruta' => $montoCentavos / 100,
            'asignado_socios' => 0,
            'pendiente' => $montoCentavos / 100,
            'estado' => $estado,
        ];
    }

    private function empresaDesdeRuta(Ruta $ruta): ?string
    {
        $empresa = Str::upper(trim((string) ($ruta->empresa ?? '')));

        return match (true) {
            $empresa === 'GJ', Str::contains($empresa, 'JOSELITO') => 'GJ',
            $empresa === 'NG', Str::contains($empresa, 'NEGOSUR') => 'NG',
            default => $this->empresaDesdeNombre((string) ($ruta->nombre_ruta ?? '')),
        };
    }

    /** @param Collection<int, DistribucionGastoRutaMapeo> $mapeos */
    private function empresaDesdeMapeos(Collection $mapeos): ?string
    {
        $empresas = $mapeos->map(fn (DistribucionGastoRutaMapeo $mapeo): ?string => match ($mapeo->company_id) {
            '168' => 'GJ',
            '169' => 'NG',
            default => null,
        })->filter()->unique()->values();

        return $empresas->count() === 1 ? $empresas->first() : null;
    }

    private function empresaDesdeNombre(string $nombre): ?string
    {
        if (preg_match('/(?:^|[\s-])(GJ|NG)(?=$|[\s-])/iu', $nombre, $coincidencia) !== 1) {
            return null;
        }

        return Str::upper($coincidencia[1]);
    }

    private function nombreEmpresa(?string $empresa): string
    {
        return match ($empresa) {
            'GJ' => 'Grupo Joselito',
            'NG' => 'Negosur',
            default => 'Sin empresa',
        };
    }

    private function normalizarRutaBase(string $ruta): string
    {
        $ruta = preg_replace('/(?:^|[\s-])(GJ|NG)(?=$|[\s-])/iu', ' ', $ruta) ?? $ruta;

        return $this->normalizarRuta($ruta);
    }

    private function normalizarRuta(string $ruta): string
    {
        return Str::upper(trim(preg_replace('/\s+/u', ' ', $ruta) ?? $ruta));
    }

    private function normalizarTerminal(mixed $terminal): string
    {
        $terminal = ltrim(trim((string) $terminal), '0');

        return $terminal === '' ? '' : $terminal;
    }

    private function detalleIncidenciaSocio(string $estado): string
    {
        return match ($estado) {
            'sin_terminal' => 'La agencia no tiene una terminal registrada.',
            'conflicto_socio' => 'La terminal aparece asociada a mas de un socio en Centro de Costo.',
            default => 'La terminal no tiene un socio activo en Centro de Costo.',
        };
    }
}
