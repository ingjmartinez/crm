<?php

namespace App\Http\Controllers\Bi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bi\IndexAgenciaTerminalRequest;
use App\Models\Agencia;
use App\Models\BiVentaHora;
use App\Models\BiVentaSnapshot;
use App\Models\VentaOnlinePromedioHistorico;
use App\Services\Bi\AgenciaTerminalCatalogo;
use App\Services\Bi\ClasificarVentaBi;
use Illuminate\View\View;

class TvDashboardController extends Controller
{
    public function index(IndexAgenciaTerminalRequest $request, AgenciaTerminalCatalogo $catalogo, ClasificarVentaBi $clasificador): View
    {
        $empresa = trim((string) $request->validated('empresa', ''));
        $zona = trim((string) $request->validated('zona', ''));
        $ruta = trim((string) $request->validated('ruta', ''));
        $filtrado = $empresa !== '' || $zona !== '' || $ruta !== '';
        $agencias = $filtrado ? Agencia::query()->when($empresa, fn ($query) => $query->where('empresa', $empresa))
            ->when($zona, fn ($query) => $query->where('zona', $zona))
            ->when($ruta, fn ($query) => $query->where('ruta', $ruta))
            ->whereNotNull('terminal')->get(['terminal', 'ruta']) : collect();
        $terminales = $agencias->pluck('terminal')->map(fn (string $terminal): string => ltrim(trim($terminal), '0') ?: '0')->unique()->all();
        $fecha = today()->toDateString();
        $fechaAnterior = today()->subDays(7)->toDateString();
        $lecturas = BiVentaHora::query()->where('fecha', $fecha)->whereBetween('hora', [6, 22])->orderBy('hora')->get();
        $lecturasAnteriores = BiVentaHora::query()->where('fecha', $fechaAnterior)->whereBetween('hora', [6, 22])->orderBy('hora')->get()->keyBy('hora');
        if ($filtrado) {
            $lecturas = $lecturas->map(fn (BiVentaHora $lectura): ?BiVentaHora => $this->filtrarLectura($lectura, $terminales, $clasificador))->filter()->values();
            $lecturasAnteriores = $lecturasAnteriores->map(fn (BiVentaHora $lectura): ?BiVentaHora => $this->filtrarLectura($lectura, $terminales, $clasificador))->filter()->keyBy('hora');
        }
        $ultima = $lecturas->last();
        $total = $ultima ? $this->totalHora($ultima) : 0.0;
        $maximo = max(1.0, $total, ...$lecturasAnteriores->map(fn (BiVentaHora $lectura): float => $this->totalHora($lectura))->all());
        $pasoBase = 10 ** floor(log10($maximo / 5));
        $paso = collect([1, 2, 2.5, 5, 10])->map(fn (float|int $factor): float => $factor * $pasoBase)
            ->first(fn (float $candidato): bool => ceil($maximo / $candidato) <= 5) ?? 10 * $pasoBase;
        $tope = $paso * ceil($maximo / $paso);
        $coordenadas = fn (BiVentaHora $lectura): array => [
            'x' => 70 + (($lectura->hora - 6) * 49),
            'y' => 190 - (($this->totalHora($lectura) / $tope) * 160),
        ];
        $puntosHoy = $lecturas->map(function (BiVentaHora $lectura) use ($coordenadas, $lecturasAnteriores): array {
            $anterior = $lecturasAnteriores->get($lectura->hora);
            $totalAnterior = $anterior ? $this->totalHora($anterior) : 0.0;

            return [
                'hora' => $lectura->hora,
                'total' => $this->totalHora($lectura),
                'porcentaje' => $totalAnterior > 0 ? (($this->totalHora($lectura) / $totalAnterior) - 1) * 100 : null,
                ...$coordenadas($lectura),
            ];
        });
        $puntosAnteriores = $lecturasAnteriores->values()->map(fn (BiVentaHora $lectura): array => [
            'hora' => $lectura->hora,
            'total' => $this->totalHora($lectura),
            ...$coordenadas($lectura),
        ]);
        $promedios = VentaOnlinePromedioHistorico::query()->whereIn('tipo_categoria', ['tradicional', 'no_tradicional'])
            ->get(['tipo_categoria', 'monto_promedio'])->pluck('monto_promedio', 'tipo_categoria');
        $snapshot = $ultima ? BiVentaSnapshot::query()->where('fecha', $fecha)->first(['rutas']) : null;
        $rutas = collect($snapshot?->rutas ?? [])->take(10);
        if ($filtrado) {
            $rutasPorTerminal = $agencias->mapWithKeys(fn (Agencia $agencia): array => [
                ltrim(trim((string) $agencia->terminal), '0') ?: '0' => trim((string) $agencia->ruta),
            ]);
            $ventasPorRuta = [];
            $ultimaPorTerminal = $ultima?->productos_terminales ?? [];
            foreach ($terminales as $terminal) {
                $rutaTerminal = $rutasPorTerminal[$terminal] ?? '';
                if ($rutaTerminal !== '') {
                    $ventasPorRuta[$rutaTerminal] = ($ventasPorRuta[$rutaTerminal] ?? 0) + array_sum($ultimaPorTerminal[$terminal] ?? []);
                }
            }
            arsort($ventasPorRuta, SORT_NUMERIC);
            $rutas = collect($ventasPorRuta)->take(10)->map(fn (float|int $monto, string $nombre): array => [
                'nombre' => $nombre, 'monto' => $monto,
            ])->values();
        }

        return view('bi.tv', [
            'fecha' => $fecha,
            'fechaAnterior' => $fechaAnterior,
            'lecturas' => $lecturas,
            'ultima' => $ultima,
            'total' => $total,
            'tope' => $tope,
            'puntosHoy' => $puntosHoy,
            'puntosAnteriores' => $puntosAnteriores,
            'promedios' => $promedios,
            'rutas' => $rutas,
            'empresas' => $catalogo->empresas(),
            'zonas' => $catalogo->zonas($empresa),
            'rutasDisponibles' => $catalogo->rutas($empresa, $zona),
            'empresaSeleccionada' => $empresa,
            'zonaSeleccionada' => $zona,
            'rutaSeleccionada' => $ruta,
            'filtrado' => $filtrado,
        ]);
    }

    /** @param array<int, string> $terminales */
    private function filtrarLectura(BiVentaHora $lectura, array $terminales, ClasificarVentaBi $clasificador): ?BiVentaHora
    {
        $porTerminal = $lectura->productos_terminales;
        if ($porTerminal === null) {
            return null;
        }

        $totales = array_fill_keys(['tradicional', 'no_tradicional', 'externas', 'recargas', 'otros'], 0.0);
        $terminalesCategoria = array_fill_keys(array_keys($totales), 0);
        foreach ($terminales as $terminal) {
            $categoriasConVenta = [];
            foreach ($porTerminal[$terminal] ?? [] as $producto => $monto) {
                $categoria = $clasificador->categoria(['producto_id' => (string) $producto]);
                $totales[$categoria] += (float) $monto;
                if ($monto > 0) {
                    $categoriasConVenta[$categoria] = true;
                }
            }
            foreach (array_keys($categoriasConVenta) as $categoria) {
                $terminalesCategoria[$categoria]++;
            }
        }

        foreach ($totales as $categoria => $monto) {
            $lectura->setAttribute($categoria.'_acumulado', $monto);
        }
        $lectura->setAttribute('terminales_categoria', $terminalesCategoria);

        return $lectura;
    }

    private function totalHora(BiVentaHora $lectura): float
    {
        return (float) $lectura->tradicional_acumulado + (float) $lectura->no_tradicional_acumulado
            + (float) $lectura->externas_acumulado + (float) $lectura->recargas_acumulado
            + (float) $lectura->otros_acumulado;
    }
}
