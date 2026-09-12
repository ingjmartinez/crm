<?php

namespace Tests\Feature;

use App\Services\Gerencia\VentasEnVivoService;
use App\Services\Lotobet\LotobetSessionService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GerenciaVentasEnVivoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('vt_usuarios_bet');
        Schema::dropIfExists('vt_usuarios_net');
        Schema::dropIfExists('agencias');
        Schema::dropIfExists('catalogo_juegos');

        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('agencia')->nullable();
            $table->string('terminal');
            $table->string('nombre_agencia')->nullable();
            $table->string('sistema')->nullable();
            $table->string('empresa')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('ruta')->nullable();
            $table->boolean('estatus')->default(true);
            $table->timestamps();
        });

        foreach (['vt_usuarios_bet', 'vt_usuarios_net'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id('vt_usuario_id');
                $table->string('agencia_id');
                $table->string('tipo')->nullable();
                $table->string('producto_id')->nullable();
                $table->string('descripcion')->nullable();
                $table->decimal('monto', 14, 2);
                $table->date('fecha');
            });
        }

        Schema::create('catalogo_juegos', function (Blueprint $table): void {
            $table->id();
            $table->string('producto_id')->nullable();
            $table->string('tipo')->nullable();
            $table->string('descripcion')->nullable();
        });
    }

    public function test_report_is_published_in_gerencia_module(): void
    {
        $item = collect(config('module_hubs.gerencia.items'))->firstWhere('nombre', 'Ventas en Vivo');

        $this->assertNotNull($item);
        $this->assertSame('/gerencia/ventas-en-vivo', $item['url']);
        $this->assertTrue(Route::has('gerencia.ventas-en-vivo'));
        $this->assertTrue(view()->exists('gerencia.ventas-en-vivo'));
    }

    public function test_service_generates_kpis_and_top10_rankings(): void
    {
        DB::table('agencias')->insert([
            [
                'terminal' => '00123',
                'agencia' => 'A-123',
                'nombre_agencia' => 'Agencia Centro',
                'sistema' => 'Lotobet',
                'empresa' => 'Empresa Uno',
                'ciudad' => 'Santo Domingo',
                'ruta' => 'Ruta 1',
                'estatus' => 1,
            ],
            [
                'terminal' => '00999',
                'agencia' => 'A-999',
                'nombre_agencia' => 'Agencia Norte',
                'sistema' => 'Lotonet',
                'empresa' => 'Empresa Dos',
                'ciudad' => 'Santiago',
                'ruta' => 'Ruta 2',
                'estatus' => 1,
            ],
        ]);

        $this->mock(LotobetSessionService::class, function ($mock): void {
            $mock->shouldReceive('getVentasProducto')
                ->once()
                ->with('2026-09-10')
                ->andReturn([
                    'Content' => [
                        ['agencia_id' => '123', 'tipo' => 'Tradicional', 'producto_id' => 'P01', 'descripcion' => 'Quiniela', 'monto' => 1000],
                        ['agencia_id' => '123', 'tipo' => 'No Tradicional', 'producto_id' => 'P02', 'descripcion' => 'Loto Real', 'monto' => 500],
                        ['agencia_id' => '123', 'tipo' => 'Recargas', 'producto_id' => 'P03', 'descripcion' => 'Recargas', 'monto' => 200],
                    ],
                ]);
        });

        DB::table('vt_usuarios_net')->insert([
            ['agencia_id' => '999', 'tipo' => 'no_tradicional', 'producto_id' => 'P04', 'descripcion' => 'Tripleta', 'monto' => 800, 'fecha' => '2026-09-10'],
        ]);

        $servicio = app(VentasEnVivoService::class);
        $reporte = $servicio->generar(Carbon::parse('2026-09-10'), [
            'sistema' => 'todos',
            'tipo_producto' => 'todos',
            'empresa' => null,
            'ciudad' => null,
            'ruta' => null,
            'buscar' => '',
        ]);

        $this->assertSame(2500.0, (float) $reporte['resumen']['total_ventas']);
        $this->assertSame(1000.0, (float) $reporte['resumen']['total_tradicional']);
        $this->assertSame(1300.0, (float) $reporte['resumen']['total_no_tradicional']);
        $this->assertSame('00123', $reporte['top_terminales'][0]['terminal']);
        $this->assertSame('00999', $reporte['peores_agencias'][0]['terminal']);
        $this->assertSame('Quiniela', $reporte['top_productos'][0]['producto']);
    }

    public function test_service_applies_empresa_and_tipo_filters(): void
    {
        DB::table('agencias')->insert([
            [
                'terminal' => '00123',
                'agencia' => 'A-123',
                'nombre_agencia' => 'Agencia Centro',
                'sistema' => 'Lotobet',
                'empresa' => 'Empresa Uno',
                'ciudad' => 'Santo Domingo',
                'ruta' => 'Ruta 1',
                'estatus' => 1,
            ],
            [
                'terminal' => '00456',
                'agencia' => 'A-456',
                'nombre_agencia' => 'Agencia Este',
                'sistema' => 'Lotobet',
                'empresa' => 'Empresa Dos',
                'ciudad' => 'La Romana',
                'ruta' => 'Ruta 3',
                'estatus' => 1,
            ],
        ]);

        $this->mock(LotobetSessionService::class, function ($mock): void {
            $mock->shouldReceive('getVentasProducto')
                ->once()
                ->with('2026-09-10')
                ->andReturn([
                    'Content' => [
                        ['agencia_id' => '123', 'tipo' => 'No Tradicional', 'producto_id' => 'P02', 'descripcion' => 'Loto Real', 'monto' => 500],
                        ['agencia_id' => '456', 'tipo' => 'No Tradicional', 'producto_id' => 'P02', 'descripcion' => 'Loto Real', 'monto' => 900],
                        ['agencia_id' => '123', 'tipo' => 'Tradicional', 'producto_id' => 'P01', 'descripcion' => 'Quiniela', 'monto' => 1000],
                    ],
                ]);
        });

        $servicio = app(VentasEnVivoService::class);
        $reporte = $servicio->generar(Carbon::parse('2026-09-10'), [
            'sistema' => 'lotobet',
            'tipo_producto' => 'no_tradicional',
            'empresa' => 'Empresa Uno',
            'ciudad' => null,
            'ruta' => null,
            'buscar' => '',
        ]);

        $this->assertSame(500.0, (float) $reporte['resumen']['total_ventas']);
        $this->assertSame(500.0, (float) $reporte['resumen']['total_no_tradicional']);
        $this->assertSame('00123', $reporte['top_terminales'][0]['terminal']);
        $this->assertCount(1, $reporte['top_terminales']);
    }

    public function test_service_classifies_without_tipo_using_descripcion_catalog(): void
    {
        DB::table('agencias')->insert([
            [
                'terminal' => '00123',
                'agencia' => 'A-123',
                'nombre_agencia' => 'Agencia Centro',
                'sistema' => 'Lotobet',
                'empresa' => 'Empresa Uno',
                'ciudad' => 'Santo Domingo',
                'ruta' => 'Ruta 1',
                'estatus' => 1,
            ],
        ]);

        DB::table('catalogo_juegos')->insert([
            ['producto_id' => '9001', 'tipo' => 'Tradicional', 'descripcion' => 'ANGUILLA 8AM QUINIELA'],
            ['producto_id' => '9002', 'tipo' => 'No Tradicional', 'descripcion' => 'CHANCE EXPRESS CHANCE EXPRESS'],
        ]);

        $this->mock(LotobetSessionService::class, function ($mock): void {
            $mock->shouldReceive('getVentasProducto')
                ->once()
                ->with('2026-09-11')
                ->andReturn([
                    'Content' => [
                        ['agencia_id' => '123', 'producto_id' => '9001', 'descripcion' => 'ANGUILLA 8AM QUINIELA', 'monto' => 600],
                        ['agencia_id' => '123', 'producto_id' => '9002', 'descripcion' => 'CHANCE EXPRESS CHANCE EXPRESS', 'monto' => 400],
                    ],
                ]);
        });

        $servicio = app(VentasEnVivoService::class);
        $reporte = $servicio->generar(Carbon::parse('2026-09-11'), [
            'sistema' => 'lotobet',
            'tipo_producto' => 'todos',
            'empresa' => null,
            'ciudad' => null,
            'ruta' => null,
            'buscar' => '',
        ]);

        $this->assertSame(1000.0, (float) $reporte['resumen']['total_ventas']);
        $this->assertSame(600.0, (float) $reporte['resumen']['total_tradicional']);
        $this->assertSame(400.0, (float) $reporte['resumen']['total_no_tradicional']);
    }
}
