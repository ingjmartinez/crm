<?php

namespace App\Services\RecursosHumanos;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NominaDomingoService
{
    /** @return array{horas_requeridas: float, monto_fijo: float} */
    public function configuracion(): array
    {
        $configuracion = Schema::hasTable('nomina_domingo_configuraciones') ? DB::table('nomina_domingo_configuraciones')->first() : null;

        return ['horas_requeridas' => (float) ($configuracion->horas_requeridas ?? 8), 'monto_fijo' => (float) ($configuracion->monto_fijo ?? 0)];
    }

    /** @param array{horas_requeridas: float|int|string, monto_fijo: float|int|string} $datos */
    public function guardarConfiguracion(array $datos): void
    {
        DB::table('nomina_domingo_configuraciones')->updateOrInsert(['id' => 1], [
            'horas_requeridas' => $datos['horas_requeridas'], 'monto_fijo' => $datos['monto_fijo'],
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return Collection<int, array<string, float|string|null>> */
    public function generar(Carbon $fecha): Collection
    {
        $configuracion = $this->configuracion();
        $ventas = $this->ventas($fecha);
        $ponches = $this->ponches($fecha);
        $claves = $ventas->keys()->merge($ponches->keys())->unique();
        $cedulas = $claves->map(fn (string $clave): string => explode('|', $clave, 2)[1])->all();
        $empleados = $this->empleados($cedulas);

        return $claves->map(function (string $clave) use ($ventas, $ponches, $empleados, $configuracion): array {
            [$terminal, $cedula] = explode('|', $clave, 2);
            $venta = $ventas->get($clave);
            $ponche = $ponches->get($clave);
            $entrada = $this->instante($ponche['entrada'] ?? null);
            $salidaPonche = $this->instante($ponche['salida'] ?? null);
            $ultimaTransaccion = $this->instante($venta['ultima_transaccion'] ?? null);
            $salidaSegunTransaccion = $salidaPonche === null && $ultimaTransaccion !== null
                ? $ultimaTransaccion->copy()->addMinutes(5)
                : $ultimaTransaccion;
            $salidaEfectiva = collect([$salidaPonche, $salidaSegunTransaccion])
                ->filter()
                ->sortByDesc(fn (Carbon $instante): int => $instante->getTimestamp())
                ->first();
            $horas = $entrada && $salidaEfectiva
                ? max(0, $entrada->diffInSeconds($salidaEfectiva, false) / 3600)
                : 0;
            $cumple = $entrada !== null && $salidaEfectiva !== null && $horas >= $configuracion['horas_requeridas'];

            return [
                'terminal' => $terminal, 'cedula' => $this->cedulaParaMostrar($cedula),
                'empleado' => $empleados->get($cedula) ?: ($ponche['empleado'] ?? 'No identificado'),
                'entrada' => $entrada?->toDateTimeString(), 'salida_ponche' => $salidaPonche?->toDateTimeString(), 'ultima_transaccion' => $ultimaTransaccion?->toDateTimeString(),
                'salida_efectiva' => $salidaEfectiva?->toDateTimeString(),
                'fuente_salida' => $this->fuenteSalida($salidaPonche, $ultimaTransaccion),
                'horas_trabajadas' => round($horas, 2), 'estatus' => $cumple ? 'Cumple' : 'No cumple',
                'monto_pagar' => $cumple ? $configuracion['monto_fijo'] : 0.0,
            ];
        })->sortBy(['empleado', 'terminal'])->values();
    }

    /** @return Collection<string, array{ultima_transaccion: string}> */
    private function ventas(Carbon $fecha): Collection
    {
        if (! Schema::hasTable('gestion_agencias_ventas')) {
            return collect();
        }

        return DB::table('gestion_agencias_ventas')->whereDate('fecha_transaccion', $fecha->toDateString())
            ->whereNotNull('usuario_venta')->where('usuario_venta', '<>', '')->get(['terminal', 'usuario_venta', 'fecha_transaccion'])
            ->groupBy(fn (object $fila): string => $this->clave($fila->terminal, $fila->usuario_venta))
            ->map(fn (Collection $filas): array => ['ultima_transaccion' => (string) $filas->max('fecha_transaccion')]);
    }

    /** @return Collection<string, array{entrada: ?string, salida: ?string, empleado: string}> */
    private function ponches(Carbon $fecha): Collection
    {
        $filas = collect();
        if (Schema::hasTable('asistencias_bet')) {
            $filas = $filas->merge(DB::table('asistencias_bet')->whereDate('fecha', $fecha->toDateString())
                ->get(['agencia_id as terminal', 'cedula', 'usuario as empleado', 'primer_login as entrada', 'ultimo_login as salida']));
        }
        if (Schema::hasTable('asistencias_net')) {
            $filas = $filas->merge(DB::table('asistencias_net')->whereDate('entrada', $fecha->toDateString())
                ->get(['terminal', 'identificacion as cedula', 'username as empleado', 'entrada', 'salida']));
        }

        return $filas->filter(fn (object $fila): bool => $this->normalizar($fila->cedula) !== '')
            ->groupBy(fn (object $fila): string => $this->clave($fila->terminal, $fila->cedula))
            ->map(fn (Collection $grupo): array => ['entrada' => $grupo->pluck('entrada')->filter()->min(), 'salida' => $grupo->pluck('salida')->filter()->max(), 'empleado' => (string) ($grupo->pluck('empleado')->filter()->first() ?? 'No identificado')]);
    }

    /** @param array<int, string> $cedulas */
    private function empleados(array $cedulas): Collection
    {
        if (! Schema::hasTable('empleados') || $cedulas === []) {
            return collect();
        }

        return DB::table('empleados')->whereNull('fechasalida')->get(['cedula', 'nombres', 'apellidos'])
            ->filter(fn (object $empleado): bool => in_array($this->normalizar($empleado->cedula), $cedulas, true))
            ->mapWithKeys(fn (object $empleado): array => [$this->normalizar($empleado->cedula) => trim("{$empleado->nombres} {$empleado->apellidos}")]);
    }

    private function clave(mixed $terminal, mixed $cedula): string
    {
        return $this->normalizar($terminal).'|'.$this->normalizar($cedula);
    }

    private function normalizar(mixed $valor): string
    {
        $digitos = (string) preg_replace('/\D+/', '', trim((string) $valor));
        if ($digitos === '') {
            return '';
        }

        $normalizado = ltrim($digitos, '0');

        return $normalizado === '' ? '0' : $normalizado;
    }

    private function cedulaParaMostrar(string $cedula): string
    {
        return strlen($cedula) <= 11
            ? str_pad($cedula, 11, '0', STR_PAD_LEFT)
            : $cedula;
    }

    private function instante(mixed $valor): ?Carbon
    {
        if ($valor === null || trim((string) $valor) === '') {
            return null;
        }

        return Carbon::parse($valor);
    }

    private function fuenteSalida(?Carbon $salidaPonche, ?Carbon $ultimaTransaccion): string
    {
        if ($salidaPonche === null && $ultimaTransaccion === null) {
            return 'Sin salida';
        }

        if ($salidaPonche === null) {
            return 'Última transacción + 5 minutos';
        }

        if ($ultimaTransaccion !== null && ($salidaPonche === null || $ultimaTransaccion->greaterThan($salidaPonche))) {
            return 'Última transacción';
        }

        return 'Ponche';
    }
}
