<?php

namespace App\Http\Controllers\RecursosHumanos;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecursosHumanos\ConsultarPonchePorCedulaRequest;
use App\Models\Asistencia;
use App\Models\AsistenciaNet;
use App\Models\Empleado;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PonchePorCedulaController extends Controller
{
    public function index(): View
    {
        return view('recursos_humanos.ponche_por_cedula');
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
                ->get(['fecha', 'primer_login', 'ultimo_login'])
                ->map(fn (Asistencia $ponche): array => [
                    'fecha' => (string) $ponche->fecha,
                    'sistema' => 'Lotobet',
                    'primer_login' => $ponche->primer_login,
                    'ultimo_login' => $ponche->ultimo_login,
                ]));
        }

        if ($filters['sistema'] !== 'lotobet') {
            $rows = $rows->concat(AsistenciaNet::query()
                ->whereBetween('entrada', [$filters['fecha_inicio'].' 00:00:00', $filters['fecha_fin'].' 23:59:59'])
                ->whereRaw("REPLACE(REPLACE(identificacion, '-', ''), ' ', '') = ?", [$cedula])
                ->get(['entrada', 'salida'])
                ->map(fn (AsistenciaNet $ponche): array => [
                    'fecha' => substr((string) $ponche->entrada, 0, 10),
                    'sistema' => 'Lotonet',
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

        $resultados = $rows->groupBy(fn (array $row): string => $row['fecha'].'|'.$row['sistema'])
            ->map(function ($ponches) use ($cedula, $nombre): array {
                $primero = $ponches->pluck('primer_login')->filter()->min();
                $ultimo = $ponches->pluck('ultimo_login')->filter()->max();
                $segundos = $primero && $ultimo
                    ? max(0, CarbonImmutable::parse($primero)->diffInSeconds(CarbonImmutable::parse($ultimo), false))
                    : 0;

                return [
                    'cedula' => $cedula,
                    'nombre' => $nombre,
                    'fecha' => $ponches->first()['fecha'],
                    'sistema' => $ponches->first()['sistema'],
                    'primer_login' => $primero,
                    'ultimo_login' => $ultimo,
                    'total_horas' => round($segundos / 3600, 2),
                ];
            })
            ->sortBy([['fecha', 'asc'], ['sistema', 'asc']])
            ->values();

        return response()->json($resultados);
    }
}
