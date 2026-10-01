<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResumirProductosBi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bi:resumir-productos {--producto= : 43 Quiniela Loteka o 38 Mega Chance; vacío reconstruye ambos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconstruye los resúmenes diarios históricos de productos BI desde vt_usuarios_bet';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $producto = $this->option('producto');
        if ($producto !== null && ! in_array((string) $producto, ['43', '38'], true)) {
            $this->error('El producto debe ser 43 o 38.');

            return self::FAILURE;
        }

        $productos = $producto !== null ? [(string) $producto] : ['43', '38'];
        $ahora = now()->toDateTimeString();
        $cantidad = 0;

        DB::table('vt_usuarios_bet')
            ->whereIn('producto_id', $productos)
            ->where('fecha', '<', today()->toDateString())
            ->select(['fecha', 'producto_id'])
            ->selectRaw('SUM(COALESCE(monto, 0)) AS monto, COUNT(*) AS registros')
            ->groupBy('fecha', 'producto_id')
            ->orderBy('fecha')
            ->get()
            ->chunk(500)
            ->each(function ($filas) use ($ahora, &$cantidad): void {
                DB::table('bi_producto_dias')->upsert(
                    $filas->map(fn (object $fila): array => [
                        'producto_id' => (string) $fila->producto_id,
                        'fecha' => $fila->fecha,
                        'monto' => $fila->monto,
                        'registros' => $fila->registros,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ])->all(),
                    ['producto_id', 'fecha'],
                    ['monto', 'registros', 'updated_at'],
                );
                $cantidad += $filas->count();
            });

        $this->info("{$cantidad} resúmenes diarios de productos guardados.");

        return self::SUCCESS;
    }
}
