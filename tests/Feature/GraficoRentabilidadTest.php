<?php

namespace Tests\Feature;

use App\Http\Controllers\Gerencia\GraficoRentabilidadController;
use App\Http\Controllers\Gerencia\RentabilidadAgenciaV5Controller;
use App\Http\Requests\ConsultarGraficoRentabilidadRequest;
use App\Http\Requests\ConsultarRentabilidadAgenciaV5Request;
use App\Models\User;
use App\ViewPermissionCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class GraficoRentabilidadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
        foreach (['vt_usuarios_bet', 'vt_usuarios_net', 'premios_bet', 'premios_net', 'pagos_misma_empresa_bet', 'pagos_porotra_empresa_bet', 'pagos_misma_empresa_net', 'pagos_porotra_empresa_net'] as $tabla) {
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
        $this->actingAs(User::factory()->make());
    }

    public function test_initial_panel_lists_active_routes_and_registers_the_card(): void
    {
        $this->crearDatos();
        $datos = $this->consultar([]);

        $this->assertNull($datos['resumen']);
        $this->assertSame(['Tradicional' => 0.0, 'No Tradicional' => 0.0, 'Recarga' => 0.0], $datos['ventasPorProducto']);
        $this->assertSame(['Norte', 'Sur'], $datos['rutas']->all());
        $this->assertSame(['Empresa A', 'Empresa B'], $datos['empresas']->all());
        $this->assertSame(['Tradicional', 'No Tradicional', 'Recarga'], $datos['productosSeleccionados']->all());
        $this->assertSame('/gerencia/grafico-rentabilidad', route('gerencia.grafico-rentabilidad', [], false));
        $tarjeta = app(ViewPermissionCatalog::class)->items()->firstWhere('path', '/gerencia/grafico-rentabilidad');
        $this->assertSame('Gráfico de rentabilidad', $tarjeta['name']);
        $this->assertSame('gerencia.rentabilidad_de_agencia.view', $tarjeta['permission']);
    }

    public function test_expense_aggregation_preserves_fixed_and_variable_totals_across_many_accounts(): void
    {
        $this->crearDatos();
        $clasificaciones = config('clasificacion_cuentas.cuentas');
        for ($numero = 7000; $numero < 7100; $numero++) {
            $cuenta = (string) $numero;
            $clasificaciones[$cuenta] = $numero % 2 === 0 ? 'FIJO' : 'VARIABLE';
            DB::table('cuentas_contables')->insert(['cuenta' => $cuenta, 'tipo' => 'Gasto']);
            DB::table('entradas_diario')->insert([
                'id_viejo' => '1001', 'fecha' => '2026-09-10', 'cuenta' => $cuenta, 'debito' => 1, 'credito' => 0,
            ]);
        }
        config(['clasificacion_cuentas.cuentas' => $clasificaciones]);
        $datos = $this->consultar(['mes' => '2026-09', 'empresa' => 'Empresa A', 'ruta' => 'Norte']);
        $this->assertSame(20.0, $datos['resumen']['costos']);
        $this->assertSame(210.0, $datos['resumen']['gastos']);
        $this->assertSame(160.0, $datos['agenciasResumen']->first()['gastos_fijos']);
        $this->assertSame(995.0, $datos['resumen']['balance']);
        $agencia = collect($datos['datosCargados']['agencias'])->firstWhere('terminal', '1001');
        $this->assertCount(1, $agencia['gastosMensuales']);
        $this->assertSame(210.0, $agencia['gastosMensuales'][0]['gastos']);
    }

    public function test_date_covering_migration_is_idempotent_and_reversible(): void
    {
        Schema::table('entradas_diario', function (Blueprint $table): void {
            $table->index('fecha', 'entradas_diario_fecha_index');
        });
        $migracion = require database_path('migrations/2026_10_29_155434_add_date_covering_indexes_to_rentabilidad_chart_tables.php');
        $migracion->up();
        $migracion->up();
        foreach (['vt_usuarios_bet', 'vt_usuarios_net'] as $tabla) {
            $this->assertTrue(Schema::hasIndex($tabla, ['fecha', 'agencia_id', 'tipo', 'monto']));
        }
        foreach (['premios_bet', 'premios_net'] as $tabla) {
            $this->assertTrue(Schema::hasIndex($tabla, ['fecha', 'agencia_id', 'producto_id', 'monto']));
        }
        $this->assertTrue(Schema::hasIndex('entradas_diario', ['fecha', 'cuenta', 'id_viejo', 'debito', 'credito']));
        $migracion->down();
        $migracion->down();
        foreach (['vt_usuarios_bet', 'vt_usuarios_net', 'premios_bet', 'premios_net', 'entradas_diario'] as $tabla) {
            $this->assertFalse(Schema::hasIndex($tabla, $tabla.'_rent_grafico_fecha_index'));
        }
        $this->assertTrue(Schema::hasIndex('entradas_diario', 'entradas_diario_fecha_index'));
    }

    public function test_online_migration_rejects_myisam_before_any_ddl(): void
    {
        $conexion = \Mockery::mock(\Illuminate\Database\Connection::class);
        $conexion->shouldReceive('getDriverName')->andReturn('mysql');
        $conexion->shouldReceive('getDatabaseName')->andReturn('crm');
        $conexion->shouldReceive('select')->once()->andReturn(collect([
            'vt_usuarios_bet', 'vt_usuarios_net', 'premios_bet', 'premios_net', 'entradas_diario',
        ])->map(fn (string $tabla): object => (object) ['tabla' => $tabla, 'motor' => $tabla === 'entradas_diario' ? 'MyISAM' : 'InnoDB'])->all());
        $conexion->shouldNotReceive('statement');
        Schema::shouldReceive('hasIndex')->never();
        DB::shouldReceive('connection')->andReturn($conexion);
        $migracion = require database_path('migrations/2026_10_29_155434_add_date_covering_indexes_to_rentabilidad_chart_tables.php');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('entradas_diario debe usar InnoDB');
        $migracion->up();
    }

    public function test_mysql_migration_requires_online_ddl_for_creation_and_rollback(): void
    {
        $conexion = \Mockery::mock(\Illuminate\Database\Connection::class);
        $conexion->shouldReceive('getDriverName')->andReturn('mysql');
        $conexion->shouldReceive('getDatabaseName')->andReturn('crm');
        $conexion->shouldReceive('select')->twice()->andReturn(collect([
            'vt_usuarios_bet', 'vt_usuarios_net', 'premios_bet', 'premios_net', 'entradas_diario',
        ])->map(fn (string $tabla): object => (object) ['tabla' => $tabla, 'motor' => 'InnoDB'])->all());
        $sentencias = [];
        $conexion->shouldReceive('statement')->times(10)->andReturnUsing(function (string $sql) use (&$sentencias): bool {
            $sentencias[] = $sql;

            return true;
        });
        $creando = true;
        Schema::shouldReceive('hasIndex')->andReturnUsing(function () use (&$creando): bool {
            return ! $creando;
        });
        DB::shouldReceive('connection')->andReturn($conexion);
        $migracion = require database_path('migrations/2026_10_29_155434_add_date_covering_indexes_to_rentabilidad_chart_tables.php');
        $migracion->up();
        $creando = false;
        $migracion->down();
        foreach ($sentencias as $sql) {
            $this->assertStringContainsString('ALGORITHM=INPLACE, LOCK=NONE', $sql);
        }
        $this->assertCount(5, array_filter($sentencias, fn (string $sql): bool => str_contains($sql, 'ADD INDEX')));
        $this->assertCount(5, array_filter($sentencias, fn (string $sql): bool => str_contains($sql, 'DROP INDEX')));
    }

    public function test_monthly_queries_keep_date_filters_sargable_and_omit_redundant_month_grouping(): void
    {
        $this->crearDatos();
        DB::enableQueryLog();
        $this->consultar(['mes' => '2026-09']);
        $consultas = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_contains(strtolower($sql), 'sum('));
        DB::disableQueryLog();
        $this->assertCount(9, $consultas);
        foreach ($consultas as $sql) {
            $this->assertStringNotContainsString('SUBSTR(', $sql);
            $this->assertStringContainsString('between ? and ?', strtolower($sql));
            $this->assertStringContainsString('? AS mes', $sql);
        }
    }

    public function test_http_page_defers_annual_data_without_querying_the_transaction_tables(): void
    {
        $this->crearDatos();
        auth()->user()->setRelation('roles', new \Illuminate\Database\Eloquent\Collection([
            new \Spatie\Permission\Models\Role(['name' => 'superadmin', 'guard_name' => 'web']),
        ]));
        view()->share('errors', new ViewErrorBag);
        $this->withoutMiddleware();

        $this->get(route('gerencia.grafico-rentabilidad'))
            ->assertOk()
            ->assertViewHas('datosCargados', fn (mixed $datos): bool => $datos === null)
            ->assertSee('let datosCargados = null;', false);

        DB::enableQueryLog();
        $this->get(route('gerencia.grafico-rentabilidad', [
            'periodo' => 'anio', 'anio' => 2026, 'empresa' => 'Empresa A', 'alcance' => 'todas',
            'productos_filtrados' => 1, 'productos' => ['Tradicional', 'No Tradicional', 'Recarga'],
        ]))
            ->assertOk()
            ->assertViewHas('datosCargados', null)
            ->assertViewHas('mesesPorCargar', fn (array $meses): bool => count($meses) === 12 && $meses[0] === '2026-01' && $meses[11] === '2026-12')
            ->assertSee('Ventas mensuales por producto')
            ->assertSee('FiltrosGraficoRentabilidad.cargarMeses', false)
            ->assertDontSee('Undefined variable');
        $consultas = collect(DB::getQueryLog())->pluck('query')->implode(' ');
        DB::disableQueryLog();
        foreach (['vt_usuarios_', 'premios_bet', 'pagos_misma_empresa_', 'entradas_diario'] as $tabla) {
            $this->assertStringNotContainsString($tabla, $consultas);
        }
        $this->getJson(route('gerencia.grafico-rentabilidad.datos', ['mes' => '2026-09']))
            ->assertOk()
            ->assertJsonPath('meses.0', '2026-09')
            ->assertJsonCount(3, 'agencias');
        $this->getJson(route('gerencia.grafico-rentabilidad.datos', ['periodo' => 'anio', 'anio' => 2026]))->assertUnprocessable();
        $this->getJson(route('gerencia.grafico-rentabilidad.datos'))->assertUnprocessable();
    }

    public function test_route_and_company_filter_the_source_without_excluding_payments_by_other_routes(): void
    {
        $this->crearDatos();
        $datos = $this->consultar(['mes' => '2026-09', 'empresa' => 'Empresa A', 'ruta' => 'Norte']);

        $this->assertSame(['1001'], $datos['agenciasResumen']->pluck('terminal')->all());
        $this->assertSame('Norte', $datos['agenciasResumen']->first()['ruta']);
        $this->assertSame(1600.0, $datos['resumen']['venta']);
        $this->assertSame(375.0, $datos['resumen']['premios']);
        $this->assertSame(500.0, $datos['resumen']['premios_sacados']);
        $this->assertSame(20.0, $datos['resumen']['costos']);
        $this->assertSame(110.0, $datos['resumen']['gastos']);
        $this->assertSame(1095.0, $datos['resumen']['balance']);
        $this->assertSame(['Tradicional' => 1000.0, 'No Tradicional' => 500.0, 'Recarga' => 100.0], $datos['ventasPorProducto']);
        $this->assertSame(['2026-09'], $datos['ventasMensuales']['meses']);
        $this->assertSame([
            ['name' => 'Tradicional', 'data' => [1000.0]],
            ['name' => 'No Tradicional', 'data' => [500.0]],
            ['name' => 'Recarga', 'data' => [100.0]],
        ], $datos['ventasMensuales']['series']);

        $norte = $this->consultar(['mes' => '2026-09', 'ruta' => 'Norte']);
        $this->assertSame(['1001', '2001'], $norte['agenciasResumen']->pluck('terminal')->all());
        $this->assertSame(2300.0, $norte['resumen']['venta']);
        $this->assertSame(475.0, $norte['resumen']['premios']);
        $this->assertSame(['Tradicional' => 1700.0, 'No Tradicional' => 500.0, 'Recarga' => 100.0], $norte['ventasPorProducto']);
    }

    public function test_route_options_follow_the_selected_company(): void
    {
        $this->crearDatos();
        $datos = $this->consultar(['empresa' => 'Empresa B']);

        $this->assertSame(['Norte'], $datos['rutas']->all());
        $this->assertSame(['Norte', 'Sur'], $datos['rutasDisponibles']->pluck('ruta')->unique()->values()->all());
    }

    public function test_terminal_selection_respects_route_and_company(): void
    {
        $this->crearDatos();
        $filtros = ['mes' => '2026-09', 'empresa' => 'Empresa A', 'ruta' => 'Norte', 'alcance' => 'terminal', 'terminal' => '1001'];

        $this->assertSame(375.0, $this->consultar($filtros)['resumen']['premios']);
        $fueraDeRuta = $this->consultar([...$filtros, 'terminal' => '1002']);
        $this->assertTrue($fueraDeRuta['agenciasResumen']->isEmpty());
        $this->assertSame(0, $fueraDeRuta['resumen']['terminales']);
        $this->assertSame(['Tradicional' => 0.0, 'No Tradicional' => 0.0, 'Recarga' => 0.0], $fueraDeRuta['ventasPorProducto']);

        $todas = $this->consultar([...$filtros, 'alcance' => 'todas', 'terminal' => '1002']);
        $this->assertSame(['1001'], $todas['agenciasResumen']->pluck('terminal')->all());
        $this->assertTrue($this->consultar([...$filtros, 'ruta' => 'Desconocida'])['agenciasResumen']->isEmpty());
    }

    public function test_product_filters_keep_the_v5_prize_and_expense_rules(): void
    {
        $this->crearDatos();
        foreach ([
            [['Tradicional'], 1000.0, 325.0, 400.0],
            [['No Tradicional'], 500.0, 50.0, 100.0],
            [['Recarga'], 100.0, 0.0, 0.0],
            [[], 0.0, 0.0, 0.0],
        ] as [$productos, $ventas, $premios, $sacados]) {
            $datos = $this->consultar([
                'mes' => '2026-09', 'empresa' => 'Empresa A', 'ruta' => 'Norte',
                'productos_filtrados' => 1, 'productos' => $productos,
            ]);

            $this->assertSame($ventas, $datos['resumen']['venta']);
            $this->assertSame($premios, $datos['resumen']['premios']);
            $this->assertSame($sacados, $datos['resumen']['premios_sacados']);
            $this->assertSame($ventas - $premios - 130, $datos['resumen']['balance']);
            $this->assertSame($ventas, array_sum($datos['ventasPorProducto']));
            foreach (['Tradicional', 'No Tradicional', 'Recarga'] as $producto) {
                if (! in_array($producto, $productos, true)) {
                    $this->assertSame(0.0, $datos['ventasPorProducto'][$producto]);
                    $this->assertSame([0.0], collect($datos['ventasMensuales']['series'])->firstWhere('name', $producto)['data']);
                }
            }
        }
    }

    public function test_prizes_are_compacted_by_product_family_without_duplicate_or_missing_amounts(): void
    {
        $this->crearDatos();
        DB::table('catalogo_juegos')->insert([
            ['producto_id' => '10', 'tipo' => 'Tradicional'],
            ['producto_id' => '10', 'tipo' => 'Tradicional'],
            ['producto_id' => '11', 'tipo' => 'Tradicional'],
            ['producto_id' => '11', 'tipo' => 'No Tradicional'],
        ]);
        foreach (['10' => 4, '11' => 7, '999' => 9, '' => 3] as $producto => $importe) {
            DB::table('pagos_misma_empresa_bet')->insert([
                'agencia_id' => '1002', 'pagado_agencia_id' => '1001', 'fecha' => '2026-09-05',
                'producto_id' => $producto === '' ? null : (string) $producto, 'monto' => $importe,
            ]);
        }
        for ($producto = 100; $producto < 200; $producto++) {
            DB::table('catalogo_juegos')->insert(['producto_id' => (string) $producto, 'tipo' => 'Tradicional']);
            DB::table('pagos_misma_empresa_bet')->insert([
                'agencia_id' => '1001', 'pagado_agencia_id' => '1001', 'fecha' => '2026-09-06',
                'producto_id' => (string) $producto, 'monto' => 1,
            ]);
        }
        $filtros = ['mes' => '2026-09', 'empresa' => 'Empresa A', 'ruta' => 'Norte', 'productos_filtrados' => 1];
        foreach ([
            [['Tradicional', 'No Tradicional', 'Recarga'], 498.0],
            [['Tradicional'], 436.0],
            [['No Tradicional'], 57.0],
            [['Recarga'], 0.0],
            [[], 0.0],
        ] as [$productos, $premios]) {
            $datos = $this->consultar([...$filtros, 'productos' => $productos]);
            $this->assertSame($premios, $datos['resumen']['premios']);
            $agencia = collect($datos['datosCargados']['agencias'])->firstWhere('terminal', '1001');
            $this->assertCount(4, $agencia['premios']);
            $cliente = $this->filtrarEnNavegador($datos['datosCargados'], [
                'empresa' => 'Empresa A', 'ruta' => 'Norte', 'alcance' => 'todas', 'terminal' => '', 'productos' => $productos,
            ]);
            $this->assertEquals($premios, $cliente['resumen']['premios']);
            $this->assertEquals($premios, array_sum($cliente['graficos']['premios'][0]['data']));
        }
    }

    public function test_unfiltered_source_matches_v5(): void
    {
        $this->crearDatos();
        $request = ConsultarRentabilidadAgenciaV5Request::create('/gerencia/rentabilidad-agencia-v5', 'GET', ['mes' => '2026-09']);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();
        $v5 = app(RentabilidadAgenciaV5Controller::class)->index($request)->getData();
        $grafico = $this->consultar(['mes' => '2026-09']);

        $this->assertSame($v5['resumen'], $grafico['resumen']);
        $this->assertSame($v5['agenciasResumen']->all(), $grafico['agenciasResumen']->map(function (array $fila): array {
            unset($fila['ruta']);

            return $fila;
        })->all());
    }

    public function test_panel_renders_filters_and_totals_without_a_data_table(): void
    {
        $this->crearDatos();
        $datos = $this->consultar(['mes' => '2026-09', 'empresa' => 'Empresa A', 'ruta' => 'Norte']);
        $fuente = file_get_contents(resource_path('views/gerencia/grafico-rentabilidad.blade.php'));
        $contenido = Blade::render(str_replace(["@extends('app')", "@section('content')", "@section('script')", '@endsection'], '', $fuente), [
            ...$datos, 'errors' => new ViewErrorBag,
        ]);

        $this->assertStringContainsString('Gráfico de rentabilidad', $contenido);
        foreach (['periodo', 'mes', 'mes_desde', 'mes_hasta', 'anio', 'empresa', 'ruta', 'alcance', 'terminal', 'productos[]'] as $campo) {
            $this->assertStringContainsString('name="'.$campo.'"', $contenido);
        }
        $this->assertStringContainsString('<option value="Norte" selected>', $contenido);
        $this->assertStringContainsString('RD$ 1,095.00', $contenido);
        $this->assertStringNotContainsString('DataTable', $contenido);
        $this->assertStringNotContainsString('<table', $contenido);
        $this->assertStringNotContainsString('informe-ejecutivo', $contenido);
        $this->assertStringContainsString('Ventas mensuales por producto', $contenido);
        $this->assertStringContainsString('id="graficoVentasPorProducto"', $contenido);
        $this->assertStringContainsString('libs/apexcharts/apexcharts.min.js', $contenido);
        $this->assertStringContainsString('distributed: false', $contenido);
        $this->assertStringContainsString('series: ventasMensuales.series', $contenido);
        $this->assertStringContainsString('categories: ventasMensuales.etiquetas', $contenido);
        $this->assertStringContainsString('RD$ 1,600.00', $contenido);
        $this->assertStringContainsString('type="button" id="filtrarRentabilidadGrafico"', $contenido);
        $this->assertStringContainsString('Cargar datos', $contenido);
        $this->assertStringContainsString('Mostrar resumen', $contenido);
        $this->assertStringContainsString('id="tarjetaResumenRentabilidad" class="card mb-4 d-none"', $contenido);
        $this->assertFalse($datos['mostrarResumen']);
        $datosConResumen = $this->consultar(['mes' => '2026-09', 'mostrar_resumen' => '1']);
        $this->assertTrue($datosConResumen['mostrarResumen']);
        $contenidoConResumen = Blade::render(str_replace(["@extends('app')", "@section('content')", "@section('script')", '@endsection'], '', $fuente), [
            ...$datosConResumen, 'errors' => new ViewErrorBag,
        ]);
        $this->assertStringContainsString('id="tarjetaResumenRentabilidad" class="card mb-4"', $contenidoConResumen);
        $this->assertStringContainsString('name="mostrar_resumen" value="1" checked', $contenidoConResumen);
        $this->assertStringContainsString('grafico-rentabilidad-filtros.js', $contenido);
        foreach (['ventas_por_dia', 'meta_venta', 'premios_sacados', 'premios', 'ingreso_real', 'gastos', 'balance'] as $campo) {
            $this->assertStringContainsString('data-grafico-metrica="'.$campo.'"', $contenido);
        }
        $this->assertStringNotContainsString('data-grafico-metrica="gastos_fijos"', $contenido);
    }

    public function test_browser_filters_reuse_the_loaded_period_and_match_server_totals(): void
    {
        $this->crearDatos();
        DB::table('pagos_misma_empresa_bet')->insert(['agencia_id' => '1002', 'pagado_agencia_id' => '1001', 'fecha' => '2026-09-01', 'producto_id' => '999', 'monto' => 7]);
        $productos = ['Tradicional', 'No Tradicional', 'Recarga'];
        $carga = $this->consultar([
            'mes' => '2026-09', 'empresa' => 'Empresa A', 'ruta' => 'Norte', 'productos_filtrados' => 1, 'productos' => ['Tradicional'],
        ]);
        $this->assertCount(3, $carga['datosCargados']['agencias']);

        foreach ([
            ['empresa' => '', 'ruta' => '', 'alcance' => 'todas', 'terminal' => '', 'productos' => $productos],
            ['empresa' => 'Empresa A', 'ruta' => 'Norte', 'alcance' => 'todas', 'terminal' => '', 'productos' => ['Tradicional']],
            ['empresa' => 'Empresa A', 'ruta' => 'Sur', 'alcance' => 'todas', 'terminal' => '', 'productos' => ['Recarga']],
            ['empresa' => 'Empresa B', 'ruta' => 'Norte', 'alcance' => 'terminal', 'terminal' => '2001', 'productos' => $productos],
            ['empresa' => '', 'ruta' => 'Sur', 'alcance' => 'terminal', 'terminal' => '1001', 'productos' => $productos],
            ['empresa' => 'Empresa A', 'ruta' => 'Norte', 'alcance' => 'todas', 'terminal' => '', 'productos' => []],
            ['empresa' => 'Empresa A', 'ruta' => 'Norte', 'alcance' => 'todas', 'terminal' => '', 'productos' => ['Tradicional', 'No Tradicional']],
        ] as $filtros) {
            $servidor = $this->consultar(['mes' => '2026-09', 'productos_filtrados' => 1, ...$filtros]);
            $navegador = $this->filtrarEnNavegador($carga['datosCargados'], $filtros);

            $this->assertEquals($servidor['resumen'], $navegador['resumen']);
            $this->assertEquals($servidor['ventasPorProducto'], $navegador['ventasPorProducto']);
            $this->assertEquals($servidor['ventasMensuales'], $navegador['ventasMensuales']);
        }
    }

    public function test_browser_filters_preserve_monthly_series_and_annual_r20(): void
    {
        $this->crearDatos();
        DB::table('vt_usuarios_bet')->insert(['agencia_id' => '1001', 'fecha' => '2026-10-01', 'tipo' => 'Tradicional', 'monto' => 300]);
        $filtros = ['empresa' => 'Empresa A', 'ruta' => 'Norte', 'alcance' => 'terminal', 'terminal' => '1001', 'productos' => ['Tradicional']];
        $carga = $this->consultar(['periodo' => 'anio', 'anio' => 2026, 'productos_filtrados' => 1, ...$filtros]);
        $navegador = $this->filtrarEnNavegador($carga['datosCargados'], $filtros);

        $this->assertEquals($carga['resumen'], $navegador['resumen']);
        $this->assertEquals($carga['ventasMensuales'], $navegador['ventasMensuales']);
        $this->assertCount(12, $navegador['ventasMensuales']['meses']);
        $this->assertEquals(1000.0, $navegador['ventasMensuales']['series'][0]['data'][8]);
        $this->assertEquals(300.0, $navegador['ventasMensuales']['series'][0]['data'][9]);
        $this->assertEquals(220.0, $navegador['resumen']['gastos']);
    }

    public function test_month_loader_limits_concurrency_preserves_order_and_aborts_pending_requests_on_error(): void
    {
        $codigo = <<<'JS'
import { pathToFileURL } from 'node:url';
await import(pathToFileURL(process.argv[2]).href);
const cargar = FiltrosGraficoRentabilidad.cargarMeses;
const meses = ['2026-01', '2026-02', '2026-03', '2026-04', '2026-05'];
const datosMes = (mes) => {
    const numero = Number(mes.slice(-2));
    return { meses: [mes], etiquetas: [mes], productosPremios: { Tradicional: ['1', '3'], 'No Tradicional': ['2', '3'] }, agencias: [{
        terminal: '001', empresa: 'Empresa A', ruta: 'Norte', costos: numero / 10, gastos: numero,
        ventas: [{ mes, tipo: 'Tradicional', total: numero * 100 }], premios: [{ mes, producto_id: '1', total: numero * 2 }],
        sacados: [{ mes, producto_id: '1', total: numero * 3 }], gastosMensuales: [{ mes, costos: numero / 10, gastos: numero }],
    }] };
};
const referencia = await cargar(meses, async (mes) => datosMes(mes), () => {});
const pendientes = new Map();
const solicitados = [];
const progreso = [];
let activas = 0;
let maximo = 0;
const promesa = cargar([...meses].reverse(), (mes) => new Promise((resolver) => {
    solicitados.push(mes);
    maximo = Math.max(maximo, ++activas);
    pendientes.set(mes, () => { activas--; resolver(datosMes(mes)); });
}), (completados, total, mes) => progreso.push({ completados, total, mes }));
const iniciales = [...solicitados];
for (const mes of ['2026-03', '2026-04', '2026-05', '2026-02', '2026-01']) {
    pendientes.get(mes)();
    await new Promise(setImmediate);
}
const datos = await promesa;
const resumen = FiltrosGraficoRentabilidad.filtrar(datos, { empresa: 'Empresa A', ruta: '', alcance: 'todas', terminal: '', productos: ['Tradicional'] }).resumen;
const iniciadosError = [];
const pendientesError = new Map();
let activosError = 0;
let abortados = 0;
let terminados = 0;
const cargaConError = cargar(meses, (mes, signal) => new Promise((resolver, rechazar) => {
    iniciadosError.push(mes);
    activosError++;
    let terminado = false;
    const finalizar = (error) => {
        if (terminado) return;
        terminado = true;
        activosError--;
        terminados++;
        rechazar(error);
    };
    pendientesError.set(mes, finalizar);
    signal.addEventListener('abort', () => {
        if (!terminado) abortados++;
        finalizar(new Error('Cancelado'));
    }, { once: true });
}), () => {});
const errorObservado = cargaConError.then(() => 'No debe completar', (error) => error.message);
pendientesError.get('2026-02')(new Error('Falló 2026-02'));
const error = await errorObservado;
const vacio = await cargar([], () => { throw new Error('No debe consultar'); }, () => { throw new Error('No debe actualizar'); });
process.stdout.write(JSON.stringify({ iniciales, maximo, activas, meses: datos.meses, igual: JSON.stringify(datos) === JSON.stringify(referencia),
    progreso, resumen, error, iniciadosError, activosError, abortados, terminados, vacio }));
JS;
        $proceso = new Process(['node', '--input-type=module', '-', public_path('js/pages/grafico-rentabilidad-filtros.js')]);
        $proceso->setInput($codigo);
        $proceso->mustRun();
        $resultado = json_decode($proceso->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(['2026-01', '2026-02', '2026-03'], $resultado['iniciales']);
        $this->assertSame(3, $resultado['maximo']);
        $this->assertSame(0, $resultado['activas']);
        $this->assertSame(['2026-01', '2026-02', '2026-03', '2026-04', '2026-05'], $resultado['meses']);
        $this->assertTrue($resultado['igual']);
        $this->assertSame([0, 1, 2, 3, 4, 5], array_column($resultado['progreso'], 'completados'));
        $this->assertSame([5], array_values(array_unique(array_column($resultado['progreso'], 'total'))));
        $this->assertSame(1500, $resultado['resumen']['venta']);
        $this->assertSame(30, $resultado['resumen']['premios']);
        $this->assertSame(15, $resultado['resumen']['gastos']);
        $this->assertSame(1453.5, $resultado['resumen']['balance']);
        $this->assertSame('Falló 2026-02', $resultado['error']);
        $this->assertSame(['2026-01', '2026-02', '2026-03'], $resultado['iniciadosError']);
        $this->assertSame(0, $resultado['activosError']);
        $this->assertSame(2, $resultado['abortados']);
        $this->assertSame(3, $resultado['terminados']);
        $this->assertNull($resultado['vacio']);
    }

    public function test_filter_button_updates_cards_and_chart_without_reloading_and_requires_loading_new_dates(): void
    {
        $this->crearDatos();
        $datos = $this->consultar(['mes' => '2026-09', 'empresa' => 'Empresa A', 'ruta' => 'Norte']);
        $fuente = file_get_contents(resource_path('views/gerencia/grafico-rentabilidad.blade.php'));
        $html = Blade::render(str_replace(["@extends('app')", "@section('content')", "@section('script')", '@endsection'], '', $fuente), [...$datos, 'errors' => new ViewErrorBag]);
        preg_match('/<script>\s*([\s\S]*?)<\/script>/', $html, $coincidencias);
        $diferidos = $this->consultar(['periodo' => 'rango', 'mes_desde' => '2026-09', 'mes_hasta' => '2026-10', 'empresa' => 'Empresa A', 'ruta' => 'Norte'], false);
        $htmlDiferido = Blade::render(str_replace(["@extends('app')", "@section('content')", "@section('script')", '@endsection'], '', $fuente), [...$diferidos, 'errors' => new ViewErrorBag]);
        preg_match('/<script>\s*([\s\S]*?)<\/script>/', $htmlDiferido, $scriptDiferido);
        $codigo = <<<'JS'
import { pathToFileURL } from 'node:url';
import vm from 'node:vm';
await import(pathToFileURL(process.argv[1]).href);
const entrada = globalThis.entradaGrafico;
const elementos = new Map();
function elemento(value = '') {
    const clases = new Set();
    return { value, textContent: '', dataset: {}, eventos: {}, disabled: false, hijos: [],
        append(...hijos) { this.hijos.push(...hijos); }, replaceChildren() { this.hijos = []; },
        classList: { toggle(nombre, activo) { if (activo) clases.add(nombre); else clases.delete(nombre); }, contains(nombre) { return clases.has(nombre); } },
        addEventListener(nombre, accion) { this.eventos[nombre] = accion; }, reportValidity() { return true; } };
}
const valores = { periodoRentabilidadGrafico: 'mes', mesRentabilidadGrafico: '2026-09', empresaRentabilidadGrafico: 'Empresa A', rutaRentabilidadGrafico: 'Norte', alcanceRentabilidadGrafico: 'todas' };
const get = (id) => { if (!elementos.has(id)) elementos.set(id, elemento(valores[id] || '')); return elementos.get(id); };
const productos = ['Tradicional', 'No Tradicional', 'Recarga'].map((producto) => { const campo = elemento(producto); campo.checked = true; campo.dataset.totalProducto = producto; return campo; });
const resumen = ['Ventas', 'Premios sacados', 'Premios propios pagados', 'Ingreso real', 'Gastos', 'Balance'].map((nombre) => { const campo = elemento(); campo.dataset.resumenRentabilidad = nombre; return campo; });
const pagos = [60, 70, 80].map((pago) => { const campo = elemento(String(pago)); campo.checked = pago === 60; return campo; });
const metricas = ['ventas_por_dia', 'meta_venta', 'premios_sacados', 'premios', 'ingreso_real', 'gastos', 'balance'].map((campo) => { const contenedor = get('graficoMetrica-' + campo); contenedor.dataset = { graficoMetrica: campo, titulo: campo }; return contenedor; });
globalThis.window = globalThis;
globalThis.document = { documentElement: {}, getElementById: get,
    createElement() { return elemento(); },
    addEventListener(nombre, accion) { accion(); },
    querySelectorAll(selector) {
        if (selector === '[data-grafico-metrica]') return metricas;
        if (selector === '#pagosRentabilidadGrafico input:checked') return pagos.filter((pago) => pago.checked);
        if (selector === '#pagosRentabilidadGrafico input') return pagos;
        if (selector === '[data-total-producto]') return productos;
        if (selector === '[data-resumen-rentabilidad]') return resumen;
        if (selector.includes(':checked')) return productos.filter((campo) => campo.checked);
        return [];
    } };
globalThis.getComputedStyle = () => ({ getPropertyValue() { return ''; } });
globalThis.fetch = () => { throw new Error('No debe haber peticiones al filtrar.'); };
let grafico;
const graficos = new Map();
globalThis.ApexCharts = class { constructor(destino, opciones) { this.opciones = opciones; this.rangosColor = opciones.plotOptions.bar.colors.ranges; if (destino === get('graficoVentasPorProducto')) grafico = this; graficos.set(destino, this); } render() {} updateOptions(opciones) { this.opciones = opciones; } };
vm.runInThisContext(entrada.script);
const resumenOcultoInicial = get('tarjetaResumenRentabilidad').classList.contains('d-none');
get('mostrarResumenRentabilidad').checked = true;
get('mostrarResumenRentabilidad').eventos.change();
const resumenVisible = !get('tarjetaResumenRentabilidad').classList.contains('d-none');
get('formRentabilidadAgenciaGrafico').eventos.submit = () => { throw new Error('Filtrar no debe enviar el formulario.'); };
get('rutaRentabilidadGrafico').value = 'Sur';
get('filtrarRentabilidadGrafico').eventos.click();
const sur = { ventas: get('totalVentasProductosRentabilidad').textContent, balance: resumen[5].textContent, serie: grafico.opciones.series[0].data };
get('rutaRentabilidadGrafico').value = 'Desconocida';
get('filtrarRentabilidadGrafico').eventos.click();
const vacio = get('tarjetaVentasRentabilidad').classList.contains('d-none') && !get('sinTerminalesRentabilidad').classList.contains('d-none');
get('mostrarResumenRentabilidad').eventos.change();
const resumenOcultoSinTerminales = get('tarjetaResumenRentabilidad').classList.contains('d-none');
get('rutaRentabilidadGrafico').value = 'Norte';
get('filtrarRentabilidadGrafico').eventos.click();
const norte = get('totalVentasProductosRentabilidad').textContent;
pagos[1].checked = true;
pagos[1].eventos.change();
const metas = graficos.get(get('graficoMetrica-meta_venta')).opciones.series;
const diarios = graficos.get(get('graficoMetrica-ventas_por_dia')).opciones.series;
const gastosGrafico = graficos.get(get('graficoMetrica-gastos')).opciones.series;
const balanceGrafico = graficos.get(get('graficoMetrica-balance')).opciones.series;
const resumenVisibleAlFiltrar = !get('tarjetaResumenRentabilidad').classList.contains('d-none');
get('mostrarResumenRentabilidad').checked = false;
get('mostrarResumenRentabilidad').eventos.change();
get('filtrarRentabilidadGrafico').eventos.click();
const resumenOcultoAlFiltrar = get('tarjetaResumenRentabilidad').classList.contains('d-none');
get('mesRentabilidadGrafico').value = '2026-10';
get('mesRentabilidadGrafico').eventos.input();
get('filtrarRentabilidadGrafico').eventos.click();
const resultado = { sur, vacio, norte, metas, diarios, gastosGrafico, balanceGrafico, resumenOcultoInicial, resumenVisible, resumenOcultoSinTerminales, resumenVisibleAlFiltrar, resumenOcultoAlFiltrar, deshabilitado: get('filtrarRentabilidadGrafico').disabled, sinCambio: get('totalVentasProductosRentabilidad').textContent === norte };
const graficoBalance = graficos.get(get('graficoMetrica-balance'));
resultado.coloresBalance = [-1, -0.01, 0, 1].map((importe) => graficoBalance.rangosColor.find((rango) => importe >= rango.from && importe <= rango.to)?.color || 'normal');
resultado.coloresVentasSinCambios = grafico.rangosColor.length === 0;
get('mesRentabilidadGrafico').value = '2026-09';
productos.forEach((campo) => { campo.checked = false; });
get('filtrarRentabilidadGrafico').eventos.click();
resultado.balanceNegativo = graficoBalance.opciones.series[0].data[0];
resultado.totalBalanceRojo = get('totalesGrafico-balance').hijos[0].hijos[0].className.includes('text-danger');
productos.forEach((campo) => { campo.checked = true; });
get('filtrarRentabilidadGrafico').eventos.click();
resultado.totalBalancePositivoNormal = !get('totalesGrafico-balance').hijos[0].hijos[0].className.includes('text-danger');
elementos.clear();
valores.periodoRentabilidadGrafico = 'rango';
valores.mesDesdeRentabilidadGrafico = '2026-09';
valores.mesHastaRentabilidadGrafico = '2026-10';
let peticiones = [];
const avisos = [];
const actualizacionesAviso = [];
let cargasAviso = 0;
let cierresAviso = 0;
globalThis.Swal = {
    fire(opciones) { avisos.push(opciones); opciones.didOpen?.(); },
    showLoading() { cargasAviso++; },
    update(opciones) { actualizacionesAviso.push(opciones.text); },
    close() { cierresAviso++; },
};
globalThis.fetch = async (url) => {
    const mes = url.searchParams.get('mes');
    peticiones.push(mes);
    return { ok: true, json: async () => structuredClone(entrada.meses[mes]) };
};
vm.runInThisContext(entrada.scriptDiferido);
const bloqueadoDuranteCarga = get('filtrarRentabilidadGrafico').disabled;
await new Promise(setImmediate);
resultado.diferido = {
    bloqueadoDuranteCarga, peticiones: [...peticiones],
    ventas: get('totalVentasProductosRentabilidad').textContent,
    balance: resumen[5].textContent,
    categorias: grafico.opciones.xaxis.categories.length,
    habilitado: !get('filtrarRentabilidadGrafico').disabled,
    avisoOculto: get('estadoCargaMesesRentabilidad').classList.contains('d-none'),
};
resultado.modalCarga = { opciones: avisos[0], cargas: cargasAviso, cierres: cierresAviso, progreso: [...actualizacionesAviso] };
get('filtrarRentabilidadGrafico').eventos.click();
resultado.diferido.sinPeticionesAlFiltrar = peticiones.length === 2;
elementos.clear();
peticiones = [];
globalThis.fetch = async (url) => {
    const mes = url.searchParams.get('mes');
    return { ok: mes === '2026-09', json: async () => structuredClone(entrada.meses[mes]) };
};
vm.runInThisContext(entrada.scriptDiferido);
await new Promise(setImmediate);
resultado.errorCarga = avisos.at(-1).icon === 'error'
    && avisos.at(-1).text.includes('2026-10')
    && get('filtrarRentabilidadGrafico').disabled;
delete globalThis.Swal;
elementos.clear();
vm.runInThisContext(entrada.scriptDiferido);
await new Promise(setImmediate);
resultado.errorSinSweetAlert = get('estadoCargaMesesRentabilidad').classList.contains('alert-danger')
    && get('estadoCargaMesesRentabilidad').textContent.includes('2026-10')
    && get('filtrarRentabilidadGrafico').disabled;
process.stdout.write(JSON.stringify(resultado));
JS;
        $ejecutor = 'globalThis.entradaGrafico = JSON.parse((await import("node:fs")).readFileSync(0, "utf8")); await import("data:text/javascript;base64," + Buffer.from(entradaGrafico.codigo).toString("base64"));';
        $proceso = new Process(['node', '--input-type=module', '-e', $ejecutor, public_path('js/pages/grafico-rentabilidad-filtros.js')]);
        $proceso->setInput(json_encode([
            'codigo' => $codigo,
            'script' => $coincidencias[1], 'scriptDiferido' => $scriptDiferido[1],
            'meses' => ['2026-09' => $datos['datosCargados'], '2026-10' => $this->consultar(['mes' => '2026-10'])['datosCargados']],
        ], JSON_THROW_ON_ERROR));
        $proceso->mustRun();
        $resultado = json_decode($proceso->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        foreach (['resumenOcultoInicial', 'resumenVisible', 'resumenOcultoSinTerminales', 'resumenVisibleAlFiltrar', 'resumenOcultoAlFiltrar'] as $comprobacion) {
            $this->assertTrue($resultado[$comprobacion], $comprobacion);
        }
        $this->assertSame('RD$ 500.00', $resultado['sur']['ventas']);
        $this->assertSame('RD$ 430.00', $resultado['sur']['balance']);
        $this->assertSame([500], $resultado['sur']['serie']);
        $this->assertSame('RD$ 1,600.00', $resultado['norte']);
        $this->assertEqualsWithDelta(110 / 0.4, $resultado['metas'][0]['data'][0], 0.00001);
        $this->assertEqualsWithDelta(110 / 0.3, $resultado['metas'][1]['data'][0], 0.00001);
        $this->assertEqualsWithDelta(110 / 0.3 / 30, $resultado['diarios'][1]['data'][0], 0.00001);
        $this->assertSame([110], $resultado['gastosGrafico'][0]['data']);
        $this->assertSame([1095], $resultado['balanceGrafico'][0]['data']);
        $this->assertTrue($resultado['vacio']);
        $this->assertTrue($resultado['deshabilitado']);
        $this->assertTrue($resultado['sinCambio']);
        $this->assertSame(['#f06548', '#f06548', 'normal', 'normal'], $resultado['coloresBalance']);
        $this->assertSame(-130, $resultado['balanceNegativo']);
        $this->assertTrue($resultado['totalBalanceRojo']);
        $this->assertTrue($resultado['totalBalancePositivoNormal']);
        $this->assertTrue($resultado['coloresVentasSinCambios']);
        $this->assertTrue($resultado['errorCarga']);
        $this->assertTrue($resultado['errorSinSweetAlert']);
        $this->assertSame('Cargando los datos del período por mes…', $resultado['modalCarga']['opciones']['text']);
        foreach (['allowOutsideClick', 'allowEscapeKey', 'showConfirmButton', 'showCloseButton'] as $opcion) {
            $this->assertFalse($resultado['modalCarga']['opciones'][$opcion], $opcion);
        }
        $this->assertSame(1, $resultado['modalCarga']['cargas']);
        $this->assertSame(1, $resultado['modalCarga']['cierres']);
        $this->assertStringContainsString('2 de 2 meses completados.', collect($resultado['modalCarga']['progreso'])->last());
        $this->assertSame(['2026-09', '2026-10'], $resultado['diferido']['peticiones']);
        $this->assertSame('RD$ 1,600.00', $resultado['diferido']['ventas']);
        $this->assertSame('RD$ 1,085.00', $resultado['diferido']['balance']);
        $this->assertSame(2, $resultado['diferido']['categorias']);
        foreach (['bloqueadoDuranteCarga', 'habilitado', 'avisoOculto', 'sinPeticionesAlFiltrar'] as $comprobacion) {
            $this->assertTrue($resultado['diferido'][$comprobacion], $comprobacion);
        }
    }

    /** @param array<string, mixed> $datos @param array<string, mixed> $filtros @return array<string, mixed> */
    private function filtrarEnNavegador(array $datos, array $filtros): array
    {
        $codigo = <<<'JS'
import { readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
await import(pathToFileURL(process.argv[1]).href);
const entrada = JSON.parse(readFileSync(0, 'utf8'));
globalThis.fetch = () => { throw new Error('El filtrado local no debe consultar al servidor.'); };
process.stdout.write(JSON.stringify(FiltrosGraficoRentabilidad.filtrar(entrada.datos, entrada.filtros)));
JS;
        $proceso = new Process(['node', '--input-type=module', '-e', $codigo, public_path('js/pages/grafico-rentabilidad-filtros.js')]);
        $proceso->setInput(json_encode(compact('datos', 'filtros'), JSON_THROW_ON_ERROR));
        $proceso->mustRun();

        return json_decode($proceso->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_product_bars_use_the_correct_system_and_support_mixed_terminals(): void
    {
        $this->crearDatos();
        DB::table('vt_usuarios_net')->insert(['agencia_id' => '1001', 'fecha' => '2026-09-01', 'tipo' => 'Tradicional', 'monto' => 9999]);
        DB::table('vt_usuarios_bet')->insert(['agencia_id' => '2001', 'fecha' => '2026-09-01', 'tipo' => 'Tradicional', 'monto' => 9999]);
        DB::table('agencias')->insert(['terminal' => '3001', 'sistema' => 'MIXTO', 'empresa' => 'Empresa A', 'ruta' => 'Mixta', 'estatus' => 1]);
        DB::table('vt_usuarios_bet')->insert(['agencia_id' => '3001', 'fecha' => '2026-09-01', 'tipo' => 'Tradicional', 'monto' => 300]);
        DB::table('vt_usuarios_net')->insert(['agencia_id' => '3001', 'fecha' => '2026-09-01', 'tipo' => 'No Tradicional', 'monto' => 400]);

        $norte = $this->consultar(['mes' => '2026-09', 'ruta' => 'Norte']);
        $this->assertSame(1700.0, $norte['ventasPorProducto']['Tradicional']);
        $this->assertSame($norte['resumen']['venta'], array_sum($norte['ventasPorProducto']));

        $mixta = $this->consultar(['mes' => '2026-09', 'alcance' => 'terminal', 'terminal' => '3001']);
        $this->assertSame(['Tradicional' => 300.0, 'No Tradicional' => 400.0, 'Recarga' => 0.0], $mixta['ventasPorProducto']);
        $this->assertSame(700.0, $mixta['resumen']['venta']);
    }

    public function test_filters_require_a_user_and_reject_invalid_input(): void
    {
        $reglas = (new ConsultarGraficoRentabilidadRequest)->rules();
        foreach ([['mes' => 'invalido'], ['ruta' => ['Norte']], ['ruta' => str_repeat('x', 256)], ['alcance' => 'terminal'], ['productos' => ['Otro']]] as $datos) {
            $this->assertTrue(Validator::make($datos, $reglas)->fails());
        }

        auth()->forgetGuards();
        $this->assertFalse((new ConsultarGraficoRentabilidadRequest)->authorize());
    }

    public function test_month_range_includes_complete_months_for_sales_prizes_and_expenses(): void
    {
        DB::table('agencias')->insert(['terminal' => '01', 'sistema' => 'LOTOBET', 'empresa' => 'Empresa A', 'ruta' => 'Norte']);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '01', 'fecha' => '2026-08-31', 'tipo' => 'Tradicional', 'monto' => 9999],
            ['agencia_id' => '01', 'fecha' => '2026-09-01', 'tipo' => 'Tradicional', 'monto' => 100],
            ['agencia_id' => '01', 'fecha' => '2026-10-31', 'tipo' => 'Recarga', 'monto' => 200],
            ['agencia_id' => '01', 'fecha' => '2026-11-01', 'tipo' => 'Tradicional', 'monto' => 9999],
        ]);
        foreach (['pagos_misma_empresa_bet', 'premios_bet'] as $tabla) {
            DB::table($tabla)->insert([
                ['agencia_id' => '01', 'pagado_agencia_id' => '01', 'fecha' => '2026-08-31', 'monto' => 999],
                ['agencia_id' => '01', 'pagado_agencia_id' => '01', 'fecha' => '2026-09-01', 'monto' => 10],
                ['agencia_id' => '01', 'pagado_agencia_id' => '01', 'fecha' => '2026-10-31', 'monto' => 20],
                ['agencia_id' => '01', 'pagado_agencia_id' => '01', 'fecha' => '2026-11-01', 'monto' => 999],
            ]);
        }
        config(['clasificacion_cuentas.cuentas' => ['6001' => 'VARIABLE']]);
        DB::table('cuentas_contables')->insert(['cuenta' => '6001', 'tipo' => 'Gasto']);
        DB::table('entradas_diario')->insert([
            ['id_viejo' => '01', 'fecha' => '2026-08-31', 'cuenta' => '6001', 'debito' => 999, 'credito' => 0],
            ['id_viejo' => '01', 'fecha' => '2026-09-01', 'cuenta' => '6001', 'debito' => 30, 'credito' => 0],
            ['id_viejo' => '01', 'fecha' => '2026-10-31', 'cuenta' => '6001', 'debito' => 40, 'credito' => 0],
            ['id_viejo' => '01', 'fecha' => '2026-11-01', 'cuenta' => '6001', 'debito' => 999, 'credito' => 0],
        ]);
        DB::table('rentabilidad_agencia_r20')->insert(['terminal' => '*', 'mes' => 'GLOBAL', 'monto' => 10]);

        $datos = $this->consultar(['periodo' => 'rango', 'mes_desde' => '2026-09', 'mes_hasta' => '2026-10', 'empresa' => 'Empresa A', 'ruta' => 'Norte']);

        $this->assertTrue($datos['consultar']);
        $this->assertSame('2026-09 a 2026-10', $datos['etiquetaPeriodo']);
        $this->assertSame(['Tradicional' => 100.0, 'No Tradicional' => 0.0, 'Recarga' => 200.0], $datos['ventasPorProducto']);
        $this->assertSame(['2026-09', '2026-10'], $datos['ventasMensuales']['meses']);
        $this->assertSame([
            ['name' => 'Tradicional', 'data' => [100.0, 0.0]],
            ['name' => 'No Tradicional', 'data' => [0.0, 0.0]],
            ['name' => 'Recarga', 'data' => [0.0, 200.0]],
        ], $datos['ventasMensuales']['series']);
        $this->assertSame(30.0, $datos['resumen']['premios']);
        $this->assertSame(30.0, $datos['resumen']['premios_sacados']);
        $this->assertSame(90.0, $datos['resumen']['gastos']);
        $this->assertSame(20.0, $datos['agenciasResumen']->first()['gastos_fijos']);
        $this->assertSame(180.0, $datos['resumen']['balance']);
        $this->assertEqualsWithDelta(90 / 0.4 / 61, $datos['agenciasResumen']->first()['ventas_por_dia'][60], 0.00001);
        $filtros = ['empresa' => 'Empresa A', 'ruta' => 'Norte', 'alcance' => 'todas', 'terminal' => '', 'productos' => ['Tradicional', 'No Tradicional', 'Recarga'], 'pagos' => [60, 70, 80]];
        $graficos = $this->filtrarEnNavegador($datos['datosCargados'], $filtros)['graficos'];
        $this->assertSame([40, 50], $graficos['gastos'][0]['data']);
        $this->assertSame([10, 20], $graficos['premios'][0]['data']);
        $this->assertSame([10, 20], $graficos['premios_sacados'][0]['data']);
        $this->assertSame([90, 180], $graficos['ingreso_real'][0]['data']);
        $this->assertSame([50, 130], $graficos['balance'][0]['data']);
        foreach ([60, 70, 80] as $indice => $pago) {
            $this->assertEqualsWithDelta(40 / (1 - $pago / 100), $graficos['meta_venta'][$indice]['data'][0], 0.00001);
            $this->assertEqualsWithDelta(50 / (1 - $pago / 100) / 31, $graficos['ventas_por_dia'][$indice]['data'][1], 0.00001);
        }
        $recarga = $this->filtrarEnNavegador($datos['datosCargados'], [...$filtros, 'productos' => ['Recarga'], 'pagos' => []]);
        $this->assertSame([0, 0], $recarga['graficos']['premios'][0]['data']);
        $this->assertSame([0, 200], $recarga['graficos']['ingreso_real'][0]['data']);
        $this->assertSame([-40, 150], $recarga['graficos']['balance'][0]['data']);
        $this->assertSame([], $recarga['graficos']['meta_venta']);
        $this->assertSame([], $recarga['graficos']['ventas_por_dia']);
    }

    public function test_year_and_cross_year_ranges_use_calendar_boundaries_and_monthly_r20(): void
    {
        DB::table('agencias')->insert(['terminal' => '01', 'sistema' => 'LOTOBET']);
        DB::table('rentabilidad_agencia_r20')->insert(['terminal' => '*', 'mes' => 'GLOBAL', 'monto' => 10]);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '01', 'fecha' => '2023-12-31', 'tipo' => 'Tradicional', 'monto' => 100],
            ['agencia_id' => '01', 'fecha' => '2024-01-01', 'tipo' => 'Tradicional', 'monto' => 200],
            ['agencia_id' => '01', 'fecha' => '2024-02-29', 'tipo' => 'Recarga', 'monto' => 30],
            ['agencia_id' => '01', 'fecha' => '2024-12-31', 'tipo' => 'No Tradicional', 'monto' => 300],
            ['agencia_id' => '01', 'fecha' => '2025-01-01', 'tipo' => 'Tradicional', 'monto' => 900],
        ]);
        $anio = $this->consultar(['periodo' => 'anio', 'anio' => 2024]);
        $this->assertTrue($anio['consultar']);
        $this->assertSame('2024', $anio['etiquetaPeriodo']);
        $this->assertSame(['Tradicional' => 200.0, 'No Tradicional' => 300.0, 'Recarga' => 30.0], $anio['ventasPorProducto']);
        $this->assertSame(120.0, $anio['resumen']['gastos']);
        $this->assertSame(410.0, $anio['resumen']['balance']);
        $this->assertCount(12, $anio['ventasMensuales']['meses']);
        $this->assertSame('2024-01', $anio['ventasMensuales']['meses'][0]);
        $this->assertSame('2024-12', $anio['ventasMensuales']['meses'][11]);
        $this->assertSame([200.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0], $anio['ventasMensuales']['series'][0]['data']);
        $this->assertSame(30.0, $anio['ventasMensuales']['series'][2]['data'][1]);
        $this->assertSame(300.0, $anio['ventasMensuales']['series'][1]['data'][11]);
        foreach ($anio['ventasMensuales']['series'] as $serie) {
            $this->assertSame($anio['ventasPorProducto'][$serie['name']], array_sum($serie['data']));
        }
        $this->assertEqualsWithDelta(120 / 0.4 / 366, $anio['agenciasResumen']->first()['ventas_por_dia'][60], 0.00001);
        $graficosAnuales = $this->filtrarEnNavegador($anio['datosCargados'], ['empresa' => '', 'ruta' => '', 'alcance' => 'todas', 'terminal' => '', 'productos' => ['Tradicional', 'No Tradicional', 'Recarga']])['graficos'];
        $this->assertEqualsWithDelta(10 / 0.4 / 29, $graficosAnuales['ventas_por_dia'][0]['data'][1], 0.00001);
        $this->assertEqualsWithDelta(120 / 0.4, array_sum($graficosAnuales['meta_venta'][0]['data']), 0.00001);
        $this->assertEqualsWithDelta($anio['resumen']['balance'], array_sum($graficosAnuales['balance'][0]['data']), 0.00001);

        $rango = $this->consultar(['periodo' => 'rango', 'mes_desde' => '2023-12', 'mes_hasta' => '2024-01']);
        $this->assertSame(300.0, $rango['resumen']['venta']);
        $this->assertSame(20.0, $rango['resumen']['gastos']);
        $this->assertSame(['2023-12', '2024-01'], $rango['ventasMensuales']['meses']);
        $this->assertSame([100.0, 200.0], $rango['ventasMensuales']['series'][0]['data']);
        $this->assertEqualsWithDelta(20 / 0.4 / 62, $rango['agenciasResumen']->first()['ventas_por_dia'][60], 0.00001);

        $dosDiciembres = $this->consultar(['periodo' => 'rango', 'mes_desde' => '2023-12', 'mes_hasta' => '2024-12']);
        $this->assertCount(13, $dosDiciembres['ventasMensuales']['meses']);
        $this->assertSame(100.0, $dosDiciembres['ventasMensuales']['series'][0]['data'][0]);
        $this->assertSame(300.0, $dosDiciembres['ventasMensuales']['series'][1]['data'][12]);
        $this->assertNotSame($dosDiciembres['ventasMensuales']['etiquetas'][0], $dosDiciembres['ventasMensuales']['etiquetas'][12]);
    }

    public function test_period_validation_rejects_incomplete_or_reversed_ranges_and_ignores_unused_fields(): void
    {
        $reglas = (new ConsultarGraficoRentabilidadRequest)->rules();
        foreach ([
            ['periodo' => 'otro'],
            ['periodo' => 'mes'],
            ['periodo' => 'rango', 'mes_desde' => '2026-09'],
            ['periodo' => 'rango', 'mes_hasta' => '2026-09'],
            ['periodo' => 'rango', 'mes_desde' => '2026-10', 'mes_hasta' => '2026-09'],
            ['periodo' => 'rango', 'mes_desde' => '2026-13', 'mes_hasta' => '2026-14'],
            ['periodo' => 'anio'],
            ['periodo' => 'anio', 'anio' => 'abc'],
            ['periodo' => 'anio', 'anio' => 2026.5],
            ['periodo' => 'anio', 'anio' => 1899],
            ['periodo' => 'anio', 'anio' => 10000],
        ] as $datos) {
            $this->assertTrue(Validator::make($datos, $reglas)->fails());
        }

        $this->assertTrue(Validator::make(['periodo' => 'rango', 'mes_desde' => '2026-09', 'mes_hasta' => '2026-09', 'mes' => 'invalido'], $reglas)->passes());
        $this->assertTrue(Validator::make(['periodo' => 'anio', 'anio' => 2024, 'mes' => 'invalido', 'mes_desde' => 'invalido'], $reglas)->passes());
        $this->assertTrue(Validator::make(['periodo' => 'mes', 'mes' => '2026-09', 'anio' => 'invalido'], $reglas)->passes());
        $febrero = $this->consultar(['periodo' => 'mes', 'mes' => '2025-02']);
        $this->assertSame('2025-02', $febrero['etiquetaPeriodo']);
    }

    /** @param array<string, mixed> $filtros @return array<string, mixed> */
    private function consultar(array $filtros, bool $cargarDatos = true): array
    {
        $request = ConsultarGraficoRentabilidadRequest::create('/gerencia/grafico-rentabilidad', 'GET', $filtros);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();

        return app(GraficoRentabilidadController::class)->cargar($request, $cargarDatos)->getData();
    }

    private function crearDatos(): void
    {
        DB::table('agencias')->insert([
            ['terminal' => '1001', 'sistema' => 'LOTOBET', 'empresa' => ' Empresa A ', 'ruta' => ' Norte ', 'estatus' => 1],
            ['terminal' => '1002', 'sistema' => 'LOTOBET', 'empresa' => 'Empresa A', 'ruta' => 'Sur', 'estatus' => 1],
            ['terminal' => '2001', 'sistema' => 'LOTONET', 'empresa' => 'Empresa B', 'ruta' => 'Norte', 'estatus' => 1],
            ['terminal' => '9001', 'sistema' => 'LOTOBET', 'empresa' => 'Empresa C', 'ruta' => 'Inactiva', 'estatus' => 0],
        ]);
        DB::table('catalogo_juegos')->insert([
            ['producto_id' => '1', 'tipo' => 'Tradicional'],
            ['producto_id' => '2', 'tipo' => 'No Tradicional'],
        ]);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '1001', 'fecha' => '2026-09-01', 'tipo' => 'Tradicional', 'monto' => 1000],
            ['agencia_id' => '1001', 'fecha' => '2026-09-01', 'tipo' => 'No Tradicional', 'monto' => 500],
            ['agencia_id' => '1001', 'fecha' => '2026-09-01', 'tipo' => 'Recarga', 'monto' => 100],
            ['agencia_id' => '1002', 'fecha' => '2026-09-01', 'tipo' => 'Tradicional', 'monto' => 500],
            ['agencia_id' => '9001', 'fecha' => '2026-09-01', 'tipo' => 'Tradicional', 'monto' => 9999],
            ['agencia_id' => '1001', 'fecha' => '2026-08-31', 'tipo' => 'Tradicional', 'monto' => 9999],
        ]);
        DB::table('vt_usuarios_net')->insert(['agencia_id' => '2001', 'fecha' => '2026-09-01', 'tipo' => 'Tradicional', 'monto' => 700]);
        DB::table('pagos_misma_empresa_bet')->insert([
            ['agencia_id' => '1001', 'pagado_agencia_id' => '1001', 'fecha' => '2026-09-01', 'producto_id' => '1', 'monto' => 200],
            ['agencia_id' => '1002', 'pagado_agencia_id' => '1001', 'fecha' => '2026-09-01', 'producto_id' => '1', 'monto' => 100],
            ['agencia_id' => '1002', 'pagado_agencia_id' => '1001', 'fecha' => '2026-09-01', 'producto_id' => '2', 'monto' => 50],
            ['agencia_id' => '1001', 'pagado_agencia_id' => '1002', 'fecha' => '2026-09-01', 'producto_id' => '1', 'monto' => 60],
        ]);
        DB::table('pagos_porotra_empresa_bet')->insert(['agencia_id' => '1001', 'fecha' => '2026-09-01', 'producto_id' => '1', 'monto' => 25]);
        DB::table('pagos_misma_empresa_net')->insert(['agencia_id' => '2001', 'pagado_agencia_id' => '2001', 'fecha' => '2026-09-01', 'producto_id' => '1', 'monto' => 100]);
        DB::table('premios_bet')->insert([
            ['agencia_id' => '1001', 'fecha' => '2026-09-01', 'producto_id' => '1', 'monto' => 400],
            ['agencia_id' => '1001', 'fecha' => '2026-09-01', 'producto_id' => '2', 'monto' => 100],
        ]);
        config(['clasificacion_cuentas.cuentas' => ['5001' => 'FIJO', '6001' => 'FIJO']]);
        DB::table('cuentas_contables')->insert([['cuenta' => '5001', 'tipo' => 'Costo'], ['cuenta' => '6001', 'tipo' => 'Gasto']]);
        DB::table('entradas_diario')->insert([
            ['id_viejo' => '1001', 'fecha' => '2026-09-01', 'cuenta' => '5001', 'debito' => 20, 'credito' => 0],
            ['id_viejo' => '1001', 'fecha' => '2026-09-01', 'cuenta' => '6001', 'debito' => 100, 'credito' => 0],
        ]);
        DB::table('rentabilidad_agencia_r20')->insert(['terminal' => '*', 'mes' => 'GLOBAL', 'monto' => 10]);
    }
}
