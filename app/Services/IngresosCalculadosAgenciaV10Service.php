<?php

namespace App\Services;

use App\Jobs\ConsultarPorcentajesIngresosAgenciaV10;
use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IngresosCalculadosAgenciaV10Service
{
    public const CUENTA_VENTAS = '200111001';

    public const CUENTA_INGRESOS = '400120001';

    /** @var array<string, array<string, mixed>> */
    private array $consultas = [];

    public static function clave(string $empresa, string $mes): string
    {
        return 'rentabilidad-v10:porcentajes-ingresos:global-v2:'.$empresa.':'.$mes;
    }

    /** @return array{porcentaje: ?float, estado: string, base: float} */
    public function porcentaje(string $empresa, string $mes): array
    {
        $empresaId = match ($empresa) {
            'Grupo Joselito' => '168', 'Negosur' => '169', default => null,
        };
        if ($empresaId === null) {
            return ['porcentaje' => null, 'estado' => 'sin_datos', 'base' => 0.0];
        }
        $clave = self::clave($empresaId, $mes);
        if (! array_key_exists($clave, $this->consultas)) {
            $consulta = Cache::get($clave);
            if ($consulta === null) {
                if (Cache::add($clave.':solicitud', true, now()->addMinutes(15))) {
                    ConsultarPorcentajesIngresosAgenciaV10::dispatch($empresaId, $mes);
                }
                $consulta = ['estado' => 'pendiente'];
            }
            $this->consultas[$clave] = $consulta;
        }
        $consulta = $this->consultas[$clave];
        $porcentaje = $consulta['porcentaje'] ?? null;

        return ['porcentaje' => $porcentaje, 'estado' => $consulta['estado'] === 'listo' && $porcentaje === null ? 'sin_datos' : $consulta['estado'], 'base' => (float) ($consulta['base'] ?? 0)];
    }

    /** @return array{porcentaje: ?float, base: float} */
    public function consultarMes(string $empresa, string $mes): array
    {
        $desde = Carbon::createFromFormat('!Y-m', $mes);
        $consultas = [];
        for ($fecha = $desde->copy(); $fecha->month === $desde->month && $fecha->year === $desde->year; $fecha->addDay()) {
            foreach ([self::CUENTA_VENTAS, self::CUENTA_INGRESOS] as $cuenta) {
                $consultas[] = ['fecha' => $fecha->toDateString(), 'cuenta' => $cuenta];
            }
        }
        $creditos = [];
        foreach (array_chunk($consultas, 6) as $lote) {
            $respuestas = Http::pool(function (Pool $pool) use ($lote, $empresa): array {
                $peticiones = [];
                foreach ($lote as $indice => $consulta) {
                    $peticiones[] = $pool->as((string) $indice)->withOptions(['verify' => false])->timeout(30)->post(
                        'https://apisj.azurewebsites.net/ApiSJ/EntradaDiario/Listar?'.http_build_query([
                            'strToken' => config('services.apisj.token'), 'intIdEmpresa' => $empresa,
                            'dtFecha' => $consulta['fecha'], 'strCuenta' => $consulta['cuenta'],
                        ]),
                    );
                }

                return $peticiones;
            });
            foreach ($lote as $indice => $consulta) {
                foreach ($this->movimientos($respuestas[(string) $indice] ?? null) as $item) {
                    $item = array_change_key_case($item, CASE_UPPER);
                    if (trim((string) ($item['CUENTA'] ?? '')) !== $consulta['cuenta']
                        || substr((string) ($item['FECHA'] ?? ''), 0, 10) !== $consulta['fecha']
                        || trim((string) ($item['COMPANYID'] ?? '')) !== $empresa) {
                        throw new RuntimeException('La API devolvio movimientos ajenos a la consulta de ingresos.');
                    }
                    $importe = str_replace(',', '', (string) ($item['CREDITO'] ?? ''));
                    if (! is_numeric($importe)) {
                        throw new RuntimeException('La API devolvio un credito invalido.');
                    }
                    $creditos[$consulta['cuenta']] = ($creditos[$consulta['cuenta']] ?? 0) + (int) round((float) $importe * 100);
                }
            }
        }
        $ventas = $creditos[self::CUENTA_VENTAS] ?? 0;

        return ['porcentaje' => $ventas > 0 && array_key_exists(self::CUENTA_INGRESOS, $creditos) ? (float) ($creditos[self::CUENTA_INGRESOS] / $ventas) : null, 'base' => $ventas / 100.0];
    }

    /** @return list<array<string, mixed>> */
    private function movimientos(mixed $respuesta): array
    {
        if (! $respuesta instanceof Response || ! $respuesta->successful()) {
            throw new RuntimeException('No se pudieron consultar los porcentajes contables de ingresos.');
        }
        if (str_starts_with(trim($respuesta->body()), 'No existen datos para la empresa Id')) {
            return [];
        }
        $payload = $respuesta->json();
        if (! is_array($payload)) {
            throw new RuntimeException('La API devolvio una respuesta invalida para ingresos.');
        }
        $payload = array_change_key_case($payload, CASE_UPPER);
        $resultado = isset($payload['RESULT']) && is_array($payload['RESULT']) ? array_change_key_case($payload['RESULT'], CASE_UPPER) : $payload;
        if (! isset($resultado['DET']) || ! is_array($resultado['DET'])) {
            throw new RuntimeException('La API no devolvio el detalle de ingresos.');
        }

        return $resultado['DET'];
    }
}
