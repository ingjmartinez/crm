<?php

namespace Tests\Feature;

use App\Models\BiVentaHora;
use App\Services\Bi\CapturarVentasHora;
use App\Services\Lotobet\LotobetSessionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class CapturarVentasBiHoraTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('catalogo_juegos', function (Blueprint $table): void {
            $table->id();
            $table->string('producto_id')->unique();
            $table->string('tipo')->nullable();
            $table->string('descripcion')->nullable();
        });

        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal')->nullable();
            $table->string('ruta')->nullable();
            $table->string('sistema')->nullable();
            $table->string('empresa')->nullable();
            $table->integer('estatus')->default(1);
            $table->timestamps();
        });

        Schema::create('bi_venta_horas', function (Blueprint $table): void {
            $table->id();
            $table->date('fecha');
            $table->unsignedTinyInteger('hora');
            $table->decimal('tradicional_acumulado', 18, 2);
            $table->decimal('no_tradicional_acumulado', 18, 2);
            $table->decimal('otros_acumulado', 18, 2);
            $table->decimal('externas_acumulado', 18, 2)->default(0);
            $table->decimal('recargas_acumulado', 18, 2)->default(0);
            $table->unsignedInteger('registros');
            $table->dateTime('capturado_en');
            $table->json('rutas')->nullable();
            $table->json('productos')->nullable();
            $table->json('productos_terminales')->nullable();
            $table->unsignedInteger('terminales_evaluadas')->nullable();
            $table->unsignedInteger('terminales_con_venta')->nullable();
            $table->json('terminales_categoria')->nullable();
            $table->decimal('quiniela_loteka_acumulado', 18, 2)->nullable();
            $table->decimal('mega_chance_acumulado', 18, 2)->nullable();
            $table->timestamps();
            $table->unique(['fecha', 'hora']);
        });
    }

    public function test_captures_classified_cumulative_sales_and_updates_the_same_hour(): void
    {
        DB::table('catalogo_juegos')->insert([
            ['producto_id' => '1', 'tipo' => 'Tradicional', 'descripcion' => 'Lotería'],
            ['producto_id' => '2', 'tipo' => 'No Tradicional', 'descripcion' => 'Juego especial'],
        ]);
        DB::table('agencias')->insert([
            ['terminal' => '00100', 'ruta' => 'RUTA NORTE', 'sistema' => 'LOTOBET'],
            ['terminal' => '200', 'ruta' => 'RUTA SUR', 'sistema' => 'LOTOBET'],
        ]);

        $lotobet = $this->mock(LotobetSessionService::class);
        $lotobet->shouldReceive('getVentasProducto')->twice()->with('2026-09-29')->andReturn(
            ['Content' => [
                ['producto_id' => '1', 'agencia_id' => '100', 'monto' => 100],
                ['producto_id' => '2', 'agencia_id' => '200', 'monto' => 40],
                ['producto_id' => '3', 'agencia_id' => '100', 'tipo' => 'Recarga', 'monto' => 10],
            ]],
            ['Content' => [
                ['producto_id' => '1', 'agencia_id' => '100', 'monto' => 125],
                ['producto_id' => '2', 'agencia_id' => '200', 'monto' => 60],
            ]],
        );

        $capturador = app(CapturarVentasHora::class);
        $primera = $capturador->capturar(CarbonImmutable::parse('2026-09-29 14:05:00'));
        $segunda = $capturador->capturar(CarbonImmutable::parse('2026-09-29 14:45:00'));

        $this->assertSame($primera->id, $segunda->id);
        $this->assertSame('10.00', $primera->recargas_acumulado);
        $this->assertSame(1, BiVentaHora::query()->count());
        $this->assertSame('125.00', $segunda->tradicional_acumulado);
        $this->assertSame('60.00', $segunda->no_tradicional_acumulado);
        $this->assertSame('0.00', $segunda->otros_acumulado);
        $this->assertSame('0.00', $segunda->recargas_acumulado);
        $this->assertSame(2, $segunda->registros);
        $this->assertSame('RUTA NORTE', $segunda->rutas[0]['nombre']);
        $this->assertSame(125, $segunda->rutas[0]['monto']);
        $this->assertSame('RUTA SUR', $segunda->rutas[1]['nombre']);
        $this->assertSame(2, $segunda->terminales_evaluadas);
        $this->assertSame(2, $segunda->terminales_con_venta);
        $this->assertSame(1, $primera->terminales_categoria['recargas']);
        $this->assertSame(1, $segunda->terminales_categoria['tradicional']);
        $this->assertSame(1, $segunda->terminales_categoria['no_tradicional']);
        $this->assertEquals(['100' => ['1' => 100, '3' => 10], '200' => ['2' => 40]], $primera->productos_terminales);
        $this->assertEquals(['100' => ['1' => 125], '200' => ['2' => 60]], $segunda->productos_terminales);
    }

    public function test_invalid_api_response_does_not_create_a_snapshot(): void
    {
        $this->mock(LotobetSessionService::class)
            ->shouldReceive('getVentasProducto')
            ->once()
            ->andReturn(['Content' => [['producto_id' => '1']]]);

        try {
            app(CapturarVentasHora::class)->capturar(CarbonImmutable::parse('2026-09-29 14:05:00'));
            $this->fail('La respuesta inválida debió rechazarse.');
        } catch (RuntimeException $exception) {
            $this->assertSame(0, BiVentaHora::query()->count());
        }
    }

    public function test_captures_quiniela_loteka_by_product_id(): void
    {
        $this->mock(LotobetSessionService::class)
            ->shouldReceive('getVentasProducto')
            ->once()
            ->andReturn(['Content' => [
                ['producto_id' => 43, 'agencia_id' => '00100', 'monto' => 125],
                ['producto_id' => '43', 'agencia_id' => '200', 'monto' => 75],
                ['producto_id' => 44, 'agencia_id' => '100', 'monto' => 50],
                ['producto_id' => 38, 'agencia_id' => '100', 'monto' => 30],
            ]]);

        $lectura = app(CapturarVentasHora::class)->capturar(CarbonImmutable::parse('2026-09-29 14:05:00'));

        $this->assertSame('200.00', $lectura->quiniela_loteka_acumulado);
        $this->assertSame('30.00', $lectura->mega_chance_acumulado);
        $this->assertEquals(['43' => 200, '44' => 50, '38' => 30], $lectura->productos);
        $this->assertEquals(['100' => ['43' => 125, '44' => 50, '38' => 30], '200' => ['43' => 75]], $lectura->productos_terminales);
    }

    public function test_zero_sales_counts_only_active_lotobet_terminals_with_positive_net_sales(): void
    {
        DB::table('agencias')->insert([
            ['terminal' => '00100', 'sistema' => 'LOTOBET', 'estatus' => 1],
            ['terminal' => '00200', 'sistema' => 'LOTOBET', 'estatus' => 1],
            ['terminal' => '00300', 'sistema' => 'LOTOBET', 'estatus' => 0],
            ['terminal' => '00400', 'sistema' => 'LOTENET', 'estatus' => 1],
        ]);
        $this->mock(LotobetSessionService::class)
            ->shouldReceive('getVentasProducto')
            ->once()
            ->andReturn(['Content' => [
                ['agencia_id' => '100', 'tipo' => 'Tradicional', 'monto' => 10],
                ['agencia_id' => '00100', 'tipo' => 'Tradicional', 'monto' => -10],
                ['agencia_id' => '200', 'tipo' => 'No Tradicional', 'monto' => 5],
                ['agencia_id' => '300', 'tipo' => 'Tradicional', 'monto' => 8],
                ['agencia_id' => '400', 'tipo' => 'Tradicional', 'monto' => 9],
            ]]);

        $lectura = app(CapturarVentasHora::class)->capturar(CarbonImmutable::parse('2026-09-29 14:05:00'));

        $this->assertSame(2, $lectura->terminales_evaluadas);
        $this->assertSame(1, $lectura->terminales_con_venta);
        $this->assertSame(0, $lectura->terminales_categoria['tradicional']);
        $this->assertSame(1, $lectura->terminales_categoria['no_tradicional']);
    }

    public function test_recargas_alone_do_not_count_as_lottery_sales(): void
    {
        DB::table('agencias')->insert([
            ['terminal' => '00100', 'sistema' => 'LOTOBET', 'estatus' => 1],
            ['terminal' => '00200', 'sistema' => 'LOTOBET', 'estatus' => 1],
            ['terminal' => '00300', 'sistema' => 'LOTOBET', 'estatus' => 1],
            ['terminal' => '00400', 'sistema' => 'LOTENET', 'estatus' => 1],
        ]);
        $this->mock(LotobetSessionService::class)
            ->shouldReceive('getVentasProducto')
            ->once()
            ->andReturn(['Content' => [
                ['agencia_id' => '100', 'tipo' => 'Recarga', 'monto' => 10],
                ['agencia_id' => '200', 'tipo' => 'Tradicional', 'monto' => 5],
                ['agencia_id' => '200', 'tipo' => 'No Tradicional', 'monto' => 3],
                ['agencia_id' => '400', 'tipo' => 'Tradicional', 'monto' => 9],
            ]]);

        $lectura = app(CapturarVentasHora::class)->capturar(CarbonImmutable::parse('2026-09-29 14:05:00'));

        $this->assertSame(3, $lectura->terminales_evaluadas);
        $this->assertSame(1, $lectura->terminales_con_venta);
        $this->assertSame(1, $lectura->terminales_categoria['recargas']);
    }

    public function test_capture_keeps_only_requested_day_and_rejects_rows_from_another_date(): void
    {
        BiVentaHora::factory()->create(['fecha' => '2026-09-28', 'hora' => 14]);

        $lotobet = $this->mock(LotobetSessionService::class);
        $lotobet->shouldReceive('getVentasProducto')->twice()->with('2026-09-29')->andReturn(
            ['Content' => [['fecha' => '2026-09-28', 'producto_id' => '1', 'monto' => 10]]],
            ['Content' => [['fecha' => '2026-09-29', 'producto_id' => '1', 'tipo' => 'Tradicional', 'monto' => 20]]],
        );

        $capturador = app(CapturarVentasHora::class);

        try {
            $capturador->capturar(CarbonImmutable::parse('2026-09-29 15:05:00'));
            $this->fail('La fecha diferente debió rechazarse.');
        } catch (RuntimeException $exception) {
            $this->assertSame(1, BiVentaHora::query()->count());
        }

        $capturador->capturar(CarbonImmutable::parse('2026-09-29 15:05:00'));

        $this->assertSame(1, BiVentaHora::query()->count());
        $this->assertSame('2026-09-29', BiVentaHora::query()->first()->fecha->toDateString());
    }

    public function test_command_does_not_call_api_outside_capture_hours(): void
    {
        $this->mock(LotobetSessionService::class)
            ->shouldNotReceive('getVentasProducto');

        foreach (['2026-09-30 05:59:00', '2026-09-30 22:01:00'] as $momento) {
            CarbonImmutable::setTestNow($momento);
            $this->artisan('bi:capturar-ventas-hora')->assertSuccessful();
        }

        $this->assertSame(0, BiVentaHora::query()->count());
    }

    public function test_command_captures_at_the_final_scheduled_hour(): void
    {
        CarbonImmutable::setTestNow('2026-09-30 22:00:00');

        $this->mock(LotobetSessionService::class)
            ->shouldReceive('getVentasProducto')
            ->once()
            ->with('2026-09-30')
            ->andReturn(['Content' => []]);

        $this->artisan('bi:capturar-ventas-hora')->assertSuccessful();

        $this->assertSame(22, BiVentaHora::query()->firstOrFail()->hora);
    }

    public function test_command_skips_an_existing_hour_unless_forced(): void
    {
        CarbonImmutable::setTestNow('2026-09-30 08:05:00');
        BiVentaHora::factory()->create(['fecha' => '2026-09-30', 'hora' => 8]);

        $this->mock(LotobetSessionService::class)
            ->shouldReceive('getVentasProducto')
            ->once()
            ->with('2026-09-30')
            ->andReturn(['Content' => []]);

        $this->artisan('bi:capturar-ventas-hora')->assertSuccessful();
        $this->artisan('bi:capturar-ventas-hora', ['--force' => true])->assertSuccessful();

        $this->assertSame(1, BiVentaHora::query()->count());
        $this->assertSame('2026-09-30 08:05:00', BiVentaHora::query()->firstOrFail()->capturado_en->toDateTimeString());
    }

    public function test_command_saves_a_large_lotobet_response(): void
    {
        CarbonImmutable::setTestNow('2026-09-30 14:05:00');

        $this->mock(LotobetSessionService::class)
            ->shouldReceive('getVentasProducto')
            ->once()
            ->with('2026-09-30')
            ->andReturn(['Content' => array_fill(0, 65001, [
                'fecha' => '2026-09-30',
                'producto_id' => '1',
                'agencia_id' => '100',
                'tipo' => 'Tradicional',
                'monto' => 1,
            ])]);

        $this->artisan('bi:capturar-ventas-hora')->assertSuccessful();

        $lectura = BiVentaHora::query()->firstOrFail();
        $this->assertSame(65001, $lectura->registros);
        $this->assertSame('65001.00', $lectura->tradicional_acumulado);
        $this->assertSame(14, $lectura->hora);
    }
}
