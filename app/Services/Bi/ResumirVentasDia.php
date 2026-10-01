<?php

namespace App\Services\Bi;

use App\Models\BiVentaDia;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ResumirVentasDia
{
    private const PRODUCTOS_RECORD = ['43', '38'];

    public function __construct(private readonly ClasificarVentaBi $clasificador) {}

    public function resumir(CarbonImmutable $fecha): BiVentaDia
    {
        $dia = $fecha->toDateString();
        $filas = DB::table('vt_usuarios_bet')
            ->select(['producto_id', 'tipo'])
            ->selectRaw('SUM(COALESCE(monto, 0)) AS monto, COUNT(*) AS registros')
            ->where('fecha', $dia)
            ->groupBy('producto_id', 'tipo')
            ->get();

        $resumen = [
            'tradicional' => 0.0,
            'no_tradicional' => 0.0,
            'externas' => 0.0,
            'recargas' => 0.0,
            'otros' => 0.0,
            'registros' => 0,
        ];
        $ventasProductos = [];
        foreach ($filas as $fila) {
            $categoria = $this->clasificador->categoria((array) $fila);
            $resumen[$categoria] += (float) $fila->monto;
            $resumen['registros'] += (int) $fila->registros;
            $productoId = (string) $fila->producto_id;
            if (in_array($productoId, self::PRODUCTOS_RECORD, true)) {
                $ventasProductos[$productoId]['monto'] = ($ventasProductos[$productoId]['monto'] ?? 0) + (float) $fila->monto;
                $ventasProductos[$productoId]['registros'] = ($ventasProductos[$productoId]['registros'] ?? 0) + (int) $fila->registros;
            }
        }

        $ahora = now()->toDateTimeString();
        DB::table('bi_venta_dias')->upsert(
            [[...$resumen, 'fecha' => $dia, 'origen' => 'vt_usuarios_bet', 'resumido_en' => $ahora, 'created_at' => $ahora, 'updated_at' => $ahora]],
            ['fecha'],
            ['tradicional', 'no_tradicional', 'externas', 'recargas', 'otros', 'registros', 'origen', 'resumido_en', 'updated_at'],
        );
        DB::table('bi_producto_dias')->upsert(
            array_map(fn (string $productoId): array => [
                'producto_id' => $productoId,
                'fecha' => $dia,
                'monto' => round($ventasProductos[$productoId]['monto'] ?? 0, 2),
                'registros' => $ventasProductos[$productoId]['registros'] ?? 0,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ], self::PRODUCTOS_RECORD),
            ['producto_id', 'fecha'],
            ['monto', 'registros', 'updated_at'],
        );

        return BiVentaDia::query()->whereDate('fecha', $dia)->firstOrFail();
    }

    public function resumirRango(CarbonImmutable $desde, CarbonImmutable $hasta): int
    {
        $resumenes = [];
        for ($fecha = $desde; $fecha->lessThanOrEqualTo($hasta); $fecha = $fecha->addDay()) {
            $resumenes[$fecha->toDateString()] = [
                'tradicional' => 0.0,
                'no_tradicional' => 0.0,
                'externas' => 0.0,
                'recargas' => 0.0,
                'otros' => 0.0,
                'registros' => 0,
            ];
        }

        $filas = DB::table('vt_usuarios_bet')
            ->select(['fecha', 'producto_id', 'tipo'])
            ->selectRaw('SUM(COALESCE(monto, 0)) AS monto, COUNT(*) AS registros')
            ->where('fecha', '>=', $desde->toDateString())
            ->where('fecha', '<', $hasta->addDay()->toDateString())
            ->groupBy('fecha', 'producto_id', 'tipo')
            ->get();

        $ventasProductos = [];

        foreach ($filas as $fila) {
            $dia = substr((string) $fila->fecha, 0, 10);
            $categoria = $this->clasificador->categoria((array) $fila);
            $resumenes[$dia][$categoria] += (float) $fila->monto;
            $resumenes[$dia]['registros'] += (int) $fila->registros;
            $productoId = (string) $fila->producto_id;
            if (in_array($productoId, self::PRODUCTOS_RECORD, true)) {
                $ventasProductos[$dia][$productoId]['monto'] = ($ventasProductos[$dia][$productoId]['monto'] ?? 0) + (float) $fila->monto;
                $ventasProductos[$dia][$productoId]['registros'] = ($ventasProductos[$dia][$productoId]['registros'] ?? 0) + (int) $fila->registros;
            }
        }

        $ahora = now()->toDateTimeString();
        collect($resumenes)
            ->map(fn (array $resumen, string $fecha): array => [
                ...$resumen,
                'fecha' => $fecha,
                'origen' => 'vt_usuarios_bet',
                'resumido_en' => $ahora,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ])
            ->values()
            ->chunk(500)
            ->each(fn ($bloque) => DB::table('bi_venta_dias')->upsert(
                $bloque->all(),
                ['fecha'],
                ['tradicional', 'no_tradicional', 'externas', 'recargas', 'otros', 'registros', 'origen', 'resumido_en', 'updated_at'],
            ));
        collect($resumenes)
            ->keys()
            ->flatMap(fn (string $fecha) => collect(self::PRODUCTOS_RECORD)->map(fn (string $productoId): array => [
                'producto_id' => $productoId,
                'fecha' => $fecha,
                'monto' => round($ventasProductos[$fecha][$productoId]['monto'] ?? 0, 2),
                'registros' => $ventasProductos[$fecha][$productoId]['registros'] ?? 0,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]))
            ->chunk(500)
            ->each(fn ($bloque) => DB::table('bi_producto_dias')->upsert(
                $bloque->all(),
                ['producto_id', 'fecha'],
                ['monto', 'registros', 'updated_at'],
            ));

        return count($resumenes);
    }
}
