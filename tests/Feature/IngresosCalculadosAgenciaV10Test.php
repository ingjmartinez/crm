<?php

namespace Tests\Feature;

use App\Models\EntradaDiario;
use App\Services\IngresosCalculadosAgenciaV10Service;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IngresosCalculadosAgenciaV10Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Bus::fake();
        Schema::create('entradas_diario', function (Blueprint $table): void {
            $table->id();
            $table->string('company_id');
            $table->string('id_viejo')->nullable();
            $table->date('fecha');
            $table->string('cuenta');
            $table->decimal('credito', 15, 2);
            $table->decimal('debito', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function test_suma_creditos_globales_locales_de_ambas_cuentas_sin_filtrar_terminal(): void
    {
        foreach ([
            ['050001', '200111001', 559158], ['050001', '400120001', 29065.02],
            ['otra-agencia', '200111001', 200], ['otra-agencia', '400120001', 10],
            [null, '200111001', 500], [null, '400120001', 20],
            ['050001', '999999999', 999999],
        ] as [$terminal, $cuenta, $credito]) {
            EntradaDiario::query()->insert([
                'company_id' => '168', 'id_viejo' => $terminal, 'fecha' => '2026-05-31',
                'cuenta' => $cuenta, 'credito' => $credito, 'debito' => 999999,
            ]);
        }
        $this->cuentas('169', '2026-05-31', 1000, 900);
        $this->cuentas('168', '2026-04-30', 1000, 800);
        $this->cuentas('168', '2026-06-01', 1000, 700);
        $resultado = app(IngresosCalculadosAgenciaV10Service::class)->porcentaje('Grupo Joselito', '2026-05');
        $this->assertSame('listo', $resultado['estado']);
        $this->assertSame(559858.0, $resultado['base']);
        $this->assertEqualsWithDelta(29095.02 / 559858, $resultado['porcentaje'], 0.000000000001);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public function test_separa_los_porcentajes_por_empresa_y_mes(): void
    {
        $this->cuentas('168', '2026-05-31', 1000, 100);
        $this->cuentas('168', '2026-06-30', 1000, 200);
        $this->cuentas('169', '2026-05-31', 2000, 600);
        $service = app(IngresosCalculadosAgenciaV10Service::class);
        $this->assertSame(0.1, $service->porcentaje('Grupo Joselito', '2026-05')['porcentaje']);
        $this->assertSame(0.2, $service->porcentaje('Grupo Joselito', '2026-06')['porcentaje']);
        $this->assertSame(0.3, $service->porcentaje('Negosur', '2026-05')['porcentaje']);
        $this->assertSame(['porcentaje' => null, 'estado' => 'sin_datos', 'base' => 0.0], $service->porcentaje('Otra empresa', '2026-05'));
    }

    #[DataProvider('cuentasSinDatos')]
    public function test_distingue_porcentaje_cero_de_cuentas_o_base_faltantes(?float $ventas, ?float $ingresos, ?float $esperado): void
    {
        $this->cuentas('168', '2026-05-31', $ventas, $ingresos);
        $resultado = app(IngresosCalculadosAgenciaV10Service::class)->porcentaje('Grupo Joselito', '2026-05');
        $this->assertSame($esperado, $resultado['porcentaje']);
        $this->assertSame($esperado === null ? 'sin_datos' : 'listo', $resultado['estado']);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public static function cuentasSinDatos(): array
    {
        return [
            'ingreso cero' => [1000.0, 0.0, 0.0],
            'base cero' => [0.0, 100.0, null],
            'sin cuenta de ingresos' => [1000.0, null, null],
            'sin cuenta de ventas' => [null, 100.0, null],
            'sin movimientos' => [null, null, null],
        ];
    }

    public function test_ignora_la_cache_anterior_y_refleja_las_sincronizaciones_en_la_siguiente_consulta(): void
    {
        Cache::put(IngresosCalculadosAgenciaV10Service::clave('168', '2026-05'), ['estado' => 'pendiente'], now()->addDay());
        $this->cuentas('168', '2026-05-31', 1000, 100);
        $this->assertSame(0.1, (new IngresosCalculadosAgenciaV10Service)->porcentaje('Grupo Joselito', '2026-05')['porcentaje']);
        EntradaDiario::query()->where('cuenta', '400120001')->update(['credito' => 200]);
        $this->assertSame(0.2, (new IngresosCalculadosAgenciaV10Service)->porcentaje('Grupo Joselito', '2026-05')['porcentaje']);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public function test_reutiliza_una_sola_consulta_por_empresa_y_mes_dentro_de_la_peticion(): void
    {
        $this->cuentas('168', '2026-05-31', 1000, 100);
        $connection = (new EntradaDiario)->getConnection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();
        $service = new IngresosCalculadosAgenciaV10Service;
        for ($i = 0; $i < 25; $i++) {
            $this->assertSame(0.1, $service->porcentaje('Grupo Joselito', '2026-05')['porcentaje']);
        }
        $this->assertCount(1, $connection->getQueryLog());
        $connection->disableQueryLog();
    }

    private function cuentas(string $empresa, string $fecha, ?float $ventas, ?float $ingresos): void
    {
        foreach (['200111001' => $ventas, '400120001' => $ingresos] as $cuenta => $credito) {
            if ($credito !== null) {
                EntradaDiario::query()->insert(['company_id' => $empresa, 'fecha' => $fecha, 'cuenta' => $cuenta, 'credito' => $credito]);
            }
        }
    }
}
