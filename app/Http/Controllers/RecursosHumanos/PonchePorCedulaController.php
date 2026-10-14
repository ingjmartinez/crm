<?php

namespace App\Http\Controllers\RecursosHumanos;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecursosHumanos\CargarPonchePorCedulaVentasRequest;
use App\Http\Requests\RecursosHumanos\ConsultarPonchePorCedulaRequest;
use App\Models\Asistencia;
use App\Models\AsistenciaNet;
use App\Models\Empleado;
use App\Services\RecursosHumanos\NominaDomingoService;
use App\Services\RecursosHumanos\NominaDomingoVentasImportService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PonchePorCedulaController extends Controller
{
    public function __construct(
        private readonly NominaDomingoService $nominaDomingo,
        private readonly NominaDomingoVentasImportService $ventasImport,
    ) {}

    public function index(): View
    {
        return view('recursos_humanos.ponche_por_cedula');
    }

    public function cargarVentas(CargarPonchePorCedulaVentasRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->ventasImport->importarPonchePorCedula(
            $request->file('tradicional'),
            $request->file('no_tradicional'),
            $request->file('recargas'),
            Carbon::parse($data['fecha'])
        );

        return redirect()->route('recursos-humanos.ponche-por-cedula.index')
            ->with('success', 'Ventas de '.$data['fecha'].' cargadas correctamente para Ponche por Cédula.');
    }

    public function list(ConsultarPonchePorCedulaRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $cedula = $filters['cedula'];
        $rows = collect();

        if ($filters['sistema'] !== 'lotonet') {
            $rows = $rows->concat(Asistencia::query()
                ->whereBetween('fecha', [$filters['fecha_inicio'], $filters['fecha_fin']])
                ->whereRaw("REPLACE(REPLACE(cedula, '-', ''), ' ', '') = ?", [$cedula])
                ->get(['fecha', 'agencia_id', 'primer_login', 'ultimo_login'])
                ->map(fn (Asistencia $ponche): array => [
                    'fecha' => (string) $ponche->fecha,
                    'sistema' => 'Lotobet',
                    'terminal' => $this->normalizarClave($ponche->agencia_id),
                    'primer_login' => $ponche->primer_login,
                    'ultimo_login' => $ponche->ultimo_login,
                ]));
        }

        if ($filters['sistema'] !== 'lotobet') {
            $rows = $rows->concat(AsistenciaNet::query()
                ->whereBetween('entrada', [$filters['fecha_inicio'].' 00:00:00', $filters['fecha_fin'].' 23:59:59'])
                ->whereRaw("REPLACE(REPLACE(identificacion, '-', ''), ' ', '') = ?", [$cedula])
                ->get(['terminal', 'entrada', 'salida'])
                ->map(fn (AsistenciaNet $ponche): array => [
                    'fecha' => substr((string) $ponche->entrada, 0, 10),
                    'sistema' => 'Lotonet',
                    'terminal' => $this->normalizarClave($ponche->terminal),
                    'primer_login' => $ponche->entrada,
                    'ultimo_login' => $ponche->salida,
                ]));
        }

        $empleado = $rows->isNotEmpty()
            ? Empleado::query()
                ->whereRaw("REPLACE(REPLACE(cedula, '-', ''), ' ', '') = ?", [$cedula])
                ->orderByRaw('CASE WHEN fechasalida IS NULL THEN 0 ELSE 1 END')
                ->first(['nombres', 'apellidos'])
            : null;
        $nombre = trim(($empleado?->nombres ?? '').' '.($empleado?->apellidos ?? '')) ?: 'No identificado';
        $fechas = $rows->pluck('fecha')->unique()->values();
        $ventas = collect();
        if ($fechas->isNotEmpty() && Schema::hasTable('ponche_por_cedula_ventas')) {
            foreach ($fechas as $fecha) {
                $ventas = $ventas->concat(DB::table('ponche_por_cedula_ventas')
                    ->whereDate('fecha_transaccion', $fecha)
                    ->whereRaw("REPLACE(REPLACE(usuario_venta, '-', ''), ' ', '') = ?", [$cedula])
                    ->get(['terminal', 'fecha_transaccion', 'tipo', 'estatus'])
                    ->reject(fn (object $venta): bool => $venta->tipo === 'Recargas' && $venta->estatus === 'Pendiente')
                    ->map(fn (object $venta): array => [
                        'fecha' => $fecha,
                        'terminal' => $this->normalizarClave($venta->terminal),
                        'fecha_transaccion' => $venta->fecha_transaccion,
                    ]));
            }
        }

        $resultados = $rows->groupBy(fn (array $row): string => $row['fecha'].'|'.$row['sistema'])
            ->map(function ($ponches) use ($cedula, $nombre, $ventas): array {
                $primero = $ponches->pluck('primer_login')->filter()->min();
                $ultimo = $ponches->pluck('ultimo_login')->filter()->max();
                $fecha = $ponches->first()['fecha'];
                $terminales = $ponches->pluck('terminal')->filter()->unique();
                $ultimaVenta = $ventas->where('fecha', $fecha)->whereIn('terminal', $terminales)->max('fecha_transaccion');
                $salidaCalculada = $this->nominaDomingo->salidaEfectiva(
                    $ultimo ? Carbon::parse($ultimo) : null,
                    $ultimaVenta ? Carbon::parse($ultimaVenta) : null
                );
                $segundos = $primero && $salidaCalculada
                    ? max(0, CarbonImmutable::parse($primero)->diffInSeconds($salidaCalculada, false))
                    : 0;

                return [
                    'cedula' => $cedula,
                    'nombre' => $nombre,
                    'fecha' => $fecha,
                    'sistema' => $ponches->first()['sistema'],
                    'primer_login' => $primero,
                    'ultimo_login' => $ultimo,
                    'salida_calculada' => $salidaCalculada?->toDateTimeString(),
                    'total_horas' => round($segundos / 3600, 2),
                ];
            })
            ->sortBy([['fecha', 'asc'], ['sistema', 'asc']])
            ->values();

        return response()->json($resultados);
    }

    private function normalizarClave(mixed $valor): string
    {
        $digitos = (string) preg_replace('/\D+/', '', trim((string) $valor));

        return $digitos === '' ? '' : (ltrim($digitos, '0') ?: '0');
    }
}
