<?php

namespace Tests\Feature;

use App\Jobs\ConsultarPorcentajesIngresosAgenciaV10;
use App\Services\IngresosCalculadosAgenciaV10Service;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class IngresosCalculadosAgenciaV10Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Bus::fake();
    }

    public function test_consulta_todos_los_dias_y_usa_creditos_globales_sin_redondear_el_porcentaje(): void
    {
        $this->apiContable();
        (new ConsultarPorcentajesIngresosAgenciaV10('168', '2026-05'))->handle(app(IngresosCalculadosAgenciaV10Service::class));
        Http::assertSentCount(62);
        $service = app(IngresosCalculadosAgenciaV10Service::class);
        $porcentaje = $service->porcentaje('Grupo Joselito', '2026-05');
        $this->assertSame('listo', $porcentaje['estado']);
        $this->assertEqualsWithDelta(29095.02 / 559858, $porcentaje['porcentaje'], 0.000000000001);
        $this->assertSame(559858.0, $porcentaje['base']);
        $this->assertSame(['porcentaje' => null, 'estado' => 'sin_datos', 'base' => 0.0], $service->porcentaje('Otra empresa', '2026-05'));
        Bus::assertNothingDispatched();
    }

    public function test_encola_una_sola_consulta_por_empresa_y_mes_y_no_mezcla_porcentajes(): void
    {
        $clave = IngresosCalculadosAgenciaV10Service::clave('168', '2026-05');
        Cache::put($clave, ['estado' => 'listo', 'porcentaje' => 0.051979977, 'base' => 559158.0], now()->addHour());
        $service = app(IngresosCalculadosAgenciaV10Service::class);
        $this->assertSame(0.051979977, $service->porcentaje('Grupo Joselito', '2026-05')['porcentaje']);
        $this->assertSame('pendiente', $service->porcentaje('Negosur', '2026-05')['estado']);
        $this->assertSame('pendiente', (new IngresosCalculadosAgenciaV10Service)->porcentaje('Negosur', '2026-05')['estado']);
        $this->assertSame('pendiente', $service->porcentaje('Grupo Joselito', '2026-06')['estado']);
        Bus::assertDispatchedTimes(ConsultarPorcentajesIngresosAgenciaV10::class, 2);
        Bus::assertDispatched(ConsultarPorcentajesIngresosAgenciaV10::class, fn ($job): bool => $job->empresa === '169' && $job->mes === '2026-05' && $job->queue === 'rentabilidad-v10' && $job->connection === 'rentabilidad-v10');
        $this->assertGreaterThan((new ConsultarPorcentajesIngresosAgenciaV10('168', '2026-05'))->timeout, config('queue.connections.rentabilidad-v10.retry_after'));
    }

    public function test_un_error_de_api_no_guarda_porcentajes_parciales_y_permite_reintentar_despues(): void
    {
        $this->apiContable(fallar: true);
        $job = new ConsultarPorcentajesIngresosAgenciaV10('168', '2026-05');
        $clave = IngresosCalculadosAgenciaV10Service::clave('168', '2026-05');
        $fallo = false;
        try {
            $job->handle(app(IngresosCalculadosAgenciaV10Service::class));
        } catch (RuntimeException $exception) {
            $fallo = true;
            $this->assertNull(Cache::get($clave));
            $job->failed($exception);
        }
        $this->assertTrue($fallo, 'La consulta incompleta no debe generar un porcentaje.');
        $this->assertSame(['porcentaje' => null, 'estado' => 'error', 'base' => 0.0], (new IngresosCalculadosAgenciaV10Service)->porcentaje('Grupo Joselito', '2026-05'));
        $this->travel(6)->minutes();
        $this->assertSame('pendiente', (new IngresosCalculadosAgenciaV10Service)->porcentaje('Grupo Joselito', '2026-05')['estado']);
    }

    public function test_rechaza_movimientos_de_otra_empresa_o_periodo(): void
    {
        Http::fake(fn () => Http::response(['Result' => ['Det' => [[
            'CUENTA' => '200111001', 'COMPANYID' => '169', 'FECHA' => '2026-04-30',
            'IDVIEJO' => '050001', 'CREDITO' => '100.00',
        ]]]]));
        $this->expectException(RuntimeException::class);
        app(IngresosCalculadosAgenciaV10Service::class)->consultarMes('168', '2026-05');
    }

    public function test_distingue_una_tasa_global_cero_de_una_base_global_sin_datos(): void
    {
        $base = '100.00';
        Http::fake(function (Request $request) use (&$base) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $consulta);

            return Http::response(['Result' => ['Det' => [[
                'CUENTA' => $consulta['strCuenta'], 'COMPANYID' => '168', 'FECHA' => $consulta['dtFecha'],
                'CREDITO' => $consulta['strCuenta'] === '200111001' ? $base : '0.00',
            ]]]]);
        });
        $service = app(IngresosCalculadosAgenciaV10Service::class);
        $this->assertSame(['porcentaje' => 0.0, 'base' => 3100.0], $service->consultarMes('168', '2026-05'));
        $base = '0.00';
        $this->assertSame(['porcentaje' => null, 'base' => 0.0], $service->consultarMes('168', '2026-05'));
    }

    private function apiContable(bool $fallar = false): void
    {
        Http::fake(function (Request $request) use ($fallar) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $consulta);
            $cuenta = $consulta['strCuenta'];
            $fecha = $consulta['dtFecha'];
            if ($fallar && $fecha === '2026-05-02') {
                return Http::response('Servicio no disponible', 503);
            }
            if ($fecha !== '2026-05-31') {
                return Http::response('No existen datos para la empresa Id "168" en fecha '.$fecha, 200);
            }
            $importes = $cuenta === '200111001'
                ? ['050001' => '559,158.00', 'sin-base' => '0.00', 'sin-ingreso' => '100.00', 'cero' => '100.00', '' => '500.00']
                : ['050001' => '29,065.02', 'sin-base' => '10.00', 'cero' => '0.00', '' => '20.00'];
            $filas = [];
            foreach ($importes as $terminal => $credito) {
                $filas[] = ['Cuenta' => $cuenta, 'CompanyID' => '168', 'Fecha' => $fecha.'T00:00:00', 'IdViejo' => $terminal, 'Credito' => $credito, 'Debito' => '999999.00'];
            }

            return Http::response(['Result' => ['Det' => $filas]]);
        });
    }
}
