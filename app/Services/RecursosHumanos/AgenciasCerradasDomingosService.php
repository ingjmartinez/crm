<?php

namespace App\Services\RecursosHumanos;

use App\Models\Agencia;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AgenciasCerradasDomingosService
{
    /**
     * @return array{
     *     fecha: string,
     *     datos_disponibles: bool,
     *     mensaje_datos: ?string,
     *     agencias_activas: int,
     *     agencias_cerradas: int,
     *     movimientos_fuente: int,
     *     ponches_fuente: int,
     *     filas: Collection<int, array<string, mixed>>,
     *     empresas: Collection<int, array<string, mixed>>
     * }
     */
    public function generar(CarbonInterface $fecha): array
    {
        $fechaIso = $fecha->toDateString();
        $agencias = Agencia::query()
            ->select('id', 'agencia', 'terminal', 'nombre_agencia', 'empresa', 'ciudad', 'ruta', 'coordinador')
            ->where('estatus', 1)
            ->whereNotNull('terminal')
            ->lotobet()
            ->orderBy('terminal')
            ->get();

        $ventas = DB::table('vt_usuarios_bet')
            ->select('agencia_id')
            ->selectRaw('COUNT(*) AS movimientos')
            ->selectRaw('SUM(COALESCE(monto, 0)) AS monto')
            ->where('fecha', $fechaIso)
            ->groupBy('agencia_id')
            ->get();

        $asistencias = DB::table('asistencias_bet')
            ->select('agencia_id')
            ->selectRaw('COUNT(*) AS registros_fuente')
            ->selectRaw('SUM(CASE WHEN primer_login IS NOT NULL THEN 1 ELSE 0 END) AS ponches')
            ->where('fecha', $fechaIso)
            ->groupBy('agencia_id')
            ->get();

        $movimientosFuente = (int) $ventas->sum(fn (object $venta): int => (int) $venta->movimientos);
        $ponchesFuente = (int) $asistencias->sum(fn (object $asistencia): int => (int) $asistencia->registros_fuente);
        $datosDisponibles = $movimientosFuente > 0 && $ponchesFuente > 0;

        if (! $datosDisponibles) {
            return $this->respuestaSinDatos(
                $fechaIso,
                $agencias,
                $movimientosFuente,
                $ponchesFuente
            );
        }

        $ventasPorTerminal = $this->agruparVentasPorTerminal($ventas);
        $ponchesPorTerminal = $this->agruparPonchesPorTerminal($asistencias);

        $filas = $agencias
            ->filter(function (Agencia $agencia) use ($ventasPorTerminal, $ponchesPorTerminal): bool {
                $terminal = $this->normalizarTerminal($agencia->terminal);

                return (int) ($ventasPorTerminal[$terminal]['movimientos'] ?? 0) === 0
                    && (int) ($ponchesPorTerminal[$terminal] ?? 0) === 0;
            })
            ->map(fn (Agencia $agencia): array => [
                'fecha' => $fechaIso,
                'terminal' => (string) $agencia->terminal,
                'agencia' => $this->nombreAgencia($agencia),
                'empresa' => trim((string) $agencia->empresa) ?: 'Sin empresa',
                'ciudad' => trim((string) $agencia->ciudad) ?: 'Sin ciudad',
                'ruta' => trim((string) $agencia->ruta) ?: 'Sin ruta',
                'coordinador' => trim((string) $agencia->coordinador) ?: 'Sin coordinador',
                'movimientos_venta' => 0,
                'monto_ventas' => 0.0,
                'ponches' => 0,
            ])
            ->values();

        $empresas = $agencias
            ->groupBy(fn (Agencia $agencia): string => trim((string) $agencia->empresa) ?: 'Sin empresa')
            ->map(function (Collection $agenciasEmpresa, string $empresa) use ($filas): array {
                return [
                    'empresa' => $empresa,
                    'activas' => $agenciasEmpresa->count(),
                    'cerradas' => $filas->where('empresa', $empresa)->count(),
                ];
            })
            ->sortBy('empresa')
            ->values();

        return [
            'fecha' => $fechaIso,
            'datos_disponibles' => true,
            'mensaje_datos' => null,
            'agencias_activas' => $agencias->count(),
            'agencias_cerradas' => $filas->count(),
            'movimientos_fuente' => $movimientosFuente,
            'ponches_fuente' => $ponchesFuente,
            'filas' => $filas,
            'empresas' => $empresas,
        ];
    }

    /** @param Collection<int, object> $ventas */
    private function agruparVentasPorTerminal(Collection $ventas): array
    {
        return $ventas->reduce(function (array $resultado, object $venta): array {
            $terminal = $this->normalizarTerminal($venta->agencia_id);
            $resultado[$terminal] ??= ['movimientos' => 0, 'monto' => 0.0];
            $resultado[$terminal]['movimientos'] += (int) $venta->movimientos;
            $resultado[$terminal]['monto'] += (float) $venta->monto;

            return $resultado;
        }, []);
    }

    /** @param Collection<int, object> $asistencias */
    private function agruparPonchesPorTerminal(Collection $asistencias): array
    {
        return $asistencias->reduce(function (array $resultado, object $asistencia): array {
            $terminal = $this->normalizarTerminal($asistencia->agencia_id);
            $resultado[$terminal] = ($resultado[$terminal] ?? 0) + (int) $asistencia->ponches;

            return $resultado;
        }, []);
    }

    /**
     * @param  Collection<int, Agencia>  $agencias
     * @return array<string, mixed>
     */
    private function respuestaSinDatos(
        string $fecha,
        Collection $agencias,
        int $movimientosFuente,
        int $ponchesFuente
    ): array {
        $fuentesFaltantes = collect([
            $movimientosFuente === 0 ? 'ventas' : null,
            $ponchesFuente === 0 ? 'ponches' : null,
        ])->filter()->implode(' y ');

        return [
            'fecha' => $fecha,
            'datos_disponibles' => false,
            'mensaje_datos' => "No se puede determinar cuáles agencias cerraron porque faltan datos de {$fuentesFaltantes} para esta fecha.",
            'agencias_activas' => $agencias->count(),
            'agencias_cerradas' => 0,
            'movimientos_fuente' => $movimientosFuente,
            'ponches_fuente' => $ponchesFuente,
            'filas' => collect(),
            'empresas' => collect(),
        ];
    }

    private function normalizarTerminal(mixed $terminal): string
    {
        $normalizada = ltrim(trim((string) $terminal), '0');

        return $normalizada === '' ? '0' : $normalizada;
    }

    private function nombreAgencia(Agencia $agencia): string
    {
        return trim((string) ($agencia->nombre_agencia ?: $agencia->agencia)) ?: 'Sin identificar';
    }
}
