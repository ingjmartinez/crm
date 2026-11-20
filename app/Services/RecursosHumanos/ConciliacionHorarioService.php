<?php

namespace App\Services\RecursosHumanos;

use App\Models\Asistencia;
use App\Models\AsistenciaNet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConciliacionHorarioService
{
    public function documento(mixed $valor): string
    {
        return preg_replace('/\D+/', '', (string) $valor);
    }

    public function terminal(mixed $valor): string
    {
        $digitos = $this->documento($valor);

        return $digitos === '' ? '' : (ltrim($digitos, '0') ?: '0');
    }

    /** @return Collection<string, Collection<int, array<string, mixed>>> */
    public function actividad(string $desde, string $hasta): Collection
    {
        $filas = collect();
        $fechasImportadas = collect($this->fechasPreferidas($desde, $hasta));
        foreach (['ponche_por_cedula_ventas', 'nomina_domingo_ventas'] as $tabla) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            $ventas = DB::table($tabla)->whereBetween('fecha_transaccion', [$desde.' 00:00:00', $hasta.' 23:59:59'])
                ->where(fn (Builder $query): Builder => $query->whereNull('tipo')->orWhereNull('estatus')->orWhere('tipo', '<>', 'Recargas')->orWhere('estatus', '<>', 'Pendiente'))
                ->select(['usuario_venta', 'terminal'])
                ->selectRaw('DATE(fecha_transaccion) AS fecha, MIN(fecha_transaccion) AS primera, MAX(fecha_transaccion) AS ultima, COUNT(*) AS cantidad')
                ->groupBy('usuario_venta', 'terminal')->groupByRaw('DATE(fecha_transaccion)')->get();
            $filas = $filas->concat($tabla === 'ponche_por_cedula_ventas' ? $ventas : $ventas->reject(fn (object $venta): bool => $fechasImportadas->contains($venta->fecha)));
        }

        return $filas->map(fn (object $venta): array => [
            'cedula' => $this->documento($venta->usuario_venta), 'terminal' => $this->terminal($venta->terminal),
            'fecha' => $venta->fecha, 'primera' => $venta->primera, 'ultima' => $venta->ultima, 'cantidad' => (int) $venta->cantidad,
        ])->filter(fn (array $venta): bool => $venta['cedula'] !== '' && $venta['terminal'] !== '')
            ->groupBy(fn (array $venta): string => $venta['fecha'].'|'.$venta['terminal'].'|'.$venta['cedula'])
            ->map(fn (Collection $grupo): array => [
                ...$grupo->first(), 'primera' => $grupo->min('primera'), 'ultima' => $grupo->max('ultima'), 'cantidad' => $grupo->sum('cantidad'),
            ])->values()->groupBy(fn (array $venta): string => $venta['fecha'].'|'.$venta['terminal']);
    }

    /** @return array<string, mixed> */
    public function resolver(?string $entrada, ?string $salida, ?string $primeraVenta, ?string $ultimaVenta, bool $cruce): array
    {
        $entradaSistema = $entrada ? CarbonImmutable::parse($entrada) : null;
        $salidaSistema = $salida ? CarbonImmutable::parse($salida) : null;
        $primera = $primeraVenta ? CarbonImmutable::parse($primeraVenta) : null;
        $ultima = $ultimaVenta ? CarbonImmutable::parse($ultimaVenta) : null;
        $entradaCalculada = ! $cruce && $entradaSistema !== null ? $entradaSistema : $primera?->subMinutes(2);
        $salidaCalculada = $ultima !== null && ($salidaSistema === null || $ultima->greaterThanOrEqualTo($salidaSistema))
            ? $ultima->addMinutes(5) : $salidaSistema;
        $segundos = $entradaCalculada && $salidaCalculada ? max(0, $entradaCalculada->diffInSeconds($salidaCalculada, false)) : null;
        $segundosSistema = $entradaSistema && $salidaCalculada ? max(0, $entradaSistema->diffInSeconds($salidaCalculada, false)) : 0;

        return [
            'entrada_calculada' => $entradaCalculada?->toDateTimeString(), 'salida_calculada' => $salidaCalculada?->toDateTimeString(),
            'primera_venta' => $primeraVenta, 'ultima_venta' => $ultimaVenta, 'segundos_calculados' => $segundos,
            'hora_calculada' => $segundos !== null ? round($segundos / 3600, 2) : null, 'horas_sistema' => round($segundosSistema / 3600, 2),
            'tiene_cruce' => $cruce, 'fuente_entrada' => ! $cruce && $entradaSistema !== null ? 'Primer ponche' : ($primera !== null ? 'Primera venta - 2 minutos' : 'Sin entrada'),
        ];
    }

    /** @return array<string, mixed> */
    public function conciliar(Collection $ponches, Collection $actividad): array
    {
        $propia = collect();
        $coincidencias = collect();
        foreach ($ponches as $ponche) {
            $terminal = $this->terminal($ponche['terminal'] ?? '');
            $cedula = $this->documento($ponche['cedula']);
            $ventas = $terminal === '' ? collect() : $actividad->get($ponche['fecha'].'|'.$terminal, collect());
            $identidades = collect([$cedula, ...($ponche['usuarios'] ?? [])])->map(fn ($valor): string => ltrim($this->documento($valor), '0'));
            $esPropia = fn (array $venta): bool => $identidades->contains(ltrim($venta['cedula'], '0'));
            $ventaPropia = $ventas->first($esPropia);
            $inicio = $ponche['primer_login'] ?? $ventaPropia['primera'] ?? null;
            $fin = $ponche['ultimo_login'] ?? $ventaPropia['ultima'] ?? null;
            foreach ($ventas as $venta) {
                $clave = $venta['terminal'].'|'.$venta['cedula'];
                if ($esPropia($venta)) {
                    $propia->put($clave, $venta);
                } elseif ($inicio && $fin && $venta['primera'] <= $fin && $venta['ultima'] >= $inicio) {
                    $coincidencias->put($clave, $venta);
                }
            }
        }
        $estado = $coincidencias->isNotEmpty() ? 'conflicto' : ($propia->isEmpty() ? 'sin_transacciones' : 'sin_cruce_detectado');
        $revision = [
            'estado' => $estado,
            'motivo' => match ($estado) {
                'conflicto' => 'El ponche se cruza con actividad de otra cédula en el mismo terminal. Se usa la primera venta menos dos minutos como entrada calculada. Requiere revisión del responsable.',
                'sin_transacciones' => 'Sin transacciones vinculadas al terminal y día. Se conserva el ponche disponible; la ausencia de ventas no demuestra ausencia laboral.',
                default => 'Sin cruce detectado: se usa el primer ponche. Si falta la entrada, se usa la primera venta menos dos minutos.',
            },
            'primera_transaccion' => $propia->min('primera'), 'ultima_transaccion' => $propia->max('ultima'),
            'movimientos' => $propia->sum('cantidad'), 'coincidencias' => $coincidencias->sortBy('primera')->values()->all(),
        ];

        return [...$this->resolver($ponches->pluck('primer_login')->filter()->min(), $ponches->pluck('ultimo_login')->filter()->max(), $propia->min('primera'), $propia->max('ultima'), $estado === 'conflicto'), 'revision' => $revision];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function consultar(string $desde, string $hasta, string $sistema = 'todos'): Collection
    {
        $ponches = collect();
        if (strtolower($sistema) !== 'lotonet' && Schema::hasTable('asistencias_bet')) {
            $ponches = $ponches->concat(Asistencia::query()->whereBetween('fecha', [$desde, $hasta])->get()
                ->map(fn (Asistencia $row): array => [
                    'fecha' => (string) $row->fecha, 'cedula' => $this->documento($row->cedula), 'terminal' => $this->terminal($row->agencia_id),
                    'sistema' => 'Lotobet', 'primer_login' => $row->primer_login, 'ultimo_login' => $row->ultimo_login,
                ]));
        }
        if (strtolower($sistema) !== 'lotobet' && Schema::hasTable('asistencias_net')) {
            $ponches = $ponches->concat(AsistenciaNet::query()->whereBetween('entrada', [$desde.' 00:00:00', $hasta.' 23:59:59'])->get()
                ->map(fn (AsistenciaNet $row): array => [
                    'fecha' => substr((string) $row->entrada, 0, 10), 'cedula' => $this->documento($row->identificacion), 'terminal' => $this->terminal($row->terminal),
                    'sistema' => 'Lotonet', 'primer_login' => $row->entrada, 'ultimo_login' => $row->salida ?? $row->salida_inactividad,
                ]));
        }
        $actividad = $this->actividad($desde, $hasta);

        return $ponches->groupBy(fn (array $row): string => implode('|', [$row['fecha'], $row['sistema'], $row['terminal'], $row['cedula']]))
            ->map(fn (Collection $grupo): array => [...$grupo->first(), ...$this->conciliar($grupo, $actividad)])->values();
    }

    public function fuenteSql(string $sistema, string $desde, string $hasta): string
    {
        $bet = strtolower($sistema) === 'lotobet';
        $tabla = $bet ? 'asistencias_bet' : 'asistencias_net';
        if (! Schema::hasTable($tabla)) {
            return '(SELECT NULL AS segundos_calculados, NULL AS fecha, NULL AS cedula, NULL AS identificacion WHERE 1 = 0)';
        }
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $normalizar = function (string $campo) use ($sqlite): string {
            $sql = "REPLACE(REPLACE(REPLACE(COALESCE({$campo}, ''), '-', ''), ' ', ''), '.', '')";

            return $sqlite ? $sql : "CONVERT({$sql} USING utf8mb4) COLLATE utf8mb4_unicode_ci";
        };
        $cedula = $bet ? 'cedula' : 'identificacion';
        $terminal = $bet ? 'agencia_id' : 'terminal';
        $fecha = $bet ? 'fecha' : 'entrada';
        $entrada = $bet ? 'primer_login' : 'entrada';
        $salida = $bet ? 'ultimo_login' : 'salida';
        $salidaSql = ! $bet && Schema::hasColumn($tabla, 'salida_inactividad') ? 'COALESCE(salida, salida_inactividad)' : $salida;
        $usuarioSql = Schema::hasColumn($tabla, 'usuario') ? 'MAX(usuario)' : 'NULL';
        $agenciaSql = ! $bet && Schema::hasColumn($tabla, 'agencia') ? 'MAX(agencia)' : "MAX({$terminal})";
        $usernameSql = ! $bet && Schema::hasColumn($tabla, 'username') ? 'MAX(username)' : 'NULL';
        $base = DB::table($tabla)->whereBetween($fecha, $bet ? [$desde, $hasta] : [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->selectRaw("DATE({$fecha}) AS dia, {$normalizar($cedula)} AS documento, CAST({$terminal} AS UNSIGNED) AS terminal_key, MAX({$terminal}) AS terminal_original, MIN({$entrada}) AS entrada_original, MAX({$salidaSql}) AS salida_original, {$usuarioSql} AS usuario, {$agenciaSql} AS agencia, {$usernameSql} AS username")
            ->groupByRaw("DATE({$fecha}), {$normalizar($cedula)}, CAST({$terminal} AS UNSIGNED)")->toRawSql();
        $fuentes = [];
        $fechasPreferidas = $this->fechasPreferidas($desde, $hasta);
        $diasConsultados = (int) CarbonImmutable::parse($desde)->diffInDays(CarbonImmutable::parse($hasta)) + 1;
        foreach (['ponche_por_cedula_ventas', 'nomina_domingo_ventas'] as $ventasTabla) {
            if (! Schema::hasTable($ventasTabla) || ($ventasTabla === 'nomina_domingo_ventas' && count($fechasPreferidas) === $diasConsultados)) {
                continue;
            }
            $query = DB::table($ventasTabla)->whereBetween('fecha_transaccion', [$desde.' 00:00:00', $hasta.' 23:59:59'])
                ->where(fn (Builder $query): Builder => $query->whereNull('tipo')->orWhereNull('estatus')->orWhere('tipo', '<>', 'Recargas')->orWhere('estatus', '<>', 'Pendiente'));
            if ($ventasTabla === 'nomina_domingo_ventas' && $fechasPreferidas !== []) {
                $query->whereNotIn(DB::raw('DATE(fecha_transaccion)'), $fechasPreferidas);
            }
            $fuentes[] = $query->selectRaw("DATE(fecha_transaccion) AS dia, {$normalizar('usuario_venta')} AS documento, CAST(terminal AS UNSIGNED) AS terminal_key, MIN(fecha_transaccion) AS primera, MAX(fecha_transaccion) AS ultima")->groupByRaw("DATE(fecha_transaccion), {$normalizar('usuario_venta')}, CAST(terminal AS UNSIGNED)")->toRawSql();
        }
        $ventasSql = $fuentes === [] ? 'SELECT NULL AS dia, NULL AS documento, NULL AS terminal_key, NULL AS primera, NULL AS ultima WHERE 1 = 0' : implode(' UNION ALL ', $fuentes);
        $ventasSql = "SELECT dia, documento, terminal_key, MIN(primera) AS primera, MAX(ultima) AS ultima FROM ({$ventasSql}) movimientos GROUP BY dia, documento, terminal_key";
        $cruce = 'MAX(CASE WHEN otra.documento IS NOT NULL THEN 1 ELSE 0 END) = 1';
        $restar = $sqlite ? "datetime(v.primera, '-2 minutes')" : 'DATE_SUB(v.primera, INTERVAL 2 MINUTE)';
        $sumar = $sqlite ? "datetime(v.ultima, '+5 minutes')" : 'DATE_ADD(v.ultima, INTERVAL 5 MINUTE)';
        $entradaCalculada = "CASE WHEN NOT ({$cruce}) AND p.entrada_original IS NOT NULL THEN p.entrada_original ELSE {$restar} END";
        $salidaCalculada = "CASE WHEN v.ultima IS NOT NULL AND (p.salida_original IS NULL OR v.ultima >= p.salida_original) THEN {$sumar} ELSE p.salida_original END";
        $diario = "SELECT p.*, v.primera AS primera_venta, {$entradaCalculada} AS entrada_calculada, {$salidaCalculada} AS salida_calculada FROM ({$base}) p LEFT JOIN ({$ventasSql}) v ON v.dia = p.dia AND v.terminal_key = p.terminal_key AND v.documento = p.documento LEFT JOIN ({$ventasSql}) otra ON otra.dia = p.dia AND otra.terminal_key = p.terminal_key AND otra.documento <> p.documento AND otra.primera <= COALESCE(p.salida_original, v.ultima) AND otra.ultima >= COALESCE(p.entrada_original, v.primera) GROUP BY p.dia, p.documento, p.terminal_key, p.terminal_original, p.entrada_original, p.salida_original, p.usuario, p.agencia, p.username, v.primera, v.ultima";
        $segundos = $sqlite ? 'MAX(0, ROUND((julianday(salida_calculada) - julianday(entrada_calculada)) * 86400))' : 'GREATEST(0, TIMESTAMPDIFF(SECOND, entrada_calculada, salida_calculada))';
        $campos = $bet
            ? 'dia AS fecha, terminal_original AS agencia_id, documento AS cedula, entrada_original AS primer_login, salida_original AS ultimo_login, usuario'
            : 'documento AS identificacion, terminal_original AS terminal, entrada_original AS entrada, salida_original AS salida, salida_original AS salida_inactividad, agencia, usuario, username';

        $diario = str_replace(["({$base}) p", "({$ventasSql}) v", "({$ventasSql}) otra"], ['ponches_diarios p', 'movimientos_conciliados v', 'movimientos_conciliados otra'], $diario);

        return "(WITH movimientos_conciliados AS ({$ventasSql}), ponches_diarios AS ({$base}), horarios_conciliados AS ({$diario}) SELECT {$campos}, entrada_calculada, salida_calculada, primera_venta, {$segundos} AS segundos_calculados FROM horarios_conciliados)";
    }

    /** @param array<int, string> $cedulas
     * @return Collection<string, float>
     */
    public function totalesPorCedula(string $desde, string $hasta, string $sistema, array $cedulas): Collection
    {
        $documentos = collect($cedulas)->map(fn (mixed $cedula): string => $this->documento($cedula))->filter()->unique();
        if ($documentos->isEmpty()) {
            return collect();
        }
        $queries = [];
        foreach (['Lotobet' => 'cedula', 'Lotonet' => 'identificacion'] as $origen => $campo) {
            if (strtolower($sistema) !== 'todos' && strtolower($sistema) !== strtolower($origen)) {
                continue;
            }
            $fuente = $this->fuenteSql(strtolower($origen), $desde, $hasta);
            $queries[] = DB::query()->fromRaw($fuente.' horas_diarias')->selectRaw($campo.' AS cedula, COALESCE(segundos_calculados, 0) AS segundos')->whereIn($campo, $documentos->all());
        }
        $query = array_shift($queries);
        if ($query === null) {
            return collect();
        }
        foreach ($queries as $union) {
            $query->unionAll($union);
        }

        return DB::query()->fromSub($query, 'horas')->selectRaw('cedula, SUM(segundos) / 3600 AS horas_total')->groupBy('cedula')->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->cedula => round((float) $row->horas_total, 2)]);
    }

    /** @param array<string, mixed> $registro
     * @return array<string, mixed>
     */
    public function registroApi(array $registro, string $fecha, Collection $actividad): array
    {
        $entrada = $registro['primer_login'] ?? $registro['entrada'] ?? null;
        $salida = $registro['ultimo_logout'] ?? $registro['salida'] ?? null;
        $instante = function (mixed $valor) use ($fecha): ?string {
            if (blank($valor)) {
                return null;
            }

            return CarbonImmutable::parse(str_contains((string) $valor, ':') && ! str_contains((string) $valor, '-') && ! str_contains((string) $valor, '/') ? $fecha.' '.$valor : $valor)->toDateTimeString();
        };

        return $this->conciliar(collect([[
            'fecha' => $fecha, 'cedula' => $this->documento($registro['cedula'] ?? $registro['identificacion'] ?? ''),
            'terminal' => $this->terminal($registro['terminal'] ?? $registro['agencia'] ?? $registro['agencia_id'] ?? ''),
            'primer_login' => $instante($entrada), 'ultimo_login' => $instante($salida),
        ]]), $actividad);
    }

    /** @return array<int, string> */
    private function fechasPreferidas(string $desde, string $hasta): array
    {
        return Schema::hasTable('ponche_por_cedula_ventas')
            ? DB::table('ponche_por_cedula_ventas')->whereBetween('fecha_transaccion', [$desde.' 00:00:00', $hasta.' 23:59:59'])->selectRaw('DATE(fecha_transaccion) AS dia')->distinct()->pluck('dia')->all()
            : [];
    }
}
