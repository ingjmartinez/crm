<?php

namespace App\Services\Bi;

use App\Models\Agencia;
use App\Models\Asistencia;
use App\Models\AsistenciaNet;
use App\Models\BiHorario;
use App\Models\Empleado;
use App\Services\AsistenciaAgenteVentaEndpointService;
use App\Services\RecursosHumanos\NominaDomingoService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ControlHorario
{
    public function __construct(
        private readonly NominaDomingoService $nominaDomingo,
        private readonly AsistenciaAgenteVentaEndpointService $asistenciasEnVivo,
    ) {}

    /** @param array<string, mixed> $filters
     * @return array{fecha: string, actualizado_en: string, terminales: array<int, array<string, mixed>>}
     */
    public function vigilar(array $filters): array
    {
        $fecha = today()->toDateString();
        $agencias = Agencia::query()
            ->where('estatus', 1)
            ->whereNotNull('terminal')->where('terminal', '<>', '')
            ->when($filters['empresa'] ?? null, fn ($query, $value) => $query->where('empresa', $value))
            ->when($filters['zona'] ?? null, fn ($query, $value) => $query->where('zona', $value))
            ->when($filters['ruta'] ?? null, fn ($query, $value) => $query->where('ruta', $value))
            ->when($filters['sistema'] === 'lotobet', fn ($query) => $query->lotobet())
            ->when($filters['sistema'] === 'lotonet', fn ($query) => $query->whereRaw('UPPER(TRIM(sistema)) IN (?, ?)', ['LOTONET', 'LOTENET']))
            ->get(['terminal', 'nombre_agencia', 'agencia', 'empresa', 'zona', 'ruta'])
            ->filter(fn (Agencia $agencia): bool => $this->clave($agencia->terminal) !== '')
            ->keyBy(fn (Agencia $agencia): string => $this->clave($agencia->terminal));

        $this->asistenciasEnVivo->prepararAcceso($filters['sistema']);
        $ponches = collect($this->asistenciasEnVivo->consultar($fecha, $filters['sistema']))
            ->filter(fn (array $row): bool => $agencias->has($this->clave($row['terminal'] ?? '')))
            ->map(function (array $row) use ($fecha): array {
                try {
                    $valor = (string) ($row['entrada'] ?? '');
                    $entrada = $valor === '' ? null : CarbonImmutable::parse(
                        str_contains($valor, '-') || str_contains($valor, '/') ? $valor : $fecha.' '.$valor
                    );
                } catch (Throwable) {
                    $entrada = null;
                }

                return [...$row, 'entrada_fecha' => $entrada?->toDateTimeString()];
            })
            ->filter(fn (array $row): bool => $row['entrada_fecha'] !== null
                && substr($row['entrada_fecha'], 0, 10) === $fecha
                && substr($row['entrada_fecha'], 11, 2) < '12')
            ->sortBy('entrada_fecha')
            ->unique(fn (array $row): string => $this->clave($row['terminal']))
            ->keyBy(fn (array $row): string => $this->clave($row['terminal']));

        $horarios = BiHorario::query()->get(['clave', 'entrada'])->keyBy('clave');
        $terminales = $agencias->map(function (Agencia $agencia, string $terminal) use ($ponches, $horarios, $fecha, $filters): array {
            $ponche = $ponches->get($terminal);
            $cedula = $this->documento($ponche['cedula'] ?? '');
            $horario = $horarios->get('empleado_terminal:'.$cedula.':'.$terminal)
                ?? $horarios->get('empleado:'.$cedula.':')
                ?? $horarios->get('terminal::'.$terminal);
            $horaEsperada = $horario?->entrada ?? $filters['entrada'];
            $minutos = $ponche
                ? (int) round(CarbonImmutable::parse($fecha.' '.$horaEsperada)->diffInMinutes(CarbonImmutable::parse($ponche['entrada_fecha']), false))
                : null;

            return [
                'fecha' => $fecha,
                'terminal' => $terminal,
                'agencia' => $agencia->nombre_agencia ?: $agencia->agencia ?: $terminal,
                'empresa' => $agencia->empresa,
                'zona' => $agencia->zona,
                'ruta' => $agencia->ruta,
                'cedula' => $cedula ?: null,
                'nombre' => $ponche['nombre'] ?? null,
                'primer_login' => $ponche['entrada_fecha'] ?? null,
                'horario_entrada' => $horaEsperada,
                'minutos_entrada' => $minutos,
                'estado' => $minutos === null ? 'sin_personal' : ($minutos > 0 ? 'tarde' : ($minutos < 0 ? 'temprano' : 'a_tiempo')),
            ];
        })->sortBy(fn (array $row): string => match ($row['estado']) {
            'sin_personal' => '0', 'tarde' => '1', 'a_tiempo' => '2', default => '3',
        }.'|'.str_pad($row['terminal'], 10, '0', STR_PAD_LEFT))->values()->all();

        return ['fecha' => $fecha, 'actualizado_en' => now()->toDateTimeString(), 'terminales' => $terminales];
    }

    /** @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function consultar(array $filters): array
    {
        $cedula = $filters['cedula'] ?? null;
        $agencias = Agencia::query()
            ->when($filters['empresa'] ?? null, fn ($query, $value) => $query->where('empresa', $value))
            ->when($filters['zona'] ?? null, fn ($query, $value) => $query->where('zona', $value))
            ->when($filters['ruta'] ?? null, fn ($query, $value) => $query->where('ruta', $value))
            ->get(['terminal', 'nombre_agencia', 'agencia'])
            ->filter(fn (Agencia $agencia): bool => $this->clave($agencia->terminal) !== '')
            ->keyBy(fn (Agencia $agencia): string => $this->clave($agencia->terminal));

        $rows = collect();
        if ($filters['sistema'] !== 'lotonet') {
            $rows = $rows->concat(Asistencia::query()
                ->whereBetween('fecha', [$filters['desde'], $filters['hasta']])
                ->when($cedula, fn ($query) => $query->whereRaw("REPLACE(REPLACE(cedula, '-', ''), ' ', '') = ?", [$cedula]))
                ->get(['fecha', 'cedula', 'agencia_id', 'primer_login', 'ultimo_login'])
                ->map(fn (Asistencia $row): array => [
                    'fecha' => (string) $row->fecha, 'cedula' => $this->documento($row->cedula),
                    'terminal' => $this->clave($row->agencia_id), 'sistema' => 'Lotobet',
                    'primer_login' => $row->primer_login, 'ultimo_login' => $row->ultimo_login,
                ]));
        }
        if ($filters['sistema'] !== 'lotobet') {
            $rows = $rows->concat(AsistenciaNet::query()
                ->whereBetween('entrada', [$filters['desde'].' 00:00:00', $filters['hasta'].' 23:59:59'])
                ->when($cedula, fn ($query) => $query->whereRaw("REPLACE(REPLACE(identificacion, '-', ''), ' ', '') = ?", [$cedula]))
                ->get(['identificacion', 'terminal', 'entrada', 'salida'])
                ->map(fn (AsistenciaNet $row): array => [
                    'fecha' => substr((string) $row->entrada, 0, 10), 'cedula' => $this->documento($row->identificacion),
                    'terminal' => $this->clave($row->terminal), 'sistema' => 'Lotonet',
                    'primer_login' => $row->entrada, 'ultimo_login' => $row->salida,
                ]));
        }

        $rows = $rows->filter(fn (array $row): bool => $row['cedula'] !== '' && $agencias->has($row['terminal']));
        $horarios = BiHorario::query()->get(['clave', 'tipo', 'entrada', 'salida'])->keyBy('clave');
        $cedulas = $rows->pluck('cedula')->unique();
        $empleados = $cedulas->isEmpty() ? collect() : Empleado::query()
            ->whereIn(DB::raw("REPLACE(REPLACE(cedula, '-', ''), ' ', '')"), $cedulas->all())
            ->orderByRaw('CASE WHEN fechasalida IS NULL THEN 0 ELSE 1 END')
            ->get(['cedula', 'nombres', 'apellidos'])
            ->unique(fn (Empleado $empleado): string => $this->documento($empleado->cedula))
            ->keyBy(fn (Empleado $empleado): string => $this->documento($empleado->cedula));

        $ventas = Schema::hasTable('ponche_por_cedula_ventas') && $rows->isNotEmpty()
            ? DB::table('ponche_por_cedula_ventas')
                ->whereBetween('fecha_transaccion', [$filters['desde'].' 00:00:00', $filters['hasta'].' 23:59:59'])
                ->whereIn(DB::raw("REPLACE(REPLACE(usuario_venta, '-', ''), ' ', '')"), $cedulas->all())
                ->get(['terminal', 'usuario_venta', 'fecha_transaccion', 'tipo', 'estatus'])
                ->reject(fn (object $venta): bool => $venta->tipo === 'Recargas' && $venta->estatus === 'Pendiente')
                ->groupBy(fn (object $venta): string => substr($venta->fecha_transaccion, 0, 10).'|'.$this->documento($venta->usuario_venta).'|'.$this->clave($venta->terminal))
            : collect();

        return $rows->groupBy(fn (array $row): string => $row['fecha'].'|'.$row['cedula'].'|'.$row['sistema'].'|'.$row['terminal'])
            ->map(function (Collection $ponches) use ($agencias, $empleados, $ventas, $horarios, $filters): array {
                $row = $ponches->first();
                $primero = $ponches->pluck('primer_login')->filter()->min();
                $ultimo = $ponches->pluck('ultimo_login')->filter()->max();
                $ultimaVenta = $ventas->get($row['fecha'].'|'.$row['cedula'].'|'.$row['terminal'], collect())->max('fecha_transaccion');
                $salida = $this->nominaDomingo->salidaEfectiva(
                    $ultimo ? Carbon::parse($ultimo) : null,
                    $ultimaVenta ? Carbon::parse($ultimaVenta) : null
                );
                $horario = $horarios->get('empleado_terminal:'.$row['cedula'].':'.$row['terminal'])
                    ?? $horarios->get('empleado:'.$row['cedula'].':')
                    ?? $horarios->get('terminal::'.$row['terminal']);
                $horaEntrada = $horario?->entrada ?? $filters['entrada'];
                $horaSalida = $horario?->salida ?? $filters['salida'];
                $entradaProgramada = CarbonImmutable::parse($row['fecha'].' '.$horaEntrada);
                $salidaProgramada = CarbonImmutable::parse($row['fecha'].' '.$horaSalida);
                $entradaReal = $primero ? CarbonImmutable::parse($primero) : null;
                $empleado = $empleados->get($row['cedula']);
                $agencia = $agencias->get($row['terminal']);

                return [
                    'cedula' => $row['cedula'],
                    'nombre' => trim(($empleado?->nombres ?? '').' '.($empleado?->apellidos ?? '')) ?: 'No identificado',
                    'fecha' => $row['fecha'], 'sistema' => $row['sistema'], 'terminal' => $row['terminal'],
                    'agencia' => $agencia->nombre_agencia ?: $agencia->agencia,
                    'horario_entrada' => $horaEntrada,
                    'horario_salida' => $horaSalida,
                    'horario_tipo' => $horario?->tipo ?? 'general',
                    'primer_login' => $primero, 'ultimo_login' => $ultimo,
                    'salida_calculada' => $salida?->toDateTimeString(),
                    'total_horas' => $entradaReal && $salida ? round(max(0, $entradaReal->diffInSeconds($salida, false)) / 3600, 2) : 0,
                    'minutos_entrada' => $entradaReal ? (int) round($entradaProgramada->diffInMinutes($entradaReal, false)) : null,
                    'minutos_salida' => $salida ? (int) round($salidaProgramada->diffInMinutes($salida, false)) : null,
                ];
            })->sortBy([['fecha', 'desc'], ['nombre', 'asc']])->values()->all();
    }

    private function clave(mixed $value): string
    {
        $digits = (string) preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? '' : (ltrim($digits, '0') ?: '0');
    }

    private function documento(mixed $value): string
    {
        return (string) preg_replace('/\D+/', '', (string) $value);
    }
}
