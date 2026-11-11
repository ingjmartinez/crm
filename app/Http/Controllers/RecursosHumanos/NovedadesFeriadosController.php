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
use Illuminate\Http\JsonResponse;
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

        if (Schema::hasTable('ponche_por_cedula_ventas')) {
            $ventas = DB::table('ponche_por_cedula_ventas')
                ->whereBetween('fecha_transaccion', [$filters['fecha_inicio'].' 00:00:00', $filters['fecha_fin'].' 23:59:59'])
                ->whereIn(DB::raw($normalizarCedulaSql('usuario_venta')), $cedulas)
                ->get(['usuario_venta', 'terminal', 'fecha_transaccion', 'tipo', 'estatus'])
                ->reject(fn (object $venta): bool => $venta->tipo === 'Recargas' && $venta->estatus === 'Pendiente')
                ->groupBy(fn (object $venta): string => $this->normalizarCedula($venta->usuario_venta).'|'.substr((string) $venta->fecha_transaccion, 0, 10).'|'.$this->normalizarTerminal($venta->terminal))
                ->map(fn ($grupo): mixed => $grupo->max('fecha_transaccion'));
        }

        $resultados = $rows->groupBy(fn (array $row): string => $row['cedula'].'|'.$row['fecha'].'|'.$row['sistema'])
            ->map(function ($ponches) use ($empleados, $ventas): array {
                $cedula = $ponches->first()['cedula'];
                $fecha = $ponches->first()['fecha'];
                $empleado = $empleados->get($cedula)?->first();
                $nombre = trim(($empleado?->nombres ?? '').' '.($empleado?->apellidos ?? '')) ?: 'No identificado';
                $primero = $ponches->pluck('primer_login')->filter()->min();
                $ultimo = $ponches->pluck('ultimo_login')->filter()->max();
                $ultimaVenta = $ponches->pluck('terminal')->filter()->unique()
                    ->map(fn (string $terminal): mixed => $ventas->get($cedula.'|'.$fecha.'|'.$terminal))
                    ->filter()->max();
                $salidaCalculada = $this->nominaDomingo->salidaEfectiva(
                    $ultimo ? Carbon::parse($ultimo) : null,
                    $ultimaVenta ? Carbon::parse($ultimaVenta) : null
                );
                $segundos = $primero && $salidaCalculada
                    ? max(0, CarbonImmutable::parse($primero)->diffInSeconds($salidaCalculada, false))
                    : 0;

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
                ];
            })
            ->sortBy([['fecha', 'asc'], ['sistema', 'asc'], ['cedula', 'asc']])->values();

        return response()->json($resultados);
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
