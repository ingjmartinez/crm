<?php

namespace App\Console\Commands;

use App\Models\BiVentaHora;
use App\Services\Bi\CapturarVentasHora;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class CapturarVentasBiHora extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bi:capturar-ventas-hora {--force : Volver a consultar aunque ya exista una lectura para esta hora}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Guarda la lectura horaria acumulada de ventas LotoBet para BI';

    /**
     * Execute the console command.
     */
    public function handle(CapturarVentasHora $captura): int
    {
        $momento = CarbonImmutable::now();

        if ($momento->hour < 6 || $momento->hour > 22 || ($momento->hour === 22 && $momento->minute > 0)) {
            $this->info('Captura omitida: horario permitido de 06:00 a 22:00.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && BiVentaHora::query()->whereDate('fecha', $momento->toDateString())->where('hora', $momento->hour)->exists()) {
            $this->info('Captura omitida: esta hora ya tiene una lectura guardada.');

            return self::SUCCESS;
        }

        try {
            $lectura = $captura->capturar($momento);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('No se pudo capturar la venta horaria: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Lectura {$lectura->fecha->toDateString()} {$lectura->hora}:00 guardada.");

        return self::SUCCESS;
    }
}
