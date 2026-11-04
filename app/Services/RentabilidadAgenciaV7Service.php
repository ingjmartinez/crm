<?php

namespace App\Services;

use App\Models\CatalogoJuego;
use App\Models\ClasificacionCuenta;
use App\Models\EntradaDiario;
use App\Models\RentabilidadAgenciaR20;
use App\Models\RentabilidadAgenciaV5;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RentabilidadAgenciaV7Service
{
    public const PRODUCTOS = ['Tradicional', 'No Tradicional', 'Recarga'];

    public const IMPORTES = ['ventas', 'premios_sacados', 'utilidad', 'gastos', 'balance_general'];

    private const MEDIDAS = ['ventas', 'ventas_loterias', 'premios_sacados', 'costos', 'gastos_contables'];

    private const MINUTOS_RESUMEN_EJECUTIVO = 10;

    public function __construct(private readonly ClasificadorRentabilidadAgenciaV7 $clasificador) {}

    /** @param array<string, mixed> $filtros @return array{0: Carbon, 1: Carbon} */
    public function rango(array $filtros): array
    {
        $anio = (int) ($filtros['anio'] ?? now()->year);

        return [Carbon::create($anio, (int) ($filtros['mes_inicio'] ?? now()->month), 1)->startOfDay(), Carbon::create($anio, (int) ($filtros['mes_fin'] ?? now()->month), 1)->endOfMonth()];
    }

    /** @param array<string, mixed> $filtros @return list<string> */
    public function productos(array $filtros): array
    {
        return array_values(array_unique($filtros['productos'] ?? (! empty($filtros['productos_filtrados']) ? [] : self::PRODUCTOS)));
    }

    /** @param array<string, mixed> $filtros @param array<string, mixed> $resultado */
    public function guardarResumenEjecutivo(array $filtros, string $usuario, array $resultado): string
    {
        $terminales = $resultado['filas']->map(fn (array $fila): array => array_diff_key($fila, ['periodos' => true]))->values()->all();
        unset($resultado['filas']);
        Cache::put($this->claveResumenEjecutivo($filtros, $usuario), $resultado, now()->addMinutes(self::MINUTOS_RESUMEN_EJECUTIVO));

        $informeId = (string) Str::uuid();
        $resultado['configuracion_clasificacion'] = config('rentabilidad');
        $resultado['terminales'] = $terminales;
        Cache::put($this->claveConsultaEjecutiva($filtros, $usuario, $informeId), $resultado, now()->addMinutes(self::MINUTOS_RESUMEN_EJECUTIVO));

        return $informeId;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function resumenEjecutivo(array $filtros, string $usuario): array
    {
        if (! empty($filtros['informe_id'])) {
            $resultado = Cache::get($this->claveConsultaEjecutiva($filtros, $usuario, $filtros['informe_id']));
            if ($resultado === null) {
                throw ValidationException::withMessages(['informe_id' => 'La consulta del informe ha caducado. Vuelve a consultar el reporte y descarga nuevamente el PDF.']);
            }

            return $resultado;
        }

        return Cache::remember($this->claveResumenEjecutivo($filtros, $usuario), now()->addMinutes(self::MINUTOS_RESUMEN_EJECUTIVO), function () use ($filtros): array {
            $resultado = $this->reporte($filtros);
            unset($resultado['filas']);

            return $resultado;
        });
    }

    /** @param array<string, mixed> $resultado @return array<string, mixed> */
    public function personalizarInforme(array $resultado, string $ruta): array
    {
        if (! array_key_exists('terminales', $resultado)) {
            throw ValidationException::withMessages(['informe_id' => 'Vuelve a consultar el reporte para personalizar su impresión.']);
        }
        $filas = collect($resultado['terminales'])
            ->when($ruta !== '', fn (Collection $filas): Collection => $filas->where('ruta', $ruta))->values();
        if ($ruta !== '' && $filas->isEmpty()) {
            throw ValidationException::withMessages(['ruta_impresion' => 'La ruta seleccionada no pertenece a esta consulta.']);
        }
        $ordenCategorias = array_flip(array_column(ClasificadorRentabilidadAgenciaV7::categorias(), 'codigo'));
        $filas = $filas->sort(function (array $primera, array $segunda) use ($ordenCategorias): int {
            $categoriaPrimera = $ordenCategorias[$primera['clasificacion']['codigo']] ?? count($ordenCategorias);
            $categoriaSegunda = $ordenCategorias[$segunda['clasificacion']['codigo']] ?? count($ordenCategorias);

            return ($categoriaPrimera <=> $categoriaSegunda)
                ?: ($segunda['balance_general'] <=> $primera['balance_general'])
                ?: strcmp($primera['terminal'], $segunda['terminal']);
        })->values();
        $resultado['terminales'] = $filas->all();
        $resultado['totales'] = collect(self::IMPORTES)->mapWithKeys(fn (string $campo): array => [$campo => round($filas->sum($campo), 2)])->all();
        $resultado['resumen_clasificaciones'] = $this->resumenClasificaciones($filas);
        $resultado['rutas_impresion'] = $filas->groupBy('ruta')->sortKeys()->map(function (Collection $terminales, string $nombre): array {
            $conVentas = $terminales->filter(fn (array $fila): bool => $fila['ventas'] > 0);
            $promedioVentas = (float) $conVentas->avg('ventas');
            $ventasRuta = (float) $conVentas->sum('ventas');
            $proporcionGastosRuta = $ventasRuta > 0 ? $conVentas->sum('gastos') / $ventasRuta : 0.0;

            return [
                'ruta' => $nombre,
                'terminales' => $terminales->map(fn (array $fila): array => [
                    ...$fila, 'observaciones' => $this->observacionesTerminal($fila, $promedioVentas, $proporcionGastosRuta),
                ])->all(),
                'totales' => collect(self::IMPORTES)->mapWithKeys(fn (string $campo): array => [$campo => round($terminales->sum($campo), 2)])->all(),
            ];
        })->values()->all();

        return $resultado;
    }

    /** @param array<string, mixed> $fila @return list<array{texto: string, nivel: string}> */
    private function observacionesTerminal(array $fila, float $promedioVentas, float $proporcionGastosRuta): array
    {
        $ventas = (float) $fila['ventas'];
        $gastos = (float) $fila['gastos'];
        $premios = (float) $fila['premios_sacados'];
        if ($ventas <= 0) {
            $observaciones = [['texto' => $ventas < 0 ? 'Ventas netas negativas; revisar ajustes.' : 'Sin ventas registradas.', 'nivel' => 'alerta']];
            if ($gastos > 0) {
                $observaciones[] = ['texto' => 'Gastos sin ventas; revisar actividad.', 'nivel' => 'alerta'];
            }
            if ($premios > 0) {
                $observaciones[] = ['texto' => 'Premios sin ventas; revisar registros.', 'nivel' => 'alerta'];
            }

            return $observaciones;
        }
        $observaciones = [];
        if ($premios / $ventas * 100 >= (float) config('rentabilidad.observaciones.premios_elevados_ventas', 70)) {
            $observaciones[] = ['texto' => $premios > $ventas ? 'Premios superan las ventas.' : 'Premios elevados.', 'nivel' => $premios > $ventas ? 'alerta' : 'atencion'];
        }
        $proporcionGastos = $gastos / $ventas;
        if ($gastos > 0 && ($proporcionGastos * 100 >= (float) config('rentabilidad.observaciones.gastos_elevados_ventas', 50)
            || $proporcionGastos > $proporcionGastosRuta * (float) config('rentabilidad.observaciones.factor_gastos_ruta', 1.25))) {
            $observaciones[] = ['texto' => 'Revisar gastos elevados.', 'nivel' => 'atencion'];
        }
        if ($ventas < $promedioVentas * (float) config('rentabilidad.observaciones.ventas_bajas_promedio', 70) / 100) {
            $observaciones[] = ['texto' => 'Levantar ventas.', 'nivel' => 'atencion'];
        }
        if ($fila['clasificacion']['en_deterioro']) {
            $observaciones[] = ['texto' => 'Caída reciente del balance.', 'nivel' => 'alerta'];
        }
        if ($observaciones === []) {
            $observaciones[] = match (true) {
                $fila['balance_general'] < 0 => ['texto' => 'Revisar balance negativo.', 'nivel' => 'alerta'],
                $fila['clasificacion']['codigo'] === 'en_evaluacion' => ['texto' => 'Completar historial para evaluar.', 'nivel' => 'normal'],
                default => ['texto' => 'Mantener seguimiento.', 'nivel' => 'normal'],
            };
        }

        return array_slice($observaciones, 0, 3);
    }

    /** @param array<string, mixed> $filtros */
    private function claveResumenEjecutivo(array $filtros, string $usuario): string
    {
        return 'rentabilidad-v7:ejecutivo:v4:'.$this->clasificador->firmaConfiguracion().':'.$this->huellaFiltrosEjecutivos($filtros, $usuario);
    }

    /** @param array<string, mixed> $filtros */
    private function claveConsultaEjecutiva(array $filtros, string $usuario, string $informeId): string
    {
        return 'rentabilidad-v7:consulta:'.$this->huellaFiltrosEjecutivos($filtros, $usuario).':'.$informeId;
    }

    /** @param array<string, mixed> $filtros */
    private function huellaFiltrosEjecutivos(array $filtros, string $usuario): string
    {
        [$desde, $hasta] = $this->rango($filtros);
        $productos = $this->productos($filtros);
        sort($productos, SORT_STRING);
        $alcance = $filtros['alcance'] ?? 'todas';

        return hash('sha256', json_encode([
            'usuario' => $usuario,
            'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(),
            'empresa' => trim((string) ($filtros['empresa'] ?? '')),
            'ruta' => trim((string) ($filtros['ruta'] ?? '')),
            'terminal' => $alcance === 'terminal' ? trim((string) ($filtros['terminal'] ?? '')) : null,
            'productos' => $productos,
        ], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $filtros @return Collection<int, RentabilidadAgenciaV5> */
    public function agencias(array $filtros): Collection
    {
        $empresa = trim((string) ($filtros['empresa'] ?? ''));
        $ruta = trim((string) ($filtros['ruta'] ?? ''));

        return RentabilidadAgenciaV5::query()->activas()->whereNotNull('terminal')->where('terminal', '<>', '')
            ->when($empresa !== '', fn ($query) => $query->whereRaw('TRIM(empresa) = ?', [$empresa]))
            ->when($ruta !== '', fn ($query) => $query->whereRaw('TRIM(ruta) = ?', [$ruta]))
            ->when(($filtros['alcance'] ?? '') === 'terminal', fn ($query) => $query->where('terminal', trim((string) ($filtros['terminal'] ?? ''))))
            ->orderBy('terminal')->get(['terminal', 'nombre_agencia', 'agencia', 'sistema', 'empresa', 'ruta']);
    }

    /** @param array<string, mixed> $filtros @return array{filas: Collection, totales: array<string, float>, desde: Carbon, hasta: Carbon} */
    public function reporte(array $filtros, bool $diario = false): array
    {
        [$desde, $hasta] = $this->rango($filtros);
        $agencias = $this->agencias($filtros);
        $mediciones = $this->mediciones($agencias, $desde, $hasta, $this->productos($filtros), $diario);
        $r20 = (float) RentabilidadAgenciaR20::query()->where('terminal', '*')
            ->orderByRaw('CASE WHEN mes = ? THEN 0 ELSE 1 END', ['GLOBAL'])->orderByDesc('updated_at')->value('monto');
        $r20PorDia = $diario ? $this->distribuirR20Diario($mediciones, $r20) : [];
        $filas = $agencias->map(function (RentabilidadAgenciaV5 $agencia) use ($desde, $hasta, $mediciones, $r20, $r20PorDia, $diario): array {
            $terminal = trim((string) $agencia->terminal);
            $periodos = [];
            for ($fecha = $desde->copy(); $fecha->lte($hasta); $diario ? $fecha->addDay() : $fecha->addMonth()) {
                $periodo = $fecha->format($diario ? 'Y-m-d' : 'Y-m');
                $datos = (array) $mediciones->get($terminal.'|'.$periodo, []);
                $ventas = (float) ($datos['ventas'] ?? 0);
                $premiosSacados = (float) ($datos['premios_sacados'] ?? 0);
                $r20Periodo = $diario
                    ? ($r20PorDia[$terminal.'|'.$periodo] ?? 0.0)
                    : ($ventas > 0 ? $r20 : 0.0);
                $gastos = round((float) ($datos['costos'] ?? 0) + (float) ($datos['gastos_contables'] ?? 0) + $r20Periodo, 2);
                $periodos[] = [
                    'mes' => $periodo, 'ventas' => $ventas, 'premios_sacados' => $premiosSacados,
                    'ventas_loterias' => (float) ($datos['ventas_loterias'] ?? 0),
                    'utilidad' => round($ventas - $premiosSacados, 2),
                    'costos' => (float) ($datos['costos'] ?? 0), 'gastos_contables' => (float) ($datos['gastos_contables'] ?? 0),
                    'r20' => $r20Periodo, 'gastos' => $gastos,
                    'balance_general' => round($ventas - $premiosSacados - $gastos, 2),
                ];
            }
            $totales = collect(self::IMPORTES)->mapWithKeys(fn (string $campo): array => [$campo => round(array_sum(array_column($periodos, $campo)), 2)])->all();
            $mesesClasificacion = $diario
                ? collect($periodos)->groupBy(fn (array $dia): string => substr($dia['mes'], 0, 7))->map(fn (Collection $dias, string $mes): array => [
                    'mes' => $mes, 'ventas' => round($dias->sum('ventas'), 2), 'balance_general' => round($dias->sum('balance_general'), 2),
                ])->values()->all()
                : $periodos;

            return [
                'terminal' => $terminal, 'nombre' => trim((string) ($agencia->nombre_agencia ?: $agencia->agencia ?: $terminal)),
                'empresa' => trim((string) $agencia->empresa), 'ruta' => trim((string) $agencia->ruta), 'periodos' => $periodos, ...$totales,
                'clasificacion' => $this->clasificador->clasificar($mesesClasificacion),
            ];
        });

        return ['filas' => $filas, 'totales' => collect(self::IMPORTES)->mapWithKeys(fn (string $campo): array => [$campo => round($filas->sum($campo), 2)])->all(), 'desde' => $desde, 'hasta' => $hasta, 'resumen_clasificaciones' => $this->resumenClasificaciones($filas)];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return array{total: int, con_ventas: int, sin_ventas: int, categorias: list<array{nombre: string, color: string, cantidad: int}>}
     */
    private function resumenClasificaciones(Collection $filas): array
    {
        $terminales = $filas->unique('terminal');
        $clasificaciones = $terminales->countBy('clasificacion.nombre');
        $categorias = collect(ClasificadorRentabilidadAgenciaV7::categorias())
            ->map(fn (array $categoria): array => [
                'nombre' => $categoria['nombre'],
                'color' => $categoria['color'],
                'cantidad' => (int) $clasificaciones->get($categoria['nombre'], 0),
            ])->all();
        $sinVentas = (int) $clasificaciones->get('Sin ventas', 0);

        return [
            'total' => $terminales->count(), 'con_ventas' => $terminales->count() - $sinVentas,
            'sin_ventas' => $sinVentas, 'categorias' => $categorias,
            'en_deterioro' => $terminales->where('clasificacion.en_deterioro', true)->count(),
        ];
    }

    private function periodoSql(string $columna, bool $diario): string
    {
        if ($diario) {
            return 'DATE('.$columna.')';
        }

        return DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', ".$columna.')' : 'DATE_FORMAT('.$columna.", '%Y-%m')";
    }

    private function terminalSql(string $columna): string
    {
        return DB::connection()->getDriverName() === 'mysql'
            ? 'CONVERT('.$columna.' USING utf8mb4) COLLATE utf8mb4_unicode_ci'
            : $columna;
    }

    /**
     * @param  Collection<string, object>  $mediciones
     * @return array<string, float>
     */
    private function distribuirR20Diario(Collection $mediciones, float $r20): array
    {
        $importes = [];
        $centavos = (int) round($r20 * 100);
        $meses = $mediciones->groupBy(fn (object $fila): string => $fila->terminal.'|'.substr($fila->periodo, 0, 7));
        foreach ($meses as $movimientos) {
            if (round($movimientos->sum('ventas'), 2) <= 0) {
                continue;
            }
            $diasConVentas = $movimientos->filter(fn (object $fila): bool => (float) $fila->ventas > 0)->sortBy('periodo')->values();
            $cantidad = $diasConVentas->count();
            if ($cantidad === 0) {
                continue;
            }
            foreach ($diasConVentas as $indice => $fila) {
                $importe = intdiv($centavos, $cantidad) + ($indice < abs($centavos % $cantidad) ? ($centavos <=> 0) : 0);
                $importes[$fila->terminal.'|'.$fila->periodo] = $importe / 100;
            }
        }

        return $importes;
    }

    /** @param list<string> $productos */
    private function periodosConVentas(RentabilidadAgenciaV5 $agencia, Carbon $desde, Carbon $hasta, array $productos): Collection
    {
        $sistemas = match (mb_strtoupper(trim((string) $agencia->sistema))) {
            'LOTOBET' => ['bet'],
            'LOTONET', 'LOTENET' => ['net'],
            default => ['bet', 'net'],
        };
        $union = null;
        $periodo = $this->periodoSql('fecha', false);
        foreach ($sistemas as $sistema) {
            $ventas = DB::table('vt_usuarios_'.$sistema)->where('agencia_id', $agencia->terminal)
                ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->when(count($productos) !== count(self::PRODUCTOS), fn (Builder $query) => $query->whereIn('tipo', $productos))
                ->selectRaw($periodo.' AS periodo, SUM(COALESCE(monto, 0)) AS ventas')->groupByRaw($periodo);
            if ($union === null) {
                $union = $ventas;
            } else {
                $union->unionAll($ventas);
            }
        }

        return DB::query()->fromSub($union, 'ventas_terminal')->select('periodo')->groupBy('periodo')->havingRaw('SUM(ventas) > 0')->pluck('periodo');
    }

    /** @param Collection<int, RentabilidadAgenciaV5> $agencias @param list<string> $productos @return Collection<string, object> */
    private function mediciones(Collection $agencias, Carbon $desde, Carbon $hasta, array $productos, bool $diario): Collection
    {
        if ($agencias->isEmpty()) {
            return collect();
        }
        $ramas = [];
        $productosPremios = array_values(array_intersect($productos, ['Tradicional', 'No Tradicional']));
        $premiosFiltro = count($productosPremios) === 2 ? null : CatalogoJuego::query()->whereIn('tipo', $productosPremios)->pluck('producto_id')->map(fn ($id): string => (string) $id)->all();
        foreach (['bet', 'net'] as $sistema) {
            $terminales = $agencias->filter(function (RentabilidadAgenciaV5 $agencia) use ($sistema): bool {
                $nombre = mb_strtoupper(trim((string) $agencia->sistema));

                return $sistema === 'bet' ? ! in_array($nombre, ['LOTONET', 'LOTENET'], true) : $nombre !== 'LOTOBET';
            })->pluck('terminal')->unique()->all();
            if ($terminales === []) {
                continue;
            }
            $fuentes = [
                ['vt_usuarios_'.$sistema, [
                    'ventas' => 'COALESCE(monto, 0)',
                    'ventas_loterias' => "CASE WHEN tipo IN ('Tradicional', 'No Tradicional') THEN COALESCE(monto, 0) ELSE 0 END",
                ], count($productos) === count(self::PRODUCTOS) ? null : $productos, 'tipo'],
                ['premios_'.$sistema, ['premios_sacados' => 'COALESCE(monto, 0)'], $premiosFiltro, 'producto_id'],
            ];
            foreach ($fuentes as [$tabla, $medidas, $filtro, $columna]) {
                $periodo = $this->periodoSql('fecha', $diario);
                $consulta = DB::table($tabla)->whereIn('agencia_id', $terminales)
                    ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
                    ->when($filtro !== null, fn (Builder $query) => $query->whereIn($columna, $filtro))
                    ->selectRaw($this->terminalSql('agencia_id').' AS terminal, '.$periodo.' AS periodo');
                foreach (self::MEDIDAS as $medida) {
                    $consulta->selectRaw(isset($medidas[$medida]) ? 'SUM('.$medidas[$medida].') AS '.$medida : '0 AS '.$medida);
                }
                $ramas[] = $consulta->groupByRaw('agencia_id, '.$periodo);
            }
        }
        $clasificaciones = ClasificacionCuenta::clasificacionesActivas();
        if ($clasificaciones->isNotEmpty()) {
            $periodo = $this->periodoSql('movimientos.fecha', $diario);
            $importe = '(COALESCE(movimientos.debito, 0) - COALESCE(movimientos.credito, 0))';
            $costos = EntradaDiario::query()->from('entradas_diario as movimientos')
                ->join('cuentas_contables as cuentas', 'cuentas.cuenta', '=', 'movimientos.cuenta')
                ->whereIn('movimientos.id_viejo', $agencias->pluck('terminal')->unique()->all())
                ->whereBetween('movimientos.fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->whereIn('movimientos.cuenta', $clasificaciones->keys()->all())
                ->whereIn(DB::raw('LOWER(TRIM(cuentas.tipo))'), ['costo', 'gasto'])
                ->selectRaw($this->terminalSql('movimientos.id_viejo').' AS terminal, '.$periodo.' AS periodo');
            foreach (self::MEDIDAS as $medida) {
                $expresion = match ($medida) {
                    'costos' => "SUM(CASE WHEN LOWER(TRIM(cuentas.tipo)) = 'costo' THEN ".$importe.' ELSE 0 END)',
                    'gastos_contables' => "SUM(CASE WHEN LOWER(TRIM(cuentas.tipo)) = 'gasto' THEN ".$importe.' ELSE 0 END)',
                    default => '0',
                };
                $costos->selectRaw($expresion.' AS '.$medida);
            }
            if (DB::connection()->getDriverName() === 'mysql' && $agencias->pluck('terminal')->unique()->count() > 1) {
                $costos->forceIndex('entradas_diario_cuenta_index');
            }
            $ramas[] = $costos->groupByRaw('movimientos.id_viejo, '.$periodo)->toBase();
        }
        $union = array_shift($ramas);
        foreach ($ramas as $rama) {
            $union->unionAll($rama);
        }

        return DB::query()->fromSub($union, 'flujos')->select('terminal', 'periodo')
            ->selectRaw(implode(', ', array_map(fn (string $campo): string => 'SUM('.$campo.') AS '.$campo, self::MEDIDAS)))
            ->groupBy('terminal', 'periodo')->get()->keyBy(fn (object $fila): string => $fila->terminal.'|'.$fila->periodo);
    }

    /** @param array<string, mixed> $filtros @return array<string, mixed> */
    public function cuentas(array $filtros): array
    {
        [$desde, $hasta] = $this->rango($filtros);
        $agencia = $this->agencias([...$filtros, 'alcance' => 'terminal'])->first();
        abort_if($agencia === null, 404, 'No se encontro la terminal solicitada.');
        $clasificaciones = ClasificacionCuenta::clasificacionesActivas();
        $movimientos = collect();
        $periodo = $this->periodoSql('movimientos.fecha', false);
        if ($clasificaciones->isNotEmpty()) {
            $movimientos = EntradaDiario::query()->from('entradas_diario as movimientos')
                ->join('cuentas_contables as cuentas', 'cuentas.cuenta', '=', 'movimientos.cuenta')
                ->where('movimientos.id_viejo', $agencia->terminal)
                ->whereBetween('movimientos.fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->whereIn('movimientos.cuenta', $clasificaciones->keys()->all())
                ->whereIn(DB::raw('LOWER(TRIM(cuentas.tipo))'), ['costo', 'gasto'])
                ->selectRaw($periodo." AS mes, movimientos.cuenta AS cuenta, COALESCE(NULLIF(TRIM(cuentas.descripcion), ''), 'Sin descripcion') AS descripcion, LOWER(TRIM(cuentas.tipo)) AS tipo, SUM(COALESCE(movimientos.debito, 0) - COALESCE(movimientos.credito, 0)) AS importe")
                ->groupByRaw($periodo.', movimientos.cuenta, cuentas.descripcion, LOWER(TRIM(cuentas.tipo))')->orderBy('movimientos.cuenta')
                ->get()->map(fn ($fila): array => ['mes' => $fila->mes, 'cuenta' => (string) $fila->cuenta, 'descripcion' => $fila->descripcion, 'tipo' => $fila->tipo, 'clasificacion' => $clasificaciones->get($fila->cuenta), 'importe' => (float) $fila->importe]);
        }
        $periodosConVentas = $this->periodosConVentas($agencia, $desde, $hasta, $this->productos($filtros));
        $r20 = (float) RentabilidadAgenciaR20::query()->where('terminal', '*')->orderByRaw('CASE WHEN mes = ? THEN 0 ELSE 1 END', ['GLOBAL'])->orderByDesc('updated_at')->value('monto');
        $detalleMeses = [];
        for ($fecha = $desde->copy()->startOfMonth(); $fecha->lte($hasta); $fecha->addMonth()) {
            $mes = $fecha->format('Y-m');
            $cuentasMes = $movimientos->where('mes', $mes)->map(function (array $movimiento): array {
                unset($movimiento['mes']);

                return $movimiento;
            })->values();
            if ($periodosConVentas->contains($mes) && $r20 !== 0.0) {
                $cuentasMes->push(['cuenta' => 'R20', 'descripcion' => 'Gasto R20 manual (1 mes con ventas)', 'tipo' => 'gasto', 'clasificacion' => 'FIJO', 'importe' => round($r20, 2)]);
            }
            $detalleMeses[] = ['mes' => $mes, 'movimientos' => $cuentasMes->all(), 'total' => round($cuentasMes->sum('importe'), 2)];
        }
        $movimientos = $movimientos->groupBy('cuenta')->map(function (Collection $cuentas): array {
            $movimiento = $cuentas->first();
            unset($movimiento['mes']);
            $movimiento['importe'] = round($cuentas->sum('importe'), 2);

            return $movimiento;
        })->values();
        $meses = $periodosConVentas->count();
        if ($meses > 0 && $r20 !== 0.0) {
            $descripcion = $meses === 1 ? '1 mes con ventas' : $meses.' meses con ventas';
            $movimientos->push(['cuenta' => 'R20', 'descripcion' => 'Gasto R20 manual ('.$descripcion.')', 'tipo' => 'gasto', 'clasificacion' => 'FIJO', 'importe' => round($r20 * $meses, 2)]);
        }

        return ['terminal' => $agencia->terminal, 'agencia' => $agencia->nombre_agencia ?: $agencia->agencia ?: $agencia->terminal, 'meses' => $detalleMeses, 'movimientos' => $movimientos->values()->all(), 'total' => round($movimientos->sum('importe'), 2)];
    }
}
