<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureViewPermission;
use App\Http\Middleware\ForcePasswordChange;
use App\Models\User;
use App\Services\RentabilidadAgenciaV7Service;
use App\ViewPermissionCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RentabilidadAgenciaV7Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([EnsureViewPermission::class, ForcePasswordChange::class]);
        Http::preventStrayRequests();
        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal');
            $table->string('sistema')->nullable();
            $table->string('nombre_agencia')->nullable();
            $table->string('agencia')->nullable();
            $table->string('empresa')->nullable();
            $table->string('ruta')->nullable();
            $table->integer('estatus')->default(1);
        });
        foreach (['vt_usuarios_bet', 'vt_usuarios_net', 'premios_bet', 'premios_net', 'pagos_misma_empresa_bet', 'pagos_porotra_empresa_bet', 'pagos_misma_empresa_net', 'pagos_porotra_empresa_net', 'pagos_aotra_empresa_bet', 'pagos_aotra_empresa_net'] as $tabla) {
            Schema::create($tabla, function (Blueprint $table): void {
                $table->id();
                $table->string('agencia_id');
                $table->string('pagado_agencia_id')->nullable();
                $table->date('fecha');
                $table->decimal('monto', 15, 2);
                $table->string('tipo')->nullable();
                $table->string('producto_id')->nullable();
            });
        }
        Schema::create('catalogo_juegos', function (Blueprint $table): void {
            $table->id();
            $table->string('producto_id');
            $table->string('tipo');
        });
        Schema::create('cuentas_contables', function (Blueprint $table): void {
            $table->id();
            $table->string('cuenta');
            $table->string('descripcion')->nullable();
            $table->string('tipo');
        });
        Schema::create('entradas_diario', function (Blueprint $table): void {
            $table->id();
            $table->string('id_viejo');
            $table->date('fecha');
            $table->string('cuenta');
            $table->decimal('debito', 15, 2);
            $table->decimal('credito', 15, 2);
        });
        Schema::create('rentabilidad_agencia_r20', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal');
            $table->string('mes');
            $table->decimal('monto', 15, 2);
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();
        });
        Schema::create('clasificacion_cuentas', function (Blueprint $table): void {
            $table->id();
            $table->string('cuenta');
            $table->string('clasificacion')->nullable();
            $table->boolean('activo');
            $table->timestamps();
        });
        config(['clasificacion_cuentas.cuentas' => ['5001' => 'FIJO', '6001' => 'VARIABLE']]);

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
        });
        Gate::before(fn (): bool => false);
        $this->usuario(true);
    }

    private function usuario(bool $permitido): void
    {
        $usuario = \Mockery::mock(User::class)->makePartial();
        $usuario->id = 1;
        $usuario->name = 'Prueba';
        $usuario->email = 'prueba@example.test';
        $usuario->shouldReceive('can')->andReturn($permitido);
        $usuario->shouldReceive('hasRole')->andReturnFalse();
        $usuario->setRelation('viewPermissions', collect());
        $this->actingAs($usuario);
    }

    private function datos(): void
    {
        DB::table('agencias')->insert([
            ['terminal' => '1001', 'empresa' => 'Grupo Joselito', 'sistema' => 'LOTOBET', 'nombre_agencia' => 'Agencia A'],
            ['terminal' => '1002', 'empresa' => 'Negosur', 'sistema' => 'LOTONET', 'nombre_agencia' => 'Agencia B'],
            ['terminal' => '1003', 'empresa' => 'Grupo Joselito', 'sistema' => 'MIXTO', 'nombre_agencia' => 'Agencia C'],
        ]);
        DB::table('catalogo_juegos')->insert([['producto_id' => '1', 'tipo' => 'Tradicional'], ['producto_id' => '2', 'tipo' => 'No Tradicional']]);
        foreach ([['1001', '2026-01-01', 100], ['1001', '2026-02-01', 100], ['1001', '2026-04-01', 1000], ['1002', '2026-01-01', 999], ['1003', '2026-01-01', 50]] as [$terminal, $fecha, $monto]) {
            DB::table('vt_usuarios_bet')->insert(['agencia_id' => $terminal, 'fecha' => $fecha, 'monto' => $monto, 'tipo' => 'Tradicional']);
        }
        DB::table('vt_usuarios_net')->insert([['agencia_id' => '1002', 'fecha' => '2026-01-01', 'monto' => 200, 'tipo' => 'Tradicional'], ['agencia_id' => '1003', 'fecha' => '2026-01-01', 'monto' => 70, 'tipo' => 'Tradicional']]);
        DB::table('premios_bet')->insert(['agencia_id' => '1001', 'fecha' => '2026-01-01', 'monto' => 90, 'producto_id' => '1']);
        foreach ([['1001', '1001', '2026-01-01', 20], ['1001', '999', '2026-01-01', 30], ['999', '1001', '2026-01-01', 60], ['1001', null, '2026-01-01', 900], ['1001', '1001', '2026-02-01', 100]] as [$pagadora, $origen, $fecha, $monto]) {
            DB::table('pagos_misma_empresa_bet')->insert(['agencia_id' => $pagadora, 'pagado_agencia_id' => $origen, 'fecha' => $fecha, 'monto' => $monto, 'producto_id' => '1']);
        }
        DB::table('pagos_aotra_empresa_bet')->insert(['agencia_id' => '1001', 'fecha' => '2026-01-01', 'monto' => 5, 'producto_id' => '1']);
        DB::table('pagos_porotra_empresa_bet')->insert(['agencia_id' => '1001', 'fecha' => '2026-01-01', 'monto' => 80, 'producto_id' => '1']);
        DB::table('cuentas_contables')->insert([['cuenta' => '5001', 'tipo' => 'Costo'], ['cuenta' => '6001', 'tipo' => 'Gasto'], ['cuenta' => '9999', 'tipo' => 'Gasto']]);
        foreach ([['2026-01-01', '5001', 10, 0], ['2026-01-01', '6001', 40, 5], ['2026-02-01', '6001', 5, 0], ['2026-03-01', '6001', 7, 0], ['2026-01-01', '9999', 999, 0]] as [$fecha, $cuenta, $debito, $credito]) {
            DB::table('entradas_diario')->insert(['id_viejo' => '1001', 'fecha' => $fecha, 'cuenta' => $cuenta, 'debito' => $debito, 'credito' => $credito]);
        }
        DB::table('rentabilidad_agencia_r20')->insert(['terminal' => '*', 'mes' => 'GLOBAL', 'monto' => 10]);
    }

    private function filtros(): array
    {
        return ['anio' => 2026, 'mes_inicio' => 1, 'mes_fin' => 3, 'terminal' => '1001', 'empresa' => 'Grupo Joselito', 'alcance' => 'terminal'];
    }

    public function test_calcula_con_premios_generados_y_conserva_los_gastos_contables(): void
    {
        $this->datos();
        $response = $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', $this->filtros()))->assertOk();
        $response->assertJsonPath('ventas', 200)->assertJsonPath('premios_sacados', 90)
            ->assertJsonPath('utilidad', 110)->assertJsonPath('gastos', 77)->assertJsonPath('balance_general', 33)
            ->assertJsonPath('meses.0.utilidad', 10)->assertJsonPath('meses.0.balance_general', -45)
            ->assertJsonPath('meses.1.balance_general', 85)->assertJsonPath('meses.2.balance_general', -7)
            ->assertJsonPath('meses.2.r20', 0)
            ->assertJsonPath('clasificacion.nombre', 'En evaluación')
            ->assertJsonPath('clasificacion.meses_positivos', 1)->assertJsonPath('clasificacion.meses_con_ventas', 2)
            ->assertJsonCount(3, 'meses')->assertJsonMissingPath('premios_propios')->assertJsonMissingPath('clasificacion_neta');
        $original = app(\App\Services\RentabilidadAgenciaV6Service::class)->reporte($this->filtros())['filas']->first();
        foreach ($response->json('meses') as $indice => $mes) {
            $this->assertSame($original['periodos'][$indice]['costos'], (float) $mes['costos']);
            $this->assertSame($original['periodos'][$indice]['gastos_contables'], (float) $mes['gastos_contables']);
        }
        Http::assertNothingSent();
    }

    public function test_agrega_una_consulta_por_terminal_y_mes_sin_leer_pagos(): void
    {
        $this->datos();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $resultado = app(RentabilidadAgenciaV7Service::class)->reporte(['anio' => 2026, 'mes_inicio' => 1, 'mes_fin' => 3]);
        $consultas = collect(DB::getQueryLog())->filter(fn (array $consulta): bool => str_contains(strtolower($consulta['query']), 'union all'));
        DB::disableQueryLog();
        $this->assertCount(1, $consultas);
        $this->assertStringNotContainsString('pagos_', $consultas->first()['query']);
        $this->assertStringContainsString('group by "terminal", "periodo"', $consultas->first()['query']);
        $this->assertSame(['ventas' => 520.0, 'premios_sacados' => 90.0, 'utilidad' => 430.0, 'gastos' => 97.0, 'balance_general' => 333.0], $resultado['totales']);
        $this->assertSame(200.0, $resultado['filas']->firstWhere('terminal', '1002')['ventas']);
        $this->assertSame(120.0, $resultado['filas']->firstWhere('terminal', '1003')['ventas']);
        $this->assertSame(3, $resultado['resumen_clasificaciones']['total']);
        $categorias = collect($resultado['resumen_clasificaciones']['categorias'])->keyBy('nombre');
        $this->assertSame(0, $categorias['Rentable']['cantidad']);
        $this->assertSame(0, $categorias['Para rescatar']['cantidad']);
        $this->assertSame(3, $categorias['En evaluación']['cantidad']);
    }

    public function test_filtros_anuales_de_productos_empresa_y_terminal(): void
    {
        $this->datos();
        $filtros = $this->filtros();
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', [...$filtros, 'anio_completo' => 1]))->assertOk()->assertJsonCount(12, 'meses')->assertJsonPath('ventas', 1200)->assertJsonPath('gastos', 87)->assertJsonPath('balance_general', 1023)->assertJsonPath('clasificacion.nombre', 'En evaluación');
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', [...$filtros, 'productos_filtrados' => 1, 'productos' => ['Recarga']]))->assertOk()->assertJsonPath('ventas', 0)->assertJsonPath('premios_sacados', 0)->assertJsonPath('balance_general', -57)->assertJsonPath('clasificacion.nombre', 'Sin ventas');
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', [...$filtros, 'productos' => ['Tradicional']]))->assertOk()->assertJsonPath('premios_sacados', 90);
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', [...$filtros, 'empresa' => 'Negosur']))->assertNotFound();
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', [...$filtros, 'terminal' => '99999']))->assertNotFound();
    }

    public function test_filtra_por_ruta_y_empresa_y_conserva_la_seleccion(): void
    {
        $this->datos();
        DB::table('agencias')->whereIn('terminal', ['1001', '1002'])->update(['ruta' => ' Ruta Norte ']);
        DB::table('agencias')->where('terminal', '1003')->update(['ruta' => 'Ruta Sur']);
        DB::table('agencias')->insert(['terminal' => '9000', 'ruta' => 'Ruta inactiva', 'estatus' => 0]);
        $filtros = [...$this->filtros(), 'alcance' => 'todas', 'empresa' => '', 'ruta' => 'Ruta Norte'];

        $this->get(route('gerencia.rentabilidad-agencia-v7', $filtros))->assertOk()
            ->assertViewHas('rutas', fn ($rutas): bool => $rutas->all() === ['Ruta Norte', 'Ruta Sur'])
            ->assertViewHas('filtros', fn (array $datos): bool => $datos['ruta'] === 'Ruta Norte')
            ->assertViewHas('resultado', fn (array $resultado): bool => $resultado['filas']->pluck('terminal')->all() === ['1001', '1002']
                && $resultado['totales']['ventas'] === 400.0
                && $resultado['resumen_clasificaciones']['total'] === 2)
            ->assertSee('name="ruta"', false)
            ->assertSee('value="Ruta Norte" selected', false);

        $servicio = app(RentabilidadAgenciaV7Service::class);
        $resultado = $servicio->reporte([...$filtros, 'empresa' => 'Grupo Joselito']);
        $this->assertSame(['1001'], $resultado['filas']->pluck('terminal')->all());
        $this->assertSame(200.0, $resultado['totales']['ventas']);
        $this->assertSame(77.0, $resultado['totales']['gastos']);
        $this->assertCount(3, $servicio->reporte([...$filtros, 'ruta' => ''])['filas']);
        $this->assertCount(0, $servicio->reporte([...$filtros, 'ruta' => 'Inexistente'])['filas']);
    }

    public function test_detalles_y_cuentas_respetan_la_ruta_y_validan_su_formato(): void
    {
        $this->datos();
        DB::table('agencias')->where('terminal', '1001')->update(['ruta' => 'Ruta Norte']);
        $filtros = [...$this->filtros(), 'ruta' => 'Ruta Norte'];

        foreach (['resumen', 'detalle', 'cuentas'] as $accion) {
            $this->getJson(route('gerencia.rentabilidad-agencia-v7.'.$accion, $filtros))->assertOk();
            $this->getJson(route('gerencia.rentabilidad-agencia-v7.'.$accion, [...$filtros, 'ruta' => 'Ruta Sur']))->assertNotFound();
        }

        foreach ([['Ruta Norte'], str_repeat('a', 256)] as $ruta) {
            $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', [...$filtros, 'ruta' => $ruta]))
                ->assertUnprocessable()->assertJsonValidationErrors('ruta');
        }
    }

    public function test_selector_de_rutas_solo_incluye_rutas_activas_de_la_empresa_seleccionada(): void
    {
        $this->datos();
        DB::table('agencias')->where('terminal', '1001')->update(['empresa' => ' Grupo Joselito ', 'ruta' => ' Ruta Norte ']);
        DB::table('agencias')->where('terminal', '1002')->update(['ruta' => 'Ruta Sur']);
        DB::table('agencias')->where('terminal', '1003')->update(['ruta' => 'Ruta Compartida']);
        DB::table('agencias')->insert([
            ['terminal' => '1004', 'empresa' => 'Negosur', 'ruta' => 'Ruta Compartida', 'estatus' => 1],
            ['terminal' => '1005', 'empresa' => 'Grupo Joselito', 'ruta' => 'Ruta Inactiva', 'estatus' => 0],
            ['terminal' => '1006', 'empresa' => 'Grupo Joselito', 'ruta' => '  ', 'estatus' => 1],
        ]);
        foreach ([
            '' => ['Ruta Compartida', 'Ruta Norte', 'Ruta Sur'],
            'Grupo Joselito' => ['Ruta Compartida', 'Ruta Norte'],
            'Negosur' => ['Ruta Compartida', 'Ruta Sur'],
            'Sin rutas' => [],
        ] as $empresa => $esperadas) {
            $this->get(route('gerencia.rentabilidad-agencia-v7', ['empresa' => $empresa]))->assertOk()
                ->assertViewHas('rutas', fn ($rutas): bool => $rutas->all() === $esperadas)
                ->assertViewHas('rutasDisponibles', fn ($rutas): bool => $rutas->count() === 4);
        }
    }

    public function test_resumen_ejecutivo_separa_la_cache_por_ruta(): void
    {
        $this->datos();
        DB::table('agencias')->where('terminal', '1001')->update(['ruta' => 'Ruta Norte']);
        DB::table('agencias')->where('terminal', '1003')->update(['ruta' => 'Ruta Sur']);
        $filtros = [...$this->filtros(), 'alcance' => 'todas', 'ruta' => 'Ruta Norte'];
        $servicio = app(RentabilidadAgenciaV7Service::class);
        $servicio->guardarResumenEjecutivo($filtros, '1', $servicio->reporte($filtros));
        $norte = $servicio->resumenEjecutivo($filtros, '1');
        $sur = $servicio->resumenEjecutivo([...$filtros, 'ruta' => 'Ruta Sur'], '1');

        $this->assertSame(200.0, $norte['totales']['ventas']);
        $this->assertSame(120.0, $sur['totales']['ventas']);
        $this->assertSame(10.0, $sur['totales']['gastos']);
        $html = view('gerencia.rentabilidad-agencia-v7-pdf', [
            'filtros' => $filtros, 'resultado' => $norte, 'productos' => RentabilidadAgenciaV7Service::PRODUCTOS,
        ])->render();
        $this->assertStringContainsString('Ruta: Ruta Norte', $html);
    }

    public static function categoriasPorMesesPositivos(): array
    {
        return [[9, 'Rentable'], [7, 'Buena'], [6, 'Regular'], [5, 'Regular'], [4, 'Para rescatar'], [3, 'Para rescatar'], [2, 'Para rescatar'], [0, 'No rentable']];
    }

    #[DataProvider('categoriasPorMesesPositivos')]
    public function test_clasifica_el_balance_general_ignorando_meses_sin_ventas(int $positivos, string $categoria): void
    {
        $this->datos();
        DB::table('agencias')->insert(['terminal' => '9001', 'empresa' => 'Grupo Joselito', 'sistema' => 'LOTOBET', 'nombre_agencia' => 'Clasificacion']);
        foreach (range(1, 9) as $mes) {
            $fecha = sprintf('2026-%02d-01', $mes);
            DB::table('vt_usuarios_bet')->insert(['agencia_id' => '9001', 'fecha' => $fecha, 'monto' => 100]);
            DB::table('premios_bet')->insert(['agencia_id' => '9001', 'fecha' => $fecha, 'monto' => $mes <= $positivos ? 20 : 90]);
        }
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', [...$this->filtros(), 'terminal' => '9001', 'mes_fin' => 12]))->assertOk()
            ->assertJsonPath('clasificacion.nombre', $categoria)->assertJsonPath('clasificacion.meses_positivos', $positivos)
            ->assertJsonPath('clasificacion.meses_con_ventas', 9)->assertJsonPath('balance_general', $positivos * 70);
    }

    public function test_cache_ejecutiva_aisla_usuario_filtros_y_expira(): void
    {
        $this->datos();
        $servicio = app(RentabilidadAgenciaV7Service::class);
        $filtros = $this->filtros();
        $this->assertSame(33.0, $servicio->resumenEjecutivo($filtros, '1')['totales']['balance_general']);
        DB::table('premios_bet')->where('agencia_id', '1001')->update(['monto' => 900]);
        $this->assertSame(33.0, $servicio->resumenEjecutivo($filtros, '1')['totales']['balance_general']);
        $this->assertSame(-777.0, $servicio->resumenEjecutivo($filtros, '2')['totales']['balance_general']);
        $this->assertSame(0.0, $servicio->resumenEjecutivo([...$filtros, 'empresa' => 'Inexistente'], '1')['totales']['ventas']);
        $this->assertSame(0.0, $servicio->resumenEjecutivo([...$filtros, 'productos_filtrados' => 1, 'productos' => []], '1')['totales']['ventas']);
        $this->travel(11)->minutes();
        $this->assertSame(-777.0, $servicio->resumenEjecutivo($filtros, '1')['totales']['balance_general']);
        $this->travelBack();
    }

    public function test_cero_con_centavos_no_es_positivo_y_el_diario_concilia(): void
    {
        $this->datos();
        DB::table('rentabilidad_agencia_r20')->update(['monto' => 0.03]);
        DB::table('vt_usuarios_net')->where('agencia_id', '1002')->update(['monto' => 0.33]);
        DB::table('premios_net')->insert(['agencia_id' => '1002', 'fecha' => '2026-01-01', 'monto' => 0.3]);
        $filtros = [...$this->filtros(), 'terminal' => '1002', 'empresa' => 'Negosur', 'mes_fin' => 1];
        $servicio = app(RentabilidadAgenciaV7Service::class);
        $mensual = $servicio->reporte($filtros)['filas']->first();
        $diario = $servicio->reporte($filtros, true)['filas']->first();
        $this->assertSame(0.0, $mensual['balance_general']);
        $this->assertSame(0, $mensual['clasificacion']['meses_positivos']);
        $this->assertSame($mensual['balance_general'], $diario['balance_general']);
        $this->assertSame($mensual['clasificacion'], $diario['clasificacion']);
        $this->get(route('gerencia.rentabilidad-agencia-v7.detalle', $filtros))->assertOk()->assertSee('detalleTerminalRentabilidadV7')->assertSee('Balance general')->assertDontSee('Balance neto');
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.cuentas', $this->filtros()))->assertOk()->assertJsonPath('total', 57.06);
    }

    public function test_vista_columnas_y_permisos_iguales_a_v6(): void
    {
        $this->datos();
        $this->get(route('gerencia.rentabilidad-agencia-v7'))->assertOk()->assertSee('Año completo');
        $this->get(route('gerencia.rentabilidad-agencia-v7', $this->filtros()))->assertOk()
            ->assertSeeInOrder(['Terminal</th>', 'Agencia (nombre)</th>', 'Ventas</th>', 'Premios sacados</th>', 'Utilidad</th>', 'Gastos</th>', 'Balance general</th>', 'Clasificación</th>', 'Acción</th>'], false)
            ->assertSee('categoriaTablaV7')->assertDontSee('clasificacionTablaV7')->assertDontSee('Premios pagados propios');
        $this->get(route('gerencia.rentabilidad-agencia-v7', $this->filtros()))->assertOk()->assertSee('value="En evaluación"', false);
        foreach (['', '.resumen', '.detalle', '.cuentas', '.informe-ejecutivo'] as $sufijo) {
            $this->assertSame(Route::getRoutes()->getByName('gerencia.rentabilidad-agencia-v6'.$sufijo)->gatherMiddleware(), Route::getRoutes()->getByName('gerencia.rentabilidad-agencia-v7'.$sufijo)->gatherMiddleware());
        }
        $this->assertSame('gerencia.rentabilidad_de_agencia.view', app(ViewPermissionCatalog::class)->items()->firstWhere('path', '/gerencia/rentabilidad-agencia-v7')['permission']);
        $this->withMiddleware(EnsureViewPermission::class);
        $this->usuario(false);
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', $this->filtros()))->assertForbidden();
    }

    public function test_valida_rangos_y_parametros_de_los_modales(): void
    {
        foreach ([['mes_inicio' => 4], ['anio' => 10000], ['mes_fin' => 0], ['terminal' => null], ['productos' => ['Invalido']]] as $cambio) {
            $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', [...$this->filtros(), ...$cambio]))->assertUnprocessable();
        }
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', ['terminal' => '1001']))->assertUnprocessable();
    }

    public function test_pdf_reutiliza_consulta_y_su_cache_no_se_mezcla_con_v6(): void
    {
        $this->datos();
        $this->get(route('gerencia.rentabilidad-agencia-v7', $this->filtros()))->assertOk();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->get(route('gerencia.rentabilidad-agencia-v7.informe-ejecutivo', $this->filtros()))->assertOk();
        $consultas = collect(DB::getQueryLog())->filter(fn (array $consulta): bool => str_contains(strtolower($consulta['query']), 'union all'));
        DB::disableQueryLog();
        $this->assertCount(0, $consultas);
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('rentabilidad_agencia_v7.pdf', $response->headers->get('Content-Disposition'));
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()));
        $resultado = app(RentabilidadAgenciaV7Service::class)->resumenEjecutivo($this->filtros(), '1');
        $this->assertArrayNotHasKey('filas', $resultado);
        $this->assertSame(33.0, $resultado['totales']['balance_general']);
        $v6 = app(\App\Services\RentabilidadAgenciaV6Service::class)->resumenEjecutivo($this->filtros(), '1');
        $this->assertSame(-42.0, $v6['totales']['balance_neto']);
        $this->assertArrayNotHasKey('balance_general', $v6['totales']);
        $filtros = $this->filtros();
        $productos = RentabilidadAgenciaV7Service::PRODUCTOS;
        $html = view('gerencia.rentabilidad-agencia-v7-pdf', compact('resultado', 'filtros', 'productos'))->render();
        $this->assertStringContainsString('Balance general', $html);
        $this->assertStringContainsString('<h1>Informe ejecutivo de rentabilidad de agencia</h1>', $html);
        $this->assertStringContainsString('RD$ 33.00', $html);
        $this->assertStringContainsString('Se requieren 4 meses con ventas', $html);
        $this->assertStringNotContainsString('Pagados propios', $html);
        $this->assertStringNotContainsString('Clasificación neta', $html);
    }

    public function test_pdf_conserva_las_siete_clasificaciones_y_totales_de_la_pantalla(): void
    {
        foreach ([4, 3, 2, 0, 1, null, 6] as $indice => $positivos) {
            $terminal = (string) (8000 + $indice);
            DB::table('agencias')->insert([
                'terminal' => $terminal, 'empresa' => 'Prueba PDF', 'ruta' => 'Ruta PDF',
                'sistema' => 'LOTOBET', 'nombre_agencia' => 'Agencia '.$terminal,
            ]);
            $meses = $indice === 6 ? 9 : ($indice === 4 ? 1 : ($positivos === null ? 0 : 4));
            for ($mes = 1; $mes <= $meses; $mes++) {
                $fecha = sprintf('2026-%02d-01', $mes);
                DB::table('vt_usuarios_bet')->insert(['agencia_id' => $terminal, 'fecha' => $fecha, 'monto' => 1000, 'tipo' => 'Tradicional']);
                DB::table('premios_bet')->insert(['agencia_id' => $terminal, 'fecha' => $fecha, 'monto' => $mes <= $positivos ? 900 : ($indice === 3 ? 21000 : 1100)]);
            }
        }
        $filtros = [...$this->filtros(), 'empresa' => 'Prueba PDF', 'ruta' => 'Ruta PDF', 'alcance' => 'todas', 'mes_fin' => 9];
        $pagina = $this->get(route('gerencia.rentabilidad-agencia-v7', $filtros))->assertOk()->assertSee('value="Regular"', false);
        $pantalla = $pagina->viewData('resultado');
        $this->assertSame([1, 1, 1, 1, 1, 1, 1], array_column($pantalla['resumen_clasificaciones']['categorias'], 'cantidad'));
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', [...$filtros, 'terminal' => '8006']))->assertOk()
            ->assertJsonPath('clasificacion.nombre', 'Regular')->assertJsonPath('clasificacion.color', 'info');
        $datosPdf = null;
        app('view')->composer('gerencia.rentabilidad-agencia-v7-pdf', function (\Illuminate\View\View $vista) use (&$datosPdf): void {
            $datosPdf = $vista->getData()['resultado'];
        });

        $pdf = $this->get(route('gerencia.rentabilidad-agencia-v7.informe-ejecutivo', [...$filtros, 'informe_id' => $pagina->viewData('informeId')]))->assertOk();
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf->getContent()));

        $this->assertNotNull($datosPdf);
        $this->assertSame($pantalla['resumen_clasificaciones'], $datosPdf['resumen_clasificaciones']);
        $this->assertSame($pantalla['totales'], $datosPdf['totales']);
    }

    public function test_pdf_conserva_la_consulta_original_aunque_otra_pestana_actualice_las_clasificaciones(): void
    {
        $this->datos();
        $filtros = [...$this->filtros(), 'mes_fin' => 4];
        $primeraPagina = $this->get(route('gerencia.rentabilidad-agencia-v7', $filtros))->assertOk();
        $original = $primeraPagina->viewData('resultado');
        $informeId = $primeraPagina->viewData('informeId');
        $primeraPagina->assertSee('informe_id='.$informeId, false);
        DB::table('vt_usuarios_bet')->insert(['agencia_id' => '1001', 'fecha' => '2026-03-01', 'monto' => 100, 'tipo' => 'Tradicional']);
        $actual = $this->get(route('gerencia.rentabilidad-agencia-v7', $filtros))->assertOk()->viewData('resultado');
        $this->assertNotSame($original['resumen_clasificaciones'], $actual['resumen_clasificaciones']);
        config(['rentabilidad.min_meses' => 5]);
        $datosPdf = null;
        app('view')->composer('gerencia.rentabilidad-agencia-v7-pdf', function (\Illuminate\View\View $vista) use (&$datosPdf): void {
            $datosPdf = $vista->getData()['resultado'];
        });

        $respuesta = $this->get(route('gerencia.rentabilidad-agencia-v7.informe-ejecutivo', [...$filtros, 'informe_id' => $informeId]))->assertOk();
        $this->assertTrue($respuesta->headers->hasCacheControlDirective('no-store'));

        $this->assertSame($original['resumen_clasificaciones'], $datosPdf['resumen_clasificaciones']);
        $this->assertSame($original['totales'], $datosPdf['totales']);
        $this->assertSame(4, $datosPdf['configuracion_clasificacion']['min_meses']);
    }

    public function test_pdf_caducado_no_recalcula_otras_clasificaciones_silenciosamente(): void
    {
        $this->datos();
        $pagina = $this->get(route('gerencia.rentabilidad-agencia-v7', $this->filtros()))->assertOk();
        $filtros = [...$this->filtros(), 'informe_id' => $pagina->viewData('informeId')];
        $this->travel(11)->minutes();

        $this->getJson(route('gerencia.rentabilidad-agencia-v7.informe-ejecutivo', $filtros))
            ->assertUnprocessable()->assertJsonValidationErrors('informe_id');

        $this->travelBack();
    }

    public function test_enlace_pdf_caducado_no_redirige_al_mismo_enlace(): void
    {
        $this->datos();
        $pagina = $this->get(route('gerencia.rentabilidad-agencia-v7', $this->filtros()))->assertOk();
        $filtros = [...$this->filtros(), 'informe_id' => $pagina->viewData('informeId'), 'personalizado' => 1, 'ruta_impresion' => 'Ruta Norte', 'salida' => 'pdf'];
        $url = route('gerencia.rentabilidad-agencia-v7.informe-ejecutivo', $filtros);
        $this->travel(11)->minutes();

        $respuesta = $this->from($url)->get($url)->assertRedirect()->assertSessionHasErrors('informe_id');
        $destino = $respuesta->headers->get('Location');
        $this->assertStringStartsWith(route('gerencia.rentabilidad-agencia-v7').'?', $destino);
        parse_str(parse_url($destino, PHP_URL_QUERY), $parametros);
        $this->assertEquals($this->filtros(), $parametros);

        $this->travelBack();
    }

    public function test_referencia_pdf_se_aisla_por_usuario_y_filtros(): void
    {
        $this->datos();
        $pagina = $this->get(route('gerencia.rentabilidad-agencia-v7', $this->filtros()))->assertOk();
        $filtros = [...$this->filtros(), 'informe_id' => $pagina->viewData('informeId')];
        foreach ([['empresa' => 'Negosur'], ['ruta' => 'Otra ruta'], ['informe_id' => 'invalido']] as $cambio) {
            $this->getJson(route('gerencia.rentabilidad-agencia-v7.informe-ejecutivo', [...$filtros, ...$cambio]))
                ->assertUnprocessable()->assertJsonValidationErrors('informe_id');
        }
        auth()->user()->id = 2;
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.informe-ejecutivo', $filtros))
            ->assertUnprocessable()->assertJsonValidationErrors('informe_id');
    }

    public function test_perdida_menor_a_49000_se_rescata_en_resumen_pantalla_y_pdf(): void
    {
        $this->datos();
        DB::table('agencias')->insert(['terminal' => '9010', 'empresa' => 'Grupo Joselito', 'sistema' => 'LOTOBET']);
        foreach ([-12000, -12000, -12000, -12999.99] as $indice => $balance) {
            $fecha = sprintf('2026-%02d-01', $indice + 1);
            DB::table('vt_usuarios_bet')->insert(['agencia_id' => '9010', 'fecha' => $fecha, 'monto' => 100000, 'tipo' => 'Tradicional']);
            DB::table('premios_bet')->insert(['agencia_id' => '9010', 'fecha' => $fecha, 'monto' => 100000 - 10 - $balance]);
        }
        $filtros = [...$this->filtros(), 'terminal' => '9010', 'mes_fin' => 4];
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', $filtros))->assertOk()
            ->assertJsonPath('balance_general', -48999.99)
            ->assertJsonPath('clasificacion.nombre', 'Para rescatar')
            ->assertJsonPath('clasificacion.motivo_codigo', 'perdida_acumulada_limitada');
        $pagina = $this->get(route('gerencia.rentabilidad-agencia-v7', $filtros))->assertOk();
        $pantalla = $pagina->viewData('resultado');
        $datosPdf = null;
        app('view')->composer('gerencia.rentabilidad-agencia-v7-pdf', function (\Illuminate\View\View $vista) use (&$datosPdf): void {
            $datosPdf = $vista->getData()['resultado'];
        });
        $this->get(route('gerencia.rentabilidad-agencia-v7.informe-ejecutivo', [...$filtros, 'informe_id' => $pagina->viewData('informeId')]))->assertOk();
        $this->assertSame($pantalla['resumen_clasificaciones'], $datosPdf['resumen_clasificaciones']);
        $this->assertSame(1, collect($datosPdf['resumen_clasificaciones']['categorias'])->firstWhere('nombre', 'Para rescatar')['cantidad']);
        $this->assertSame(-48999.99, $datosPdf['totales']['balance_general']);
    }

    public function test_impresion_personalizada_solo_muestra_terminales_y_totales_de_la_ruta_consultada(): void
    {
        $this->datos();
        DB::table('agencias')->whereIn('terminal', ['1001', '1002'])->update(['ruta' => 'Ruta Norte']);
        DB::table('agencias')->where('terminal', '1003')->update(['ruta' => 'Ruta Sur']);
        $filtros = [...$this->filtros(), 'alcance' => 'todas'];
        $pagina = $this->get(route('gerencia.rentabilidad-agencia-v7', $filtros))->assertOk()
            ->assertSee('Impresión personalizada')->assertSee('name="ruta_impresion"', false)
            ->assertSee('name="salida" value="imprimir"', false)->assertSee('name="salida" value="pdf"', false);
        DB::table('vt_usuarios_bet')->where('agencia_id', '1001')->update(['monto' => 999999]);
        $opciones = [...$filtros, 'informe_id' => $pagina->viewData('informeId'), 'personalizado' => 1, 'ruta_impresion' => 'Ruta Norte', 'salida' => 'imprimir'];
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('gerencia.rentabilidad-agencia-v7.informe-ejecutivo', $opciones))->assertOk()
            ->assertViewHas('resultado', fn (array $resultado): bool => array_column($resultado['terminales'], 'terminal') === ['1001']
                && $resultado['totales']['ventas'] === 200.0 && $resultado['totales']['gastos'] === 77.0
                && $resultado['resumen_clasificaciones']['total'] === 1)
            ->assertSee('Terminal 1001')->assertDontSee('Terminal 1002')->assertDontSee('Terminal 1003')
            ->assertSee('<th class="observaciones">Observación</th>', false)->assertDontSee('<th>Terminal</th>', false)->assertDontSee('<th>Empresa</th>', false)
            ->assertSee('Totales de la ruta')->assertSee('window.print()', false)
            ->assertDontSee('Terminales evaluadas en el período')->assertDontSee('Resultados del período');
        $consultas = collect(DB::getQueryLog())->filter(fn (array $consulta): bool => str_contains(strtolower($consulta['query']), 'union all'));
        DB::disableQueryLog();
        $this->assertCount(0, $consultas);
    }

    public function test_pdf_personalizado_separa_rutas_y_conserva_terminales_sin_ruta(): void
    {
        $this->datos();
        DB::table('agencias')->where('terminal', '1001')->update(['ruta' => 'Ruta Norte']);
        DB::table('agencias')->where('terminal', '1003')->update(['ruta' => 'Ruta Sur']);
        DB::table('agencias')->insert(['terminal' => '1004', 'nombre_agencia' => 'Sin ruta', 'empresa' => 'Grupo Joselito', 'sistema' => 'LOTOBET']);
        $filtros = [...$this->filtros(), 'alcance' => 'todas'];
        $pagina = $this->get(route('gerencia.rentabilidad-agencia-v7', $filtros))->assertOk();
        $opciones = [...$filtros, 'informe_id' => $pagina->viewData('informeId'), 'personalizado' => 1, 'salida' => 'pdf'];
        $datosPdf = null;
        app('view')->composer('gerencia.rentabilidad-agencia-v7-pdf', function (\Illuminate\View\View $vista) use (&$datosPdf): void {
            $datosPdf = $vista->getData()['resultado'];
        });
        $pdf = $this->get(route('gerencia.rentabilidad-agencia-v7.informe-ejecutivo', $opciones))->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertSame(3, preg_match_all('/\/Type\s*\/Page\b/', $pdf->getContent()));
        $this->assertSame(['', 'Ruta Norte', 'Ruta Sur'], array_column($datosPdf['rutas_impresion'], 'ruta'));
        $this->assertSame(320.0, $datosPdf['totales']['ventas']);
        $this->assertSame(87.0, $datosPdf['totales']['gastos']);
        $porRuta = $this->get(route('gerencia.rentabilidad-agencia-v7.informe-ejecutivo', [...$opciones, 'ruta_impresion' => 'Ruta Norte']))->assertOk();
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $porRuta->getContent()));
        $this->assertSame(['Ruta Norte'], array_column($datosPdf['rutas_impresion'], 'ruta'));
        $this->assertSame(200.0, $datosPdf['totales']['ventas']);
    }

    public function test_impresion_personalizada_rechaza_opciones_invalidas_y_rutas_fuera_de_la_consulta(): void
    {
        $this->datos();
        $pagina = $this->get(route('gerencia.rentabilidad-agencia-v7', $this->filtros()))->assertOk();
        $opciones = [...$this->filtros(), 'informe_id' => $pagina->viewData('informeId'), 'personalizado' => 1, 'salida' => 'imprimir'];
        foreach ([
            ['informe_id' => null], ['salida' => 'excel'], ['personalizado' => 'invalido'],
            ['ruta_impresion' => ['Ruta Norte']], ['ruta_impresion' => str_repeat('a', 256)], ['ruta_impresion' => 'Fuera de consulta'],
        ] as $cambio) {
            $this->getJson(route('gerencia.rentabilidad-agencia-v7.informe-ejecutivo', [...$opciones, ...$cambio]))
                ->assertUnprocessable()->assertJsonValidationErrors(array_key_first($cambio));
        }
        $this->travel(11)->minutes();
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.informe-ejecutivo', $opciones))
            ->assertUnprocessable()->assertJsonValidationErrors('informe_id');
        $this->travelBack();
    }

    public function test_impresion_organiza_cada_ruta_por_categoria_y_balance_descendente_sin_cambiar_totales(): void
    {
        $categorias = collect(\App\Services\ClasificadorRentabilidadAgenciaV7::categorias())->keyBy('codigo');
        $terminales = collect([
            ['009', 'no_rentable', -50000, 'Ruta Norte'],
            ['005', 'regular', 5000, 'Ruta Norte'],
            ['004', 'regular', 10, 'Ruta Norte'],
            ['006', 'rentable', 20, 'Ruta Norte'],
            ['007', 'buena', 1000, 'Ruta Norte'],
            ['008', 'para_rescatar', -100, 'Ruta Norte'],
            ['002', 'en_evaluacion', 10000, 'Ruta Norte'],
            ['001', 'sin_ventas', 0, 'Ruta Norte'],
            ['003', 'regular', 5000, 'Ruta Norte'],
            ['010', 'regular', 300, 'Ruta Sur'],
        ])->map(fn (array $datos): array => [
            ...array_fill_keys(RentabilidadAgenciaV7Service::IMPORTES, 0.0),
            'terminal' => $datos[0], 'nombre' => 'Agencia '.$datos[0], 'empresa' => 'Prueba', 'ruta' => $datos[3],
            'balance_general' => (float) $datos[2], 'clasificacion' => [...$categorias[$datos[1]], 'en_deterioro' => false],
        ])->all();
        $resultado = app(RentabilidadAgenciaV7Service::class)->personalizarInforme(['terminales' => $terminales], '');

        $this->assertSame(['Ruta Norte', 'Ruta Sur'], array_column($resultado['rutas_impresion'], 'ruta'));
        $this->assertSame(['006', '007', '003', '005', '004', '008', '009', '002', '001'], array_column($resultado['rutas_impresion'][0]['terminales'], 'terminal'));
        $this->assertSame(['010'], array_column($resultado['rutas_impresion'][1]['terminales'], 'terminal'));
        $this->assertSame(array_sum(array_column($terminales, 'balance_general')), $resultado['totales']['balance_general']);
        $this->assertSame(10, $resultado['resumen_clasificaciones']['total']);
        $this->assertSame(4, collect($resultado['resumen_clasificaciones']['categorias'])->firstWhere('nombre', 'Regular')['cantidad']);
        $filtros = ['empresa' => 'Prueba', 'ruta' => '', 'alcance' => 'todas'];
        $html = view('gerencia.rentabilidad-agencia-v7-pdf', [
            'resultado' => [...$resultado, 'desde' => \Carbon\Carbon::parse('2026-01-01'), 'hasta' => \Carbon\Carbon::parse('2026-09-30')],
            'filtros' => $filtros, 'productos' => RentabilidadAgenciaV7Service::PRODUCTOS, 'contenido' => 'terminales', 'esImpresion' => true,
        ])->render();
        $this->assertStringContainsString('<h1>Informe de rentabilidad de agencia</h1>', $html);
        $posicionAnterior = -1;
        foreach (['006', '007', '003', '005', '004', '008', '009', '002', '001', '010'] as $terminal) {
            $posicion = strpos($html, '<span class="terminal-referencia">Terminal '.$terminal.'</span>');
            $this->assertNotFalse($posicion);
            $this->assertGreaterThan($posicionAnterior, $posicion);
            $posicionAnterior = $posicion;
        }
    }

    public static function volumenesPdf(): array
    {
        return [[47], [1524]];
    }

    public function test_observaciones_comparan_ventas_y_peso_de_gastos_por_ruta_sin_cambiar_importes(): void
    {
        $categoria = \App\Services\ClasificadorRentabilidadAgenciaV7::categorias()[2];
        $filas = collect([
            ['001', 'Norte', 1000, 100, 100],
            ['002', 'Norte', 100, 80, 60],
            ['003', 'Norte', 0, 0, 50],
            ['004', 'Norte', 500, 600, 25],
            ['005', 'Sur', 10, 0, 1],
            ['006', 'Sin actividad', 0, 0, 0],
        ])->map(fn (array $datos): array => [
            'terminal' => $datos[0], 'nombre' => 'Agencia '.$datos[0], 'empresa' => 'Prueba', 'ruta' => $datos[1],
            'ventas' => (float) $datos[2], 'premios_sacados' => (float) $datos[3], 'gastos' => (float) $datos[4],
            'utilidad' => (float) ($datos[2] - $datos[3]), 'balance_general' => (float) ($datos[2] - $datos[3] - $datos[4]),
            'clasificacion' => [...$categoria, 'en_deterioro' => false],
        ]);
        $servicio = app(RentabilidadAgenciaV7Service::class);
        $resultado = $servicio->personalizarInforme(['terminales' => $filas->all()], '');
        $observaciones = collect($resultado['rutas_impresion'])->flatMap(fn (array $ruta): array => $ruta['terminales'])->keyBy('terminal');
        $this->assertSame(['Mantener seguimiento.'], array_column($observaciones['001']['observaciones'], 'texto'));
        $this->assertSame(['Premios elevados.', 'Revisar gastos elevados.', 'Levantar ventas.'], array_column($observaciones['002']['observaciones'], 'texto'));
        $this->assertSame(['Sin ventas registradas.', 'Gastos sin ventas; revisar actividad.'], array_column($observaciones['003']['observaciones'], 'texto'));
        $this->assertSame(['Premios superan las ventas.'], array_column($observaciones['004']['observaciones'], 'texto'));
        $this->assertSame(['Mantener seguimiento.'], array_column($observaciones['005']['observaciones'], 'texto'));
        $this->assertSame(['Sin ventas registradas.'], array_column($observaciones['006']['observaciones'], 'texto'));
        $this->assertSame((float) $filas->sum('ventas'), $resultado['totales']['ventas']);
        $this->assertSame((float) $filas->sum('gastos'), $resultado['totales']['gastos']);
        $this->assertSame((float) $filas->sum('balance_general'), $resultado['totales']['balance_general']);
        $this->assertArrayNotHasKey('promedio_ventas', $resultado['rutas_impresion'][0]);
        $alerta = $filas->first();
        $alerta['clasificacion']['en_deterioro'] = true;
        $conAlerta = $servicio->personalizarInforme(['terminales' => [$alerta]], 'Norte');
        $this->assertSame('Caída reciente del balance.', $conAlerta['rutas_impresion'][0]['terminales'][0]['observaciones'][0]['texto']);
        config([
            'rentabilidad.observaciones.ventas_bajas_promedio' => 1,
            'rentabilidad.observaciones.premios_elevados_ventas' => 99,
            'rentabilidad.observaciones.gastos_elevados_ventas' => 99,
            'rentabilidad.observaciones.factor_gastos_ruta' => 100,
        ]);
        $ajustado = $servicio->personalizarInforme(['terminales' => $filas->all()], 'Norte');
        $revisar = collect($ajustado['rutas_impresion'][0]['terminales'])->firstWhere('terminal', '002');
        $this->assertSame(['Revisar balance negativo.'], array_column($revisar['observaciones'], 'texto'));
    }

    #[DataProvider('volumenesPdf')]
    public function test_pdf_con_terminales_ordenadas_y_alertas_termina_de_generarse(int $cantidad): void
    {
        $this->datos();
        $filtros = [...$this->filtros(), 'alcance' => 'todas'];
        $servicio = app(RentabilidadAgenciaV7Service::class);
        $resultado = $servicio->reporte($filtros);
        $categorias = \App\Services\ClasificadorRentabilidadAgenciaV7::categorias();
        $base = $resultado['filas']->first();
        $resultado['filas'] = collect(range(1, $cantidad))->map(fn (int $indice): array => [
            ...$base, 'terminal' => sprintf('05%04d', $indice), 'nombre' => 'Agencia San Pedro '.$indice,
            'ruta' => $cantidad === 47 ? '157-San Pedro 2' : 'Ruta '.intdiv($indice - 1, 40), 'ventas' => 5284077.0, 'premios_sacados' => 1371033.0,
            'utilidad' => 3913044.0, 'gastos' => 1381865.09, 'balance_general' => 2532028.91 - $indice * 100000,
            'clasificacion' => [...$base['clasificacion'], ...$categorias[$indice % count($categorias)], 'en_deterioro' => $indice % 7 === 1],
        ]);
        $informeId = $servicio->guardarResumenEjecutivo($filtros, '1', $resultado);
        $opciones = [...$filtros, 'informe_id' => $informeId, 'personalizado' => 1, 'ruta_impresion' => $cantidad === 47 ? '157-San Pedro 2' : '', 'salida' => 'pdf'];
        $pdf = $this->get(route('gerencia.rentabilidad-agencia-v7.informe-ejecutivo', $opciones))->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertGreaterThan(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf->getContent()));
    }

    public function test_recuperacion_reciente_en_endpoint_y_resumen_sin_modificar_importes(): void
    {
        $this->datos();
        DB::table('agencias')->insert(['terminal' => '05358', 'empresa' => 'Grupo Joselito', 'sistema' => 'LOTOBET', 'nombre_agencia' => 'Higuey-1']);
        $balances = [-52993.06, -76624.06, -7392.16, -5378.87, 15745.22, -61262.07, 59625.64, 33836.37, 10580.58];
        foreach ($balances as $indice => $balance) {
            $fecha = sprintf('2026-%02d-01', $indice + 1);
            DB::table('vt_usuarios_bet')->insert(['agencia_id' => '05358', 'fecha' => $fecha, 'monto' => 1000000]);
            DB::table('premios_bet')->insert(['agencia_id' => '05358', 'fecha' => $fecha, 'monto' => 1000000 - 10 - $balance]);
        }
        $filtros = [...$this->filtros(), 'terminal' => '05358', 'mes_fin' => 9];
        $response = $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', $filtros))->assertOk();
        $response->assertJsonPath('clasificacion.nombre', 'Para rescatar')->assertJsonPath('clasificacion.motivo_codigo', 'recuperacion_reciente')
            ->assertJsonPath('clasificacion.en_deterioro', false)->assertJsonPath('clasificacion.balance_reciente', 104042.59)
            ->assertJsonPath('balance_general', -83862.41)->assertJsonPath('gastos', 90)->assertJsonPath('ventas', 9000000);
        foreach ($balances as $indice => $balance) {
            $this->assertEquals($balance, $response->json('meses.'.$indice.'.balance_general'));
        }
        $resultado = app(RentabilidadAgenciaV7Service::class)->reporte($filtros);
        $this->assertSame(1, collect($resultado['resumen_clasificaciones']['categorias'])->firstWhere('nombre', 'Para rescatar')['cantidad']);
        $this->assertSame(0, $resultado['resumen_clasificaciones']['en_deterioro']);
    }

    public function test_cambio_de_configuracion_invalida_el_resumen_cacheado(): void
    {
        $this->datos();
        $servicio = app(RentabilidadAgenciaV7Service::class);
        $antes = $servicio->resumenEjecutivo($this->filtros(), '1');
        $this->assertSame(1, collect($antes['resumen_clasificaciones']['categorias'])->firstWhere('nombre', 'En evaluación')['cantidad']);
        config(['rentabilidad.min_meses' => 1]);
        $despues = $servicio->resumenEjecutivo($this->filtros(), '1');
        $this->assertSame(1, collect($despues['resumen_clasificaciones']['categorias'])->firstWhere('nombre', 'Para rescatar')['cantidad']);
        $this->assertSame($antes['totales'], $despues['totales']);
    }

    public function test_alerta_de_deterioro_no_altera_la_categoria_ni_los_totales(): void
    {
        $this->datos();
        DB::table('agencias')->insert(['terminal' => '9003', 'empresa' => 'Grupo Joselito', 'sistema' => 'LOTOBET', 'nombre_agencia' => 'En deterioro']);
        foreach ([...array_fill(0, 8, 100), -250] as $indice => $balance) {
            $fecha = sprintf('2026-%02d-01', $indice + 1);
            DB::table('vt_usuarios_bet')->insert(['agencia_id' => '9003', 'fecha' => $fecha, 'monto' => 1000]);
            DB::table('premios_bet')->insert(['agencia_id' => '9003', 'fecha' => $fecha, 'monto' => 1000 - 10 - $balance]);
        }
        $filtros = [...$this->filtros(), 'terminal' => '9003', 'mes_fin' => 9];
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', $filtros))->assertOk()
            ->assertJsonPath('clasificacion.nombre', 'Buena')->assertJsonPath('clasificacion.en_deterioro', true)
            ->assertJsonPath('clasificacion.balance_reciente', -50)->assertJsonPath('balance_general', 550);
        $resultado = app(RentabilidadAgenciaV7Service::class)->reporte($filtros);
        $this->assertSame(1, $resultado['resumen_clasificaciones']['en_deterioro']);
        $this->assertSame(1, collect($resultado['resumen_clasificaciones']['categorias'])->firstWhere('nombre', 'Buena')['cantidad']);
    }

    public function test_terminal_con_ventas_solo_en_septiembre_no_paga_r20_los_otros_meses(): void
    {
        $this->datos();
        DB::table('rentabilidad_agencia_r20')->update(['monto' => 7966.33]);
        DB::table('agencias')->insert(['terminal' => '052151', 'empresa' => 'Grupo Joselito', 'sistema' => 'LOTOBET', 'nombre_agencia' => 'Guayacanes']);
        DB::table('vt_usuarios_bet')->insert(['agencia_id' => '052151', 'fecha' => '2026-09-01', 'monto' => 45536, 'tipo' => 'Tradicional']);
        DB::table('premios_bet')->insert(['agencia_id' => '052151', 'fecha' => '2026-09-01', 'monto' => 15600]);
        DB::table('entradas_diario')->insert(['id_viejo' => '052151', 'fecha' => '2026-09-01', 'cuenta' => '6001', 'debito' => 5698.54, 'credito' => 0]);
        $filtros = [...$this->filtros(), 'terminal' => '052151', 'mes_fin' => 9];
        $response = $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', $filtros))->assertOk();
        $response->assertJsonPath('gastos', 13664.87)->assertJsonPath('balance_general', 16271.13)
            ->assertJsonPath('clasificacion.nombre', 'En evaluación')->assertJsonPath('meses.8.r20', 7966.33);
        foreach (range(0, 7) as $indice) {
            $response->assertJsonPath('meses.'.$indice.'.r20', 0)->assertJsonPath('meses.'.$indice.'.gastos', 0)->assertJsonPath('meses.'.$indice.'.balance_general', 0);
        }
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.cuentas', $filtros))->assertOk()
            ->assertJsonPath('total', 13664.87)->assertJsonCount(2, 'movimientos')
            ->assertJsonPath('movimientos.1.importe', 7966.33)->assertJsonPath('movimientos.1.descripcion', 'Gasto R20 manual (1 mes con ventas)');
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.cuentas', [...$filtros, 'mes_fin' => 8]))->assertOk()->assertJsonPath('total', 0)->assertJsonCount(0, 'movimientos');
    }

    public function test_tres_meses_en_evaluacion_y_cuatro_ya_se_clasifican(): void
    {
        $this->datos();
        DB::table('agencias')->insert(['terminal' => '9004', 'empresa' => 'Grupo Joselito', 'sistema' => 'LOTOBET', 'nombre_agencia' => 'Nueva']);
        foreach (range(1, 3) as $mes) {
            DB::table('vt_usuarios_bet')->insert(['agencia_id' => '9004', 'fecha' => sprintf('2026-%02d-01', $mes), 'monto' => 100]);
        }
        $filtros = [...$this->filtros(), 'terminal' => '9004', 'mes_fin' => 9];
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', $filtros))->assertOk()
            ->assertJsonPath('clasificacion.nombre', 'En evaluación')->assertJsonPath('clasificacion.meses_con_ventas', 3)->assertJsonPath('gastos', 30);
        DB::table('vt_usuarios_bet')->insert(['agencia_id' => '9004', 'fecha' => '2026-04-01', 'monto' => 100]);
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', $filtros))->assertOk()
            ->assertJsonPath('clasificacion.nombre', 'Rentable')->assertJsonPath('clasificacion.meses_con_ventas', 4)->assertJsonPath('gastos', 40);
    }

    public function test_r20_diario_solo_se_reparte_entre_dias_con_ventas_y_concilia_cuentas(): void
    {
        $this->datos();
        DB::table('rentabilidad_agencia_r20')->update(['monto' => 0.03]);
        DB::table('agencias')->insert(['terminal' => '9005', 'empresa' => 'Grupo Joselito', 'sistema' => 'MIXTO', 'nombre_agencia' => 'Mixta']);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '9005', 'fecha' => '2026-01-01', 'monto' => 10, 'tipo' => 'Tradicional'],
            ['agencia_id' => '9005', 'fecha' => '2026-01-03', 'monto' => 10, 'tipo' => 'Tradicional'],
        ]);
        DB::table('vt_usuarios_net')->insert(['agencia_id' => '9005', 'fecha' => '2026-01-01', 'monto' => 20, 'tipo' => 'Tradicional']);
        DB::table('entradas_diario')->insert(['id_viejo' => '9005', 'fecha' => '2026-01-02', 'cuenta' => '6001', 'debito' => 5, 'credito' => 0]);
        $filtros = [...$this->filtros(), 'terminal' => '9005', 'mes_fin' => 2];
        $servicio = app(RentabilidadAgenciaV7Service::class);
        $mensual = $servicio->reporte($filtros)['filas']->first();
        $diario = $servicio->reporte($filtros, true)['filas']->first();
        $this->assertSame(0.02, $diario['periodos'][0]['r20']);
        $this->assertSame(0.0, $diario['periodos'][1]['r20']);
        $this->assertSame(0.01, $diario['periodos'][2]['r20']);
        $this->assertSame(0.0, $mensual['periodos'][1]['r20']);
        $this->assertSame(5.03, $mensual['gastos']);
        $this->assertSame($mensual['gastos'], $diario['gastos']);
        $this->assertSame($mensual['balance_general'], $diario['balance_general']);
        $this->assertSame(5.03, $servicio->cuentas($filtros)['total']);
        $sinVentas = [...$filtros, 'productos_filtrados' => 1, 'productos' => ['Recarga']];
        $this->assertSame(5.0, $servicio->reporte($sinVentas)['filas']->first()['gastos']);
        $cuentas = $servicio->cuentas($sinVentas);
        $this->assertSame(5.0, $cuentas['total']);
        $this->assertFalse(collect($cuentas['movimientos'])->contains('cuenta', 'R20'));
    }

    public function test_mes_con_ventas_netas_cero_no_genera_r20_en_ningun_detalle(): void
    {
        $this->datos();
        DB::table('agencias')->insert(['terminal' => '9006', 'empresa' => 'Grupo Joselito', 'sistema' => 'MIXTO', 'nombre_agencia' => 'Ajustada']);
        DB::table('vt_usuarios_bet')->insert(['agencia_id' => '9006', 'fecha' => '2026-01-01', 'monto' => 100]);
        DB::table('vt_usuarios_net')->insert(['agencia_id' => '9006', 'fecha' => '2026-01-02', 'monto' => -100]);
        $filtros = [...$this->filtros(), 'terminal' => '9006', 'mes_fin' => 1];
        $servicio = app(RentabilidadAgenciaV7Service::class);
        foreach ([false, true] as $diario) {
            $fila = $servicio->reporte($filtros, $diario)['filas']->first();
            $this->assertSame(0.0, $fila['gastos']);
            $this->assertSame(0.0, array_sum(array_column($fila['periodos'], 'r20')));
        }
        $this->assertSame([], $servicio->cuentas($filtros)['movimientos']);
    }

    public function test_filtro_local_totales_y_resumen_ajax_en_javascript(): void
    {
        $codigo = <<<'JS'
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const elementos = new Map();
function elemento(id) {
    if (!elementos.has(id)) elementos.set(id, {value: '', checked: false, listeners: {}, classList: {add(){}, remove(){}, contains(){return true;}}, addEventListener(tipo, fn){this.listeners[tipo] = fn;}, replaceChildren(...opciones){this.options = opciones; this.value = opciones[0]?.value ?? '';}, textContent: '', innerHTML: ''});
    return elementos.get(id);
}
let inicio, opciones, alerta, urlConsultada, cargando = false, envios = 0, prevenidos = 0, filtro = '';
const eventosVentana = {};
const clasificacion = {nombre: 'Rentable', color: 'success', meses_positivos: 1, meses_con_ventas: 1, porcentaje: 100, balance_acumulado: 40, balance_reciente: 40, meses_recientes: 1, motivo: 'Clasificada por porcentaje.', en_deterioro: true};
const fila = {terminal: '001', nombre: '<Agencia>', empresa: 'Empresa', ventas: 100, premios_sacados: 50, utilidad: 50, gastos: 10, balance_general: 40, clasificacion};
const otraFila = {...fila, terminal: '002', ventas: 200, premios_sacados: 300, utilidad: -100, gastos: 20, balance_general: -120, clasificacion: {...clasificacion, nombre: 'No rentable', color: 'danger'}};
const sinVentas = {...fila, terminal: '003', ventas: 0, premios_sacados: 0, utilidad: 0, gastos: 10, balance_general: -10, clasificacion: {...clasificacion, nombre: 'Sin ventas', color: 'secondary'}};
const enEvaluacion = {...fila, terminal: '004', ventas: 50, premios_sacados: 30, utilidad: 20, balance_general: 10, clasificacion: {...clasificacion, nombre: 'En evaluación', color: 'secondary', en_deterioro: false}};
const datos = {...fila, meses: [{...fila, mes: '2026-01'}, {...sinVentas, mes: '2026-02'}]};
global.document = {getElementById: elemento, addEventListener(tipo, fn){inicio = fn;}, body: {appendChild(){}}, createElement(){return {set textContent(v){this.innerHTML = v.replaceAll('<', '&lt;').replaceAll('>', '&gt;');}};}};
global.window = {rentabilidadV7: {datos: [fila, otraFila, sinVentas, enEvaluacion], filtros: {anio: 2026, mes_inicio: 1, mes_fin: 3, ruta: 'Ruta Norte', productos: ['Tradicional']}, urls: {resumen: 'http://localhost/v7/resumen'}}, addEventListener(tipo, fn){eventosVentana[tipo] = fn;}};
window.Swal = {fire(configuracion){alerta = configuracion; configuracion.didOpen();}, showLoading(){cargando = true;}, close(){alerta = undefined;}};
global.HTMLFormElement = class {submit(){assert.equal(this, elemento('filtrosRentabilidadV7')); envios++;}};
global.Option = class {constructor(text, value){this.text = text; this.value = value;}};
window.rentabilidadV7.rutasDisponibles = [
    {empresa: 'Empresa A', ruta: 'Ruta Compartida'},
    {empresa: 'Empresa B', ruta: 'Ruta Compartida'},
    {empresa: 'Empresa A', ruta: 'Ruta Norte'},
    {empresa: 'Empresa B', ruta: 'Ruta Sur'},
];
const filasAplicadas = () => window.rentabilidadV7.datos.filter(fila => !filtro || new RegExp(filtro).test(fila.clasificacion.nombre));
const apiTabla = {
    row(){return {data(){return fila;}};},
    rows(opciones){assert.equal(opciones.search, 'applied'); return {data(){return {toArray: filasAplicadas};}};},
    column(indice){return {search(valor, regex, smart){assert.equal(indice, 7); assert.equal(regex, true); assert.equal(smart, false); filtro = valor; return apiTabla;}, footer(){return elemento('pie' + indice);}};},
    draw(){opciones.footerCallback.call({api(){return apiTabla;}}); return apiTabla;},
};
global.$ = () => ({DataTable(o){opciones = o; return apiTabla;}});
$.fn = {dataTable: {render: {text(){return x => x;}}, util: {escapeRegex(valor){return valor.replace(/[.*+?^$\{\}()|[\]\\]/g, '\\$&');}}}};
global.bootstrap = {Modal: class {show(){}}};
global.fetch = async url => {urlConsultada = url; return {ok: true, redirected: false, async json(){return datos;}};};
vm.runInThisContext(fs.readFileSync(process.argv[2], 'utf8'));
inicio();
const rutasVisibles = () => elemento('rutaV7').options.map(opcion => opcion.value);
assert.deepEqual(rutasVisibles(), ['', 'Ruta Compartida', 'Ruta Norte', 'Ruta Sur']);
elemento('rutaV7').value = 'Ruta Norte';
elemento('empresaV7').value = 'Empresa A';
elemento('empresaV7').listeners.change();
assert.deepEqual(rutasVisibles(), ['', 'Ruta Compartida', 'Ruta Norte']);
assert.equal(elemento('rutaV7').value, 'Ruta Norte');
elemento('empresaV7').value = 'Empresa B';
elemento('empresaV7').listeners.change();
assert.deepEqual(rutasVisibles(), ['', 'Ruta Compartida', 'Ruta Sur']);
assert.equal(elemento('rutaV7').value, '');
elemento('rutaV7').value = 'Ruta Compartida';
elemento('empresaV7').value = '';
elemento('empresaV7').listeners.change();
assert.deepEqual(rutasVisibles(), ['', 'Ruta Compartida', 'Ruta Norte', 'Ruta Sur']);
assert.equal(elemento('rutaV7').value, 'Ruta Compartida');
elemento('empresaV7').value = 'Sin rutas';
elemento('empresaV7').listeners.change();
assert.deepEqual(rutasVisibles(), ['']);
assert.equal(elemento('rutaV7').value, '');
assert.equal(opciones.columns.length, 9);
assert.deepEqual(opciones.columns.slice(2, 8).map(c => c.data), ['ventas', 'premios_sacados', 'utilidad', 'gastos', 'balance_general', 'clasificacion']);
assert.equal(opciones.deferRender, true);
assert.equal(opciones.pageLength, 25);
assert.ok(opciones.columns[7].render(clasificacion, 'display').includes('En deterioro'));
assert.equal(opciones.columns[7].render(clasificacion, 'filter'), 'Rentable');
assert.equal(opciones.columns[7].render(clasificacion, 'export'), 'Rentable · En deterioro');
assert.ok(opciones.columns[7].render({...clasificacion, nombre: 'Regular', color: 'info', en_deterioro: false}, 'display').includes('bg-info text-dark'));
assert.equal(opciones.columns[7].render({...clasificacion, nombre: 'Regular', en_deterioro: false}, 'export'), 'Regular');
assert.equal(opciones.buttons[0].exportOptions.orthogonal, 'export');
apiTabla.draw();
assert.equal(elemento('pie2').textContent, 'RD$ 350.00');
assert.equal(elemento('pie6').textContent, 'RD$ -80.00');
elemento('categoriaTablaV7').value = 'Rentable';
elemento('categoriaTablaV7').listeners.change();
assert.deepEqual(filasAplicadas().map(fila => fila.terminal), ['001']);
assert.equal(elemento('pie6').textContent, 'RD$ 40.00');
elemento('categoriaTablaV7').value = 'Buena';
elemento('categoriaTablaV7').listeners.change();
assert.equal(elemento('pie1').textContent, '0 terminales');
assert.equal(elemento('pie2').textContent, 'RD$ 0.00');
elemento('categoriaTablaV7').value = 'Sin ventas';
elemento('categoriaTablaV7').listeners.change();
assert.deepEqual(filasAplicadas().map(fila => fila.terminal), ['003']);
elemento('categoriaTablaV7').value = 'En evaluación';
elemento('categoriaTablaV7').listeners.change();
assert.deepEqual(filasAplicadas().map(fila => fila.terminal), ['004']);
elemento('categoriaTablaV7').value = '';
elemento('categoriaTablaV7').listeners.change();
assert.equal(filasAplicadas().length, 4);
assert.equal(urlConsultada, undefined);
elemento('anioCompletoV7').checked = true;
elemento('anioCompletoV7').listeners.change();
assert.equal(elemento('mes_finV7').value, '12');
elemento('mes_finV7').listeners.change();
assert.equal(elemento('anioCompletoV7').checked, false);
elemento('alcanceV7').value = 'terminal';
elemento('alcanceV7').listeners.change();
assert.equal(elemento('terminalV7').required, true);
const eventoSubmit = () => ({preventDefault(){prevenidos++;}});
elemento('filtrosRentabilidadV7').listeners.submit(eventoSubmit());
elemento('filtrosRentabilidadV7').listeners.submit(eventoSubmit());
assert.equal(alerta.title, 'Consultando datos...');
assert.equal(cargando, true);
assert.equal(envios, 0);
assert.equal(prevenidos, 2);
(async () => {
    await new Promise(resolve => setTimeout(resolve, 80));
    assert.equal(envios, 1);
    eventosVentana.pageshow({persisted: true});
    assert.equal(alerta, undefined);
    const boton = {dataset: {accion: 'resumen'}, textContent: 'Resumen', closest(){return {};}};
    await elemento('tablaRentabilidadV7').listeners.click({target: {closest(){return boton;}}});
    assert.equal(urlConsultada.pathname, '/v7/resumen');
    assert.equal(urlConsultada.searchParams.get('terminal'), '001');
    assert.equal(urlConsultada.searchParams.get('mes_fin'), '3');
    assert.equal(urlConsultada.searchParams.get('ruta'), 'Ruta Norte');
    assert.equal(urlConsultada.searchParams.get('productos[]'), 'Tradicional');
    const html = elemento('contenidoModalV7').innerHTML;
    assert.ok(html.includes('<th>Premios sacados</th>'));
    assert.ok(html.includes('<th>Utilidad</th>'));
    assert.ok(html.includes('<th>Balance general</th>'));
    assert.ok(html.includes('text-success'));
    assert.ok(html.includes('text-danger'));
    assert.ok(!html.includes('Pagados propios'));
    assert.ok(!html.includes('Balance neto'));
    assert.ok(html.includes('En deterioro'));
    assert.ok(html.includes('acumulado: RD$ 40.00'));
    assert.ok(html.includes('Clasificada por porcentaje.'));
    assert.ok(html.includes('último mes con ventas: +RD$ 40.00'));
    window.rentabilidadV7.datos = [];
    opciones = undefined;
    inicio();
    assert.equal(opciones, undefined);
    delete window.Swal;
    const previos = prevenidos;
    elemento('filtrosRentabilidadV7').listeners.submit(eventoSubmit());
    assert.equal(prevenidos, previos);
})().catch(error => {console.error(error); process.exitCode = 1;});
JS;
        $proceso = new Process(['node', '-', public_path('js/pages/rentabilidad-agencia-v7.js')]);
        $proceso->setInput($codigo)->setTimeout(15)->run();
        $this->assertTrue($proceso->isSuccessful(), $proceso->getErrorOutput());
    }

    public function test_generacion_en_javascript_descarga_pdf_muestra_errores_y_rehabilita_botones(): void
    {
        $codigo = <<<'JS'
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const elementos = new Map();
const botones = [{disabled: false}, {disabled: false}];
const descargas = [];
const esperas = [];
function elemento(id) {
    if (!elementos.has(id)) elementos.set(id, {value: '', checked: false, action: 'http://localhost/informe', listeners: {}, className: '', textContent: '', addEventListener(tipo, fn){this.listeners[tipo] = fn;}, replaceChildren(){}, querySelectorAll(){return botones;}});
    return elementos.get(id);
}
global.document = {getElementById: elemento, addEventListener(tipo, fn){global.iniciar = fn;}, body: {appendChild(){}}, createElement(){return {click(){descargas.push(this.download);}, remove(){}};}};
global.window = {rentabilidadV7: {datos: [], rutasDisponibles: []}, location: {href: 'http://localhost/reporte'}, addEventListener(){}, open(){return null;}};
global.Option = class {constructor(text, value){this.value = value;}};
global.FormData = class extends Map {constructor(){super([['informe_id', 'consulta'], ['personalizado', '1'], ['ruta_impresion', 'Ruta Norte']]);}};
global.setTimeout = (fn, delay) => {esperas.push(delay); return esperas.length;};
global.clearTimeout = () => {};
URL.createObjectURL = () => 'blob:documento';
URL.revokeObjectURL = () => {};
let peticiones = 0;
let respuesta;
const fetchNormal = async (url, opciones) => {
    peticiones++;
    assert.equal(opciones.headers.Accept, 'application/json');
    assert.equal(url.searchParams.get('ruta_impresion'), 'Ruta Norte');
    return respuesta;
};
global.fetch = fetchNormal;
const pdf = () => ({ok: true, redirected: false, headers: {get(){return 'application/pdf';}}, async blob(){return {};}});
vm.runInThisContext(fs.readFileSync(process.argv[2], 'utf8'));
iniciar();
const generar = elemento('formImpresionV7').listeners.submit;
const evento = salida => ({preventDefault(){}, submitter: {value: salida}});
const estado = () => elemento('estadoImpresionV7').textContent;
const habilitados = () => botones.every(boton => !boton.disabled);
(async () => {
    respuesta = pdf();
    await generar(evento('pdf'));
    assert.deepEqual(descargas, ['rentabilidad_agencia_v7.pdf']);
    assert.ok(estado().includes('PDF generado'));
    assert.ok(habilitados());
    assert.ok(esperas.includes(180000));
    respuesta = {ok: false, redirected: false, headers: {get(){return 'application/json';}}, async json(){return {errors: {informe_id: ['La consulta ha caducado.']}};}};
    await generar(evento('pdf'));
    assert.equal(estado(), 'La consulta ha caducado.');
    assert.ok(habilitados());
    respuesta = {ok: false, redirected: false, headers: {get(){return 'text/html';}}};
    await generar(evento('pdf'));
    assert.ok(estado().includes('No se pudo generar'));
    respuesta = {ok: true, redirected: true, headers: {get(){return 'text/html';}}};
    await generar(evento('pdf'));
    assert.ok(estado().includes('Revisa la sesión'));
    respuesta = {ok: true, redirected: false, headers: {get(){return 'text/html';}}};
    await generar(evento('pdf'));
    assert.ok(estado().includes('PDF válido'));
    global.fetch = async () => {throw Object.assign(new Error(), {name: 'AbortError'});};
    await generar(evento('pdf'));
    assert.ok(estado().includes('tiempo de espera'));
    assert.ok(habilitados());
    global.fetch = fetchNormal;
    const antes = peticiones;
    await generar(evento('imprimir'));
    assert.ok(estado().includes('bloqueó la ventana'));
    assert.equal(peticiones, antes);
    assert.ok(habilitados());
    let escrito;
    const ventana = {closed: false, document: {body: {}, open(){}, write(html){escrito = html;}, close(){}}, close(){this.closed = true;}};
    window.open = () => ventana;
    respuesta = {ok: true, redirected: false, headers: {get(){return 'text/html';}}, async text(){return '<html>Ruta Norte</html>';}};
    await generar(evento('imprimir'));
    assert.equal(escrito, '<html>Ruta Norte</html>');
    assert.ok(estado().includes('Documento preparado'));
    assert.equal(ventana.opener, null);
    let resolver;
    global.fetch = () => new Promise(resolve => {resolver = resolve;});
    const pendiente = generar(evento('pdf'));
    assert.ok(botones.every(boton => boton.disabled));
    await generar(evento('pdf'));
    resolver(pdf());
    await pendiente;
    assert.equal(descargas.length, 2);
    assert.ok(habilitados());
})().catch(error => {console.error(error); process.exitCode = 1;});
JS;
        $proceso = new Process(['node', '-', public_path('js/pages/rentabilidad-agencia-v7.js')]);
        $proceso->setInput($codigo)->setTimeout(15)->run();
        $this->assertTrue($proceso->isSuccessful(), $proceso->getErrorOutput());
    }
}
