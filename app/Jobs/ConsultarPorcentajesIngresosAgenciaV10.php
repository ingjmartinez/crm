<?php

namespace App\Jobs;

use App\Services\IngresosCalculadosAgenciaV10Service;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ConsultarPorcentajesIngresosAgenciaV10 implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public string $empresa, public string $mes)
    {
        $this->onConnection('rentabilidad-v10');
        $this->onQueue('rentabilidad-v10');
    }

    public function handle(IngresosCalculadosAgenciaV10Service $ingresos): void
    {
        $resultado = $ingresos->consultarMes($this->empresa, $this->mes);
        $clave = IngresosCalculadosAgenciaV10Service::clave($this->empresa, $this->mes);
        Cache::put($clave, ['estado' => 'listo', ...$resultado], now()->addDay());
        Cache::forget($clave.':solicitud');
    }

    public function failed(?Throwable $exception): void
    {
        $clave = IngresosCalculadosAgenciaV10Service::clave($this->empresa, $this->mes);
        Cache::put($clave, ['estado' => 'error'], now()->addMinutes(5));
        Cache::forget($clave.':solicitud');
    }
}
