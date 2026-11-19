<?php

namespace App\Http\Controllers\RecursosHumanos;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecursosHumanos\ConsultarNovedadesFeriadosRequest;
use App\Models\Asistencia;
use App\Models\AsistenciaNet;
use App\Models\Empleado;
use App\Services\RecursosHumanos\NominaDomingoService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class NovedadesFeriadosController extends Controller
{
    public function __construct(private readonly NominaDomingoService $nominaDomingo) {}

    public function index(): View
    {
        return view('recursos_humanos.novedades_feriados');
    }

    public function list(ConsultarNovedadesFeriadosRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $empresa = $filters['empresa'] ?? null;
        $cedula = $filters['cedula'] ?? null;
        $idturno = $filters['idturno'] ?? null;
        $normalizarCedulaSql = fn (string $campo): string => "REPLACE(REPLACE($campo, '-', ''), ' ', '')";
        $filtrarPonches = function (Builder $query, string $campo) use ($empresa, $cedula, $idturno, $normalizarCedulaSql): void {
            if ($cedula) {
                $query->whereRaw($normalizarCedulaSql($campo).' = ?', [$cedula]);
            }

            if ($empresa || $idturno) {
                $query->whereIn(DB::raw($normalizarCedulaSql($campo)), Empleado::query()
                    ->when($empresa, fn (Builder $query): Builder => $query->where('companyid', $empresa))
                    ->when($idturno, fn (Builder $query): Builder => $query->where('idturno', $idturno))
                    ->selectRaw($normalizarCedulaSql('cedula')));
            }
        };
        $rows = collect();

        if ($filters['sistema'] !== 'lotonet') {
            $query = Asistencia::query()->whereBetween('fecha', [$filters['fecha_inicio'], $filters['fecha_fin']]);
            $filtrarPonches($query, 'cedula');
            $rows = $rows->concat($query->get(['cedula', 'fecha', 'agencia_id', 'primer_login', 'ultimo_login'])
                ->map(fn (Asistencia $ponche): array => [
                    'cedula' => $this->normalizarCedula($ponche->cedula),
                    'fecha' => (string) $ponche->fecha,
                    'sistema' => 'Lotobet',
                    'terminal' => $this->normalizarTerminal($ponche->agencia_id),
                    'primer_login' => $ponche->primer_login,
                    'ultimo_login' => $ponche->ultimo_login,
                ]));
        }

        if ($filters['sistema'] !== 'lotobet') {
            $query = AsistenciaNet::query()->whereBetween('entrada', [$filters['fecha_inicio'].' 00:00:00', $filters['fecha_fin'].' 23:59:59']);
            $filtrarPonches($query, 'identificacion');
            $rows = $rows->concat($query->get(['identificacion', 'terminal', 'entrada', 'salida'])
                ->map(fn (AsistenciaNet $ponche): array => [
                    'cedula' => $this->normalizarCedula($ponche->identificacion),
                    'fecha' => substr((string) $ponche->entrada, 0, 10),
                    'sistema' => 'Lotonet',
                    'terminal' => $this->normalizarTerminal($ponche->terminal),
                    'primer_login' => $ponche->entrada,
                    'ultimo_login' => $ponche->salida,
                ]));
        }

        $rows = $rows->filter(fn (array $row): bool => $row['cedula'] !== '');
        if ($rows->isEmpty()) {
            return response()->json([]);
        }

        $cedulas = $rows->pluck('cedula')->unique()->values();
        $empleados = Empleado::query()
            ->whereIn(DB::raw($normalizarCedulaSql('cedula')), $cedulas)
            ->when($empresa, fn (Builder $query): Builder => $query->where('companyid', $empresa))
            ->when($idturno, fn (Builder $query): Builder => $query->where('idturno', $idturno))
            ->orderByRaw('CASE WHEN fechasalida IS NULL THEN 0 ELSE 1 END')
            ->get(['cedula', 'empleadoid', 'nombres', 'apellidos', 'idturno'])
            ->groupBy(fn (Empleado $empleado): string => $this->normalizarCedula($empleado->cedula));
        $ventas = collect();
        $actividad = collect();

        if (Schema::hasTable('ponche_por_cedula_ventas')) {
            $actividad = DB::table('ponche_por_cedula_ventas')
                ->whereBetween('fecha_transaccion', [$filters['fecha_inicio'].' 00:00:00', $filters['fecha_fin'].' 23:59:59'])
                ->whereNot(fn (QueryBuilder $query): QueryBuilder => $query->where('tipo', 'Recargas')->where('estatus', 'Pendiente'))
                ->select(['usuario_venta', 'terminal'])
                ->selectRaw('DATE(fecha_transaccion) AS fecha, MIN(fecha_transaccion) AS primera, MAX(fecha_transaccion) AS ultima, COUNT(*) AS cantidad')
                ->groupBy('usuario_venta', 'terminal')->groupByRaw('DATE(fecha_transaccion)')
                ->get()
                ->map(fn (object $venta): array => [
                    'cedula' => $this->normalizarCedula($venta->usuario_venta),
                    'terminal' => $this->normalizarTerminal($venta->terminal),
                    'fecha' => $venta->fecha,
                    'primera' => $venta->primera,
                    'ultima' => $venta->ultima,
                    'cantidad' => (int) $venta->cantidad,
                ])
                ->filter(fn (array $venta): bool => $venta['cedula'] !== '' && $venta['terminal'] !== '')
                ->groupBy(fn (array $venta): string => $venta['cedula'].'|'.$venta['fecha'].'|'.$venta['terminal'])
                ->map(fn (Collection $grupo): array => [
                    ...$grupo->first(),
                    'primera' => $grupo->min('primera'),
                    'ultima' => $grupo->max('ultima'),
                    'cantidad' => $grupo->sum('cantidad'),
                ]);
            $ventas = $actividad->map(fn (array $venta): string => $venta['ultima']);
        }

        $actividadPorTerminal = $actividad->values()->groupBy(fn (array $venta): string => $venta['fecha'].'|'.$venta['terminal']);

        $resultados = $rows->groupBy(fn (array $row): string => $row['cedula'].'|'.$row['fecha'].'|'.$row['sistema'])
            ->map(function ($ponches) use ($empleados, $ventas, $actividadPorTerminal): array {
                $cedula = $ponches->first()['cedula'];
                $fecha = $ponches->first()['fecha'];
                $empleado = $empleados->get($cedula)?->first();
                $nombre = trim(($empleado?->nombres ?? '').' '.($empleado?->apellidos ?? '')) ?: 'No identificado';
                $primero = $ponches->pluck('primer_login')->filter()->min();
                $ultimo = $ponches->pluck('ultimo_login')->filter()->max();
                $revision = $this->revisionTransaccional($ponches, $actividadPorTerminal);
                $entradaCalculada = match (true) {
                    $revision['estado'] !== 'conflicto' && $primero !== null => CarbonImmutable::parse($primero),
                    $revision['primera_transaccion'] !== null => CarbonImmutable::parse($revision['primera_transaccion'])->subMinutes(2),
                    default => null,
                };
                $ultimaVenta = $ponches->pluck('terminal')->filter()->unique()
                    ->map(fn (string $terminal): mixed => $ventas->get($cedula.'|'.$fecha.'|'.$terminal))
                    ->filter()->max();
                $salidaCalculada = $this->nominaDomingo->salidaEfectiva(
                    $ultimo ? Carbon::parse($ultimo) : null,
                    $ultimaVenta ? Carbon::parse($ultimaVenta) : null
                );
                if ($salidaCalculada === null && $ultimaVenta) {
                    $salidaCalculada = Carbon::parse($ultimaVenta)->addMinutes(5);
                }
                $segundos = $primero && $salidaCalculada
                    ? max(0, CarbonImmutable::parse($primero)->diffInSeconds($salidaCalculada, false))
                    : 0;
                $segundosCalculados = $entradaCalculada && $salidaCalculada
                    ? max(0, $entradaCalculada->diffInSeconds($salidaCalculada, false))
                    : null;

                return [
                    'cedula' => $cedula,
                    'empleadoid' => $empleado?->empleadoid,
                    'nombre' => $nombre,
                    'idturno' => $empleado?->idturno,
                    'fecha' => $fecha,
                    'sistema' => $ponches->first()['sistema'],
                    'primer_login' => $primero,
                    'ultimo_login' => $ultimo,
                    'salida_calculada' => $salidaCalculada?->toDateTimeString(),
                    'total_horas' => round($segundos / 3600, 2),
                    'hora_calculada' => $segundosCalculados !== null ? round($segundosCalculados / 3600, 2) : null,
                    'entrada_calculada' => $entradaCalculada?->toDateTimeString(),
                    'primera_venta' => $revision['primera_transaccion'],
                    'revision' => $revision,
                ];
            })
            ->sortBy([['fecha', 'asc'], ['sistema', 'asc'], ['cedula', 'asc']])->values();

        return response()->json($resultados);
    }

    /** @return array{estado: string, motivo: string, primera_transaccion: ?string, ultima_transaccion: ?string, movimientos: int, coincidencias: array<int, array<string, mixed>>} */
    private function revisionTransaccional(Collection $ponches, Collection $actividadPorTerminal): array
    {
        $cedula = $ponches->first()['cedula'];
        $actividadPropia = collect();
        $coincidencias = collect();

        foreach ($ponches as $ponche) {
            if ($ponche['terminal'] === '') {
                continue;
            }

            $actividadTerminal = $actividadPorTerminal->get($ponche['fecha'].'|'.$ponche['terminal'], collect());
            foreach ($actividadTerminal as $actividad) {
                $clave = $actividad['cedula'].'|'.$actividad['terminal'];
                if ($actividad['cedula'] === $cedula) {
                    $actividadPropia->put($clave, $actividad);

                    continue;
                }

                if ($ponche['primer_login'] && $ponche['ultimo_login']
                    && $actividad['primera'] <= $ponche['ultimo_login']
                    && $actividad['ultima'] >= $ponche['primer_login']) {
                    $coincidencias->put($clave, $actividad);
                }
            }
        }

        $estado = match (true) {
            $coincidencias->isNotEmpty() => 'conflicto',
            $actividadPropia->isEmpty() => 'sin_transacciones',
            default => 'sin_cruce_detectado',
        };

        return [
            'estado' => $estado,
            'motivo' => match ($estado) {
                'conflicto' => 'El horario registrado se cruza con el intervalo de actividad transaccional de otra cédula en el mismo terminal. Puede existir un relevo que el resumen del ponche no refleja. Requiere revisión del responsable.',
                'sin_transacciones' => 'No se encontraron transacciones vinculadas a esta cédula y terminal para el día consultado. La ausencia de ventas no demuestra ausencia laboral.',
                default => 'No se detectó un cruce con la actividad transaccional de otra cédula en los terminales del ponche. Esto no confirma por sí solo la jornada.',
            },
            'primera_transaccion' => $actividadPropia->min('primera'),
            'ultima_transaccion' => $actividadPropia->max('ultima'),
            'movimientos' => $actividadPropia->sum('cantidad'),
            'coincidencias' => $coincidencias->sortBy('primera')->values()->all(),
        ];
    }

    private function normalizarCedula(mixed $valor): string
    {
        return preg_replace('/\D+/', '', (string) $valor);
    }

    private function normalizarTerminal(mixed $valor): string
    {
        $digitos = $this->normalizarCedula($valor);

        return $digitos === '' ? '' : (ltrim($digitos, '0') ?: '0');
    }
}
