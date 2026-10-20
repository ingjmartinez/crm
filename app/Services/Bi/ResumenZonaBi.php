<?php

namespace App\Services\Bi;

use App\Models\Agencia;
use App\Models\BiVentaHora;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ResumenZonaBi
{
    public function __construct(private readonly ClasificarVentaBi $clasificador) {}

    /** @return array<string, mixed> */
    public function resumir(?string $zona, ?string $empresa, ?string $ruta, CarbonImmutable $desde, CarbonImmutable $hasta, Collection $lecturas): array
    {
        $agencias = Agencia::query()->when($zona, fn ($query) => $query->where('zona', $zona))
            ->when($empresa, fn ($query) => $query->where('empresa', $empresa))
            ->when($ruta, fn ($query) => $query->where('ruta', $ruta))
            ->whereNotNull('terminal')->get(['terminal', 'ruta', 'estatus']);
        $terminales = $agencias->pluck('terminal')->map(fn (string $terminal): string => ltrim(trim($terminal), '0') ?: '0')->unique()->values()->all();
        $codigos = $agencias->pluck('terminal')->merge($terminales)->unique()->all();
        $ayer = CarbonImmutable::yesterday();
        $inicio = $desde->min($ayer->subDays(13), CarbonImmutable::today()->subMonthNoOverflow());
        $fin = $hasta->max(CarbonImmutable::today());
        $ventas = [];
        if ($codigos !== []) {
            $filas = DB::table('vt_usuarios_bet')->whereIn('agencia_id', $codigos)
                ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
                ->select(['fecha', 'producto_id', 'tipo'])
                ->selectRaw('SUM(monto) AS monto, COUNT(*) AS registros')
                ->groupBy('fecha', 'producto_id', 'tipo')->get();
            foreach ($filas as $fila) {
                $dia = substr((string) $fila->fecha, 0, 10);
                $categoria = $this->clasificador->categoria((array) $fila);
                $ventas[$dia][$categoria] = ($ventas[$dia][$categoria] ?? 0) + (float) $fila->monto;
                $ventas[$dia]['registros'] = ($ventas[$dia]['registros'] ?? 0) + (int) $fila->registros;
                $producto = (string) $fila->producto_id;
                $ventas[$dia]['productos'][$producto] = ($ventas[$dia]['productos'][$producto] ?? 0) + (float) $fila->monto;
            }
        }
        $premios = $codigos === [] ? collect() : DB::table('premios_bet')->whereIn('agencia_id', $codigos)
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->select('fecha')->selectRaw('SUM(monto) AS monto')->groupBy('fecha')->pluck('monto', 'fecha');
        $categorias = ['tradicional', 'no_tradicional', 'externas', 'recargas', 'otros'];
        $totales = array_fill_keys($categorias, 0.0);
        $serieCategorias = [];
        $seriePremios = [];
        $diasConDatos = 0;
        for ($fecha = $desde; $fecha->lessThanOrEqualTo($hasta->min($ayer)); $fecha = $fecha->addDay()) {
            $dia = $fecha->toDateString();
            $valores = [];
            foreach ($categorias as $categoria) {
                $valores[$categoria] = (float) ($ventas[$dia][$categoria] ?? 0);
                $totales[$categoria] += $valores[$categoria];
            }
            $montoVentas = array_sum($valores);
            $montoPremios = (float) ($premios[$dia] ?? 0);
            $serieCategorias[] = ['fecha' => $dia, ...$valores, 'total' => $montoVentas];
            $seriePremios[] = [
                'fecha' => $dia, 'etiqueta' => $fecha->format('m-d'), 'ventas' => $montoVentas,
                'premios' => $montoPremios, 'utilidad' => $montoVentas - $montoPremios,
                'tasaPremiacion' => $montoVentas > 0 ? $montoPremios / $montoVentas * 100 : 0.0,
            ];
            if (($ventas[$dia]['registros'] ?? 0) > 0) {
                $diasConDatos++;
            }
        }
        $horas = $this->filtrarLecturas($lecturas, $terminales);
        $ultimaHora = $horas->last();
        $rutasPorTerminal = $agencias->mapWithKeys(fn (Agencia $agencia): array => [
            ltrim(trim((string) $agencia->terminal), '0') ?: '0' => trim((string) $agencia->ruta),
        ]);
        $ventasPorRuta = [];
        foreach ($terminales as $terminal) {
            $rutaTerminal = $rutasPorTerminal[$terminal] ?? '';
            if ($rutaTerminal !== '') {
                $ventasPorRuta[$rutaTerminal] = ($ventasPorRuta[$rutaTerminal] ?? 0)
                    + array_sum($ultimaHora['terminales'][$terminal] ?? []);
            }
        }
        arsort($ventasPorRuta, SORT_NUMERIC);
        $totalVentas = array_sum($totales);
        $totalPremios = array_sum(array_column($seriePremios, 'premios'));
        $evaluadas = Agencia::lotobet()->when($zona, fn ($query) => $query->where('zona', $zona))
            ->when($empresa, fn ($query) => $query->where('empresa', $empresa))
            ->when($ruta, fn ($query) => $query->where('ruta', $ruta))->where('estatus', 1)->count();
        $conVenta = null;
        $cobertura = array_fill_keys($categorias, 0);
        if ($ultimaHora !== null) {
            $conVenta = 0;
            foreach ($terminales as $terminal) {
                $valores = $this->clasificarProductos($ultimaHora['terminales'][$terminal] ?? []);
                foreach ($categorias as $categoria) {
                    if ($valores[$categoria] > 0) {
                        $cobertura[$categoria]++;
                    }
                }
                if ($valores['tradicional'] + $valores['no_tradicional'] + $valores['externas'] > 0) {
                    $conVenta++;
                }
            }
        }
        $hoy = CarbonImmutable::today();
        $ayerTexto = $ayer->toDateString();
        $mesAnterior = $hoy->subMonthNoOverflow()->toDateString();
        $ventaHoy = $ultimaHora['total'] ?? null;
        $premioHoy = isset($premios[$hoy->toDateString()]) ? (float) $premios[$hoy->toDateString()] : null;
        $comparativo = [
            'hoy' => $this->comparativo($hoy->toDateString(), $ventaHoy, $premioHoy, $evaluadas),
            'ayer' => $this->comparativo($ayerTexto, $this->totalDia($ventas[$ayerTexto] ?? null), isset($premios[$ayerTexto]) ? (float) $premios[$ayerTexto] : null),
            'mesAnterior' => $this->comparativo($mesAnterior, $this->totalDia($ventas[$mesAnterior] ?? null), isset($premios[$mesAnterior]) ? (float) $premios[$mesAnterior] : null),
        ];
        $semanaActual = [];
        $semanaAnterior = [];
        for ($indice = 0; $indice < 14; $indice++) {
            $dia = $ayer->subDays($indice)->toDateString();
            $monto = $this->totalDia($ventas[$dia] ?? null);
            if ($monto !== null) {
                if ($indice < 7) {
                    $semanaActual[] = $monto;
                } else {
                    $semanaAnterior[] = $monto;
                }
            }
        }
        $tendencia = count($semanaActual) === 7 && count($semanaAnterior) === 7 && array_sum($semanaAnterior) > 0
            ? (array_sum($semanaActual) / array_sum($semanaAnterior) - 1) * 100 : null;

        return [
            'terminales' => $terminales,
            'rutasConMasVenta' => collect($ventasPorRuta)->take(10)->map(fn (float|int $monto, string $nombre): array => [
                'nombre' => $nombre, 'monto' => round($monto, 2),
            ])->values()->all(),
            'ventasPorCategoria' => [
                ...$totales, 'total' => $totalVentas, 'desde' => $serieCategorias[0]['fecha'] ?? null,
                'hasta' => $serieCategorias[count($serieCategorias) - 1]['fecha'] ?? null,
                'diasSinDatos' => count($serieCategorias) - $diasConDatos, 'serie' => $serieCategorias,
            ],
            'ventasPremios' => [
                'ventas' => $totalVentas, 'premios' => $totalPremios, 'utilidad' => $totalVentas - $totalPremios,
                'tasaPremiacion' => $totalVentas > 0 ? $totalPremios / $totalVentas * 100 : 0.0,
                'desde' => $seriePremios[0]['fecha'] ?? null,
                'hasta' => $seriePremios[count($seriePremios) - 1]['fecha'] ?? null, 'serie' => $seriePremios,
            ],
            'lecturasPorHora' => $horas->map(fn (array $hora): array => array_diff_key($hora, ['productos' => true, 'terminales' => true]))->all(),
            'indicadores' => [
                'ventasHoy' => $ventaHoy, 'horaLectura' => $ultimaHora['hora'] ?? null, 'comparativoDia' => $comparativo,
                'ventasAyer' => $this->totalDia($ventas[$ayerTexto] ?? null), 'terminalesEvaluadas' => $evaluadas,
                'terminalesConVenta' => $conVenta, 'terminalesSinVenta' => $conVenta === null ? null : max(0, $evaluadas - $conVenta),
                'coberturaCategorias' => $cobertura, 'tendencia7d' => $tendencia,
                'diasTendenciaActual' => count($semanaActual), 'diasTendenciaAnterior' => count($semanaAnterior),
                'promedioVentasDiarias' => $diasConDatos > 0 ? $totalVentas / $diasConDatos : null,
                'diasPromedioConDatos' => $diasConDatos, 'diasPromedioEsperados' => count($serieCategorias),
            ],
            'quinielaLotekaHoy' => $ultimaHora !== null ? (float) ($ultimaHora['productos']['43'] ?? 0) : null,
            'megaChanceHoy' => $ultimaHora !== null ? (float) ($ultimaHora['productos']['38'] ?? 0) : null,
            'quinielaLotekaSemanaAnterior' => (float) ($ventas[$hoy->subDays(7)->toDateString()]['productos']['43'] ?? 0),
            'megaChanceSemanaAnterior' => (float) ($ventas[$hoy->subDays(7)->toDateString()]['productos']['38'] ?? 0),
        ];
    }

    /** @param Collection<int, BiVentaHora> $lecturas
     * @param  array<int, string>  $terminales
     * @return Collection<int, array<string, mixed>>
     */
    public function filtrarLecturas(Collection $lecturas, array $terminales): Collection
    {
        return $lecturas->map(function (BiVentaHora $lectura) use ($terminales): ?array {
            $porTerminal = $lectura->productos_terminales;
            if ($porTerminal === null) {
                return null;
            }
            $productos = [];
            foreach ($terminales as $terminal) {
                foreach ($porTerminal[$terminal] ?? [] as $producto => $monto) {
                    $productos[$producto] = ($productos[$producto] ?? 0) + (float) $monto;
                }
            }
            $valores = $this->clasificarProductos($productos);

            return [
                'hora' => $lectura->hora, 'tradicional' => $valores['tradicional'], 'noTradicional' => $valores['no_tradicional'],
                'total' => array_sum($valores), 'capturadoEn' => $lectura->capturado_en->toDateTimeString(), 'productos' => $productos,
                'terminales' => $porTerminal,
            ];
        })->filter()->values();
    }

    /** @param array<string, float|int> $productos
     * @return array<string, float>
     */
    private function clasificarProductos(array $productos): array
    {
        $valores = array_fill_keys(['tradicional', 'no_tradicional', 'externas', 'recargas', 'otros'], 0.0);
        foreach ($productos as $producto => $monto) {
            $valores[$this->clasificador->categoria(['producto_id' => (string) $producto])] += (float) $monto;
        }

        return $valores;
    }

    /** @param array<string, mixed>|null $dia */
    private function totalDia(?array $dia): ?float
    {
        if ($dia === null || ($dia['registros'] ?? 0) === 0) {
            return null;
        }

        return array_sum(array_map(fn (string $categoria): float => (float) ($dia[$categoria] ?? 0), ['tradicional', 'no_tradicional', 'externas', 'recargas', 'otros']));
    }

    /** @return array<string, float|int|string|null> */
    private function comparativo(string $fecha, ?float $ventas, ?float $premios, ?int $activas = null): array
    {
        return [
            'fecha' => $fecha, 'ventas' => $ventas, 'premios' => $premios,
            'resultado' => $ventas !== null && $premios !== null ? $ventas - $premios : null,
            'tasa' => $ventas !== null && $ventas > 0 && $premios !== null ? $premios / $ventas * 100 : null,
            'agenciasActivas' => $activas,
        ];
    }
}
