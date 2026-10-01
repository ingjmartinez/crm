<?php

namespace App\Console\Commands;

use App\Models\BiPremioDia;
use App\Models\BiVentaDia;
use App\Services\Bi\ResumirPremiosDia;
use App\Services\Bi\ResumirVentasDia;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class ResumirVentasBiDia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bi:resumir-ventas-dia
        {--fecha= : Fecha YYYY-MM-DD; por defecto ayer}
        {--desde= : Fecha inicial para reconstruir un rango}
        {--hasta= : Fecha final para reconstruir un rango}
        {--solo-faltantes : En un rango, omitir fechas que ya tienen ventas resumidas}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza los resúmenes BI de ventas y premios de un día cerrado';

    /**
     * Execute the console command.
     */
    public function handle(ResumirVentasDia $resumidorVentas, ResumirPremiosDia $resumidorPremios): int
    {
        $fecha = $this->option('fecha');
        $desde = $this->option('desde');
        $hasta = $this->option('hasta');

        if ($this->option('solo-faltantes') && ! $fecha && ! $desde && ! $hasta) {
            $desde = BiVentaDia::query()->min('fecha') ?: today()->subDay()->toDateString();
            $hasta = today()->subDay()->toDateString();
        }

        if ($fecha && ($desde || $hasta) || (bool) $desde !== (bool) $hasta) {
            $this->error('Use --fecha o el par --desde y --hasta.');

            return self::FAILURE;
        }

        $inicio = $this->fechaValida((string) ($desde ?: $fecha ?: today()->subDay()->toDateString()));
        $fin = $this->fechaValida((string) ($hasta ?: $fecha ?: today()->subDay()->toDateString()));
        if ($inicio === null || $fin === null || $fin->lessThan($inicio) || $fin->greaterThanOrEqualTo(today())) {
            $this->error('Indique fechas cerradas válidas en formato YYYY-MM-DD.');

            return self::FAILURE;
        }

        if ($desde && $hasta && ! $this->option('solo-faltantes')) {
            try {
                $cantidad = $resumidorVentas->resumirRango($inicio, $fin);
                $resumidorPremios->resumirRango($inicio, $fin);
            } catch (Throwable $exception) {
                report($exception);
                $this->error('No se pudo resumir el rango: '.$exception->getMessage());

                return self::FAILURE;
            }

            $this->info("{$cantidad} día(s) resumidos desde ventas y premios.");

            return self::SUCCESS;
        }

        $cantidad = 0;
        for ($dia = $inicio; $dia->lessThanOrEqualTo($fin); $dia = $dia->addDay()) {
            if ($this->option('solo-faltantes')) {
                $ventasResumidas = BiVentaDia::query()->whereDate('fecha', $dia->toDateString())->where('registros', '>', 0)->exists();
                $premiosResumidos = BiPremioDia::query()->whereDate('fecha', $dia->toDateString())->exists();

                if ($ventasResumidas && $premiosResumidos) {
                    continue;
                }
            }

            try {
                $resumen = $resumidorVentas->resumir($dia);
                $resumidorPremios->resumir($dia);
            } catch (Throwable $exception) {
                report($exception);
                $this->error('No se pudo resumir '.$dia->toDateString().': '.$exception->getMessage());

                return self::FAILURE;
            }

            $cantidad++;
            if ($cantidad % 10 === 0 || $dia->equalTo($fin)) {
                $this->info("{$resumen->fecha->toDateString()}: {$cantidad} día(s) resumidos.");
            }
        }

        return self::SUCCESS;
    }

    private function fechaValida(string $valor): ?CarbonImmutable
    {
        try {
            $fecha = CarbonImmutable::createFromFormat('!Y-m-d', $valor);

            return $fecha !== false && $fecha->toDateString() === $valor ? $fecha : null;
        } catch (Throwable) {
            return null;
        }
    }
}
