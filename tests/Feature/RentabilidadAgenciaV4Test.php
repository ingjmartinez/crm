<?php

namespace Tests\Feature;

use App\Http\Controllers\Gerencia\RentabilidadAgenciaV4Controller;
use App\Http\Requests\ConsultarRentabilidadAgenciaV4Request;
use App\Http\Requests\GenerarInformeEjecutivoRentabilidadAgenciaV4Request;
use App\Models\User;
use App\ViewPermissionCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class RentabilidadAgenciaV4Test extends TestCase
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
            $table->integer('estatus')->default(1);
        });
        foreach (['vt_usuarios_bet', 'vt_usuarios_net', 'premios_bet', 'premios_net', 'pagos_misma_empresa_bet', 'pagos_aotra_empresa_bet', 'pagos_misma_empresa_net', 'pagos_aotra_empresa_net'] as $tabla) {
            Schema::create($tabla, function (Blueprint $table): void {
                $table->id();
                $table->string('agencia_id');
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

    public function test_v4_calculates_actual_daily_income_and_monthly_balance(): void
    {
        DB::table('agencias')->insert(['terminal' => '052232', 'sistema' => 'LOTOBET', 'nombre_agencia' => 'Agencia prueba']);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '052232', 'fecha' => '2026-09-01', 'monto' => 200],
            ['agencia_id' => '052232', 'fecha' => '2026-09-02', 'monto' => 100],
            ['agencia_id' => '052232', 'fecha' => '2026-08-31', 'monto' => 500],
            ['agencia_id' => '999', 'fecha' => '2026-09-01', 'monto' => 600],
        ]);
        DB::table('pagos_misma_empresa_bet')->insert([
            ['agencia_id' => '052232', 'fecha' => '2026-09-01', 'monto' => 80],
            ['agencia_id' => '999', 'fecha' => '2026-09-01', 'monto' => 300],
        ]);
        DB::table('pagos_aotra_empresa_bet')->insert(['agencia_id' => '052232', 'fecha' => '2026-09-02', 'monto' => 150]);
        DB::table('premios_bet')->insert([
            ['agencia_id' => '052232', 'fecha' => '2026-09-01', 'monto' => 999],
            ['agencia_id' => '052232', 'fecha' => '2026-08-31', 'monto' => 500],
            ['agencia_id' => '999', 'fecha' => '2026-09-01', 'monto' => 600],
        ]);
        DB::table('vt_usuarios_net')->insert(['agencia_id' => '052232', 'fecha' => '2026-09-01', 'monto' => 999]);
        DB::table('cuentas_contables')->insert([
            ['cuenta' => '5001', 'tipo' => 'Costo'],
            ['cuenta' => '6001', 'tipo' => 'Gasto'],
            ['cuenta' => '7001', 'tipo' => 'Gasto'],
        ]);
        DB::table('entradas_diario')->insert([
            ['id_viejo' => '052232', 'fecha' => '2026-09-01', 'cuenta' => '5001', 'debito' => 20, 'credito' => 0],
            ['id_viejo' => '052232', 'fecha' => '2026-09-01', 'cuenta' => '6001', 'debito' => 10, 'credito' => 0],
            ['id_viejo' => '052232', 'fecha' => '2026-09-01', 'cuenta' => '7001', 'debito' => 200, 'credito' => 0],
        ]);
        DB::table('rentabilidad_agencia_r20')->insert(['terminal' => '*', 'mes' => 'GLOBAL', 'monto' => 5]);

        $request = ConsultarRentabilidadAgenciaV4Request::create('/gerencia/rentabilidad-agencia-v4', 'GET', ['mes' => '2026-09', 'terminal' => '052232']);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();
        $data = app(RentabilidadAgenciaV4Controller::class)->index($request)->getData();

        $this->assertSame(['fecha' => '2026-09-01', 'venta' => 200.0, 'premios_sacados' => 999.0, 'ingreso_real' => -799.0], $data['dias']->first());
        $this->assertSame(100.0, $data['dias'][1]['ingreso_real']);
        $this->assertCount(30, $data['dias']);
        $this->assertSame([
            'venta' => 300.0,
            'premios_sacados' => 999.0,
            'ingreso_real' => -699.0,
            'costos' => 20.0,
            'gastos' => 15.0,
            'gastos_fijos' => 5.0,
            'r20' => 5.0,
            'balance' => -734.0,
        ], $data['resumen']);
        $this->assertSame(5.0 / 0.4 / 30, $data['metasDiarias']->first()['meta_diaria']);
        $this->assertSame(5.0 / 0.4, $data['metasDiarias']->first()['meta_venta']);
        $this->assertSame('/gerencia/rentabilidad-agencia-v4', route('gerencia.rentabilidad-agencia-v4', [], false));
        $item = app(ViewPermissionCatalog::class)->items()->firstWhere('path', '/gerencia/rentabilidad-agencia-v4');
        $this->assertSame('Rentabilidad de Agencia V4', $item['name']);
        $this->assertSame('gerencia.rentabilidad_de_agencia.view', $item['permission']);
    }

    public function test_v4_rejects_invalid_month(): void
    {
        $request = new ConsultarRentabilidadAgenciaV4Request;

        $this->assertTrue(\Illuminate\Support\Facades\Validator::make(['mes' => 'septiembre', 'terminal' => '052232'], $request->rules())->fails());
        $this->assertTrue(\Illuminate\Support\Facades\Validator::make(['mes' => '2026-09', 'alcance' => 'terminal'], $request->rules())->fails());
        $this->assertTrue(\Illuminate\Support\Facades\Validator::make(['productos_filtrados' => 1, 'productos' => ['No válido']], $request->rules())->fails());
    }

    public function test_v4_uses_only_drawn_prizes_for_each_system_without_payment_tables(): void
    {
        $this->withoutMiddleware();

        foreach (['pagos_misma_empresa_bet', 'pagos_aotra_empresa_bet', 'pagos_misma_empresa_net', 'pagos_aotra_empresa_net'] as $tabla) {
            Schema::drop($tabla);
        }

        foreach (['LOTOBET' => '1001', 'LOTONET' => '2002', 'LOTENET' => '3003', 'MIXTO' => '4004'] as $sistema => $terminal) {
            DB::table('agencias')->insert(['terminal' => $terminal, 'sistema' => $sistema]);
            DB::table('vt_usuarios_bet')->insert(['agencia_id' => $terminal, 'fecha' => '2026-09-01', 'monto' => 100]);
            DB::table('vt_usuarios_net')->insert(['agencia_id' => $terminal, 'fecha' => '2026-09-01', 'monto' => 200]);
            DB::table('premios_bet')->insert(['agencia_id' => $terminal, 'fecha' => '2026-09-01', 'monto' => 20]);
            DB::table('premios_net')->insert(['agencia_id' => $terminal, 'fecha' => '2026-09-01', 'monto' => 50]);

            $ingresoEsperado = match ($sistema) {
                'LOTOBET' => 80.0,
                'LOTONET', 'LOTENET' => 150.0,
                default => 230.0,
            };

            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('gerencia.rentabilidad-agencia-v4', [
                    'mes' => '2026-09', 'alcance' => 'terminal', 'terminal' => $terminal,
                ]))
                ->assertOk()
                ->assertViewHas('resumen', fn (array $resumen): bool => $resumen['ingreso_real'] === $ingresoEsperado && $resumen['balance'] === $ingresoEsperado)
                ->assertSee('Premios sacados')
                ->assertDontSee('Premios pagados');
        }

        $request = ConsultarRentabilidadAgenciaV4Request::create('/gerencia/rentabilidad-agencia-v4', 'GET', ['mes' => '2026-09']);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();
        $vista = app(RentabilidadAgenciaV4Controller::class)->index($request);

        $this->assertSame('gerencia.rentabilidad-agencia-v4', $vista->name());
        $this->assertSame(610.0, $vista->getData()['resumen']['ingreso_real']);
        $this->assertSame(610.0, $vista->getData()['resumen']['balance']);
    }

    public function test_terminal_detail_link_returns_rendered_modal_content(): void
    {
        $this->withoutMiddleware();
        DB::table('agencias')->insert(['terminal' => '1001', 'sistema' => 'LOTOBET', 'nombre_agencia' => 'Agencia A']);
        DB::table('vt_usuarios_bet')->insert(['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 100]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get(route('gerencia.rentabilidad-agencia-v4', [
            'mes' => '2026-09',
            'alcance' => 'terminal',
            'terminal' => '1001',
            'productos_filtrados' => 1,
            'productos' => ['Tradicional', 'No Tradicional', 'Recarga'],
        ]))->assertOk()->assertSee('id="detalleTerminalRentabilidadV4"', false)->assertSee('Agencia A')->assertDontSee('<html', false);
    }

    public function test_cuentas_button_returns_classified_expenses_and_r20_for_the_selected_terminal(): void
    {
        $this->withoutMiddleware();
        config(['clasificacion_cuentas.cuentas' => ['5001' => 'FIJO', '6001' => 'FIJO', '6002' => 'VARIABLE']]);
        DB::table('agencias')->insert(['terminal' => '052232', 'nombre_agencia' => 'Agencia prueba', 'empresa' => 'Grupo Joselito']);
        DB::table('cuentas_contables')->insert([
            ['cuenta' => '5001', 'descripcion' => 'Costo', 'tipo' => 'Costo'],
            ['cuenta' => '6001', 'descripcion' => 'Alquiler', 'tipo' => 'Gasto'],
            ['cuenta' => '6002', 'descripcion' => 'Incentivo', 'tipo' => 'Gasto'],
            ['cuenta' => '7001', 'descripcion' => 'Excluida', 'tipo' => 'Gasto'],
        ]);
        DB::table('entradas_diario')->insert([
            ['id_viejo' => '052232', 'fecha' => '2026-09-15', 'cuenta' => '5001', 'debito' => 20, 'credito' => 0],
            ['id_viejo' => '052232', 'fecha' => '2026-09-15', 'cuenta' => '6001', 'debito' => 100, 'credito' => 0],
            ['id_viejo' => '052232', 'fecha' => '2026-09-15', 'cuenta' => '6002', 'debito' => 40, 'credito' => 0],
            ['id_viejo' => '052232', 'fecha' => '2026-09-15', 'cuenta' => '7001', 'debito' => 999, 'credito' => 0],
            ['id_viejo' => '052232', 'fecha' => '2026-08-15', 'cuenta' => '6001', 'debito' => 999, 'credito' => 0],
            ['id_viejo' => '9999', 'fecha' => '2026-09-15', 'cuenta' => '6001', 'debito' => 999, 'credito' => 0],
        ]);
        DB::table('rentabilidad_agencia_r20')->insert(['terminal' => '*', 'mes' => 'GLOBAL', 'monto' => 5]);

        $response = $this->get(route('gerencia.rentabilidad-agencia-v4.cuentas', [
            'mes' => '2026-09', 'terminal' => '052232', 'empresa' => 'Grupo Joselito',
        ]))->assertOk();
        $datos = $response->json();

        $this->assertSame('Agencia prueba', $datos['agencia']);
        $this->assertSame(['5001', '6001', '6002', 'R20'], array_column($datos['movimientos'], 'cuenta'));
        $this->assertSame(20.0, (float) $datos['costos']);
        $this->assertSame(105.0, (float) $datos['gastos_fijos']);
        $this->assertSame(40.0, (float) $datos['gastos_variables']);
        $this->assertSame(145.0, (float) $datos['gastos']);
        $this->assertSame('FIJO', $datos['movimientos'][3]['clasificacion']);

        $request = ConsultarRentabilidadAgenciaV4Request::create('/gerencia/rentabilidad-agencia-v4', 'GET', [
            'mes' => '2026-09', 'alcance' => 'terminal', 'terminal' => '052232', 'empresa' => 'Grupo Joselito',
        ]);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();
        $resumen = app(RentabilidadAgenciaV4Controller::class)->index($request)->getData()['resumen'];
        $this->assertSame($resumen['costos'], (float) $datos['costos']);
        $this->assertSame($resumen['gastos_fijos'], (float) $datos['gastos_fijos']);
        $this->assertSame($resumen['gastos'], (float) $datos['gastos']);

        $this->get(route('gerencia.rentabilidad-agencia-v4.cuentas', [
            'mes' => '2026-09', 'terminal' => '052232', 'empresa' => 'Negosur',
        ]))->assertNotFound();
    }

    public function test_v4_can_summarize_all_terminals_and_open_each_daily_detail(): void
    {
        config(['clasificacion_cuentas.cuentas' => ['6001' => 'FIJO']]);
        DB::table('agencias')->insert([
            ['terminal' => '1001', 'sistema' => 'LOTOBET', 'nombre_agencia' => 'Agencia BET'],
            ['terminal' => '2002', 'sistema' => 'LOTONET', 'nombre_agencia' => 'Agencia NET'],
        ]);
        DB::table('vt_usuarios_bet')->insert(['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 200]);
        DB::table('pagos_misma_empresa_bet')->insert(['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 30]);
        DB::table('premios_bet')->insert(['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 45]);
        DB::table('pagos_aotra_empresa_bet')->insert(['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 50]);
        DB::table('vt_usuarios_net')->insert(['agencia_id' => '2002', 'fecha' => '2026-09-01', 'monto' => 300]);
        DB::table('pagos_misma_empresa_net')->insert(['agencia_id' => '2002', 'fecha' => '2026-09-01', 'monto' => 60]);
        DB::table('premios_net')->insert(['agencia_id' => '2002', 'fecha' => '2026-09-01', 'monto' => 75]);
        DB::table('pagos_aotra_empresa_net')->insert(['agencia_id' => '2002', 'fecha' => '2026-09-01', 'monto' => 40]);
        DB::table('cuentas_contables')->insert(['cuenta' => '6001', 'tipo' => 'Gasto']);
        DB::table('entradas_diario')->insert(['id_viejo' => '1001', 'fecha' => '2026-09-01', 'cuenta' => '6001', 'debito' => 20, 'credito' => 0]);
        DB::table('rentabilidad_agencia_r20')->insert(['terminal' => '*', 'mes' => 'GLOBAL', 'monto' => 5]);

        $request = ConsultarRentabilidadAgenciaV4Request::create('/gerencia/rentabilidad-agencia-v4', 'GET', ['mes' => '2026-09', 'alcance' => 'todas']);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();
        $data = app(RentabilidadAgenciaV4Controller::class)->index($request)->getData();

        $this->assertCount(2, $data['agenciasResumen']);
        $this->assertSame(130.0, $data['agenciasResumen'][0]['balance']);
        $this->assertSame(220.0, $data['agenciasResumen'][1]['balance']);
        $this->assertSame(500.0, $data['resumen']['venta']);
        $this->assertArrayNotHasKey('premios', $data['resumen']);
        $this->assertSame(120.0, $data['resumen']['premios_sacados']);
        $this->assertSame(45.0, $data['agenciasResumen'][0]['premios_sacados']);
        $this->assertSame(75.0, $data['agenciasResumen'][1]['premios_sacados']);
        $this->assertSame(25.0, $data['agenciasResumen'][0]['gastos_fijos']);
        $this->assertSame(5.0, $data['agenciasResumen'][1]['gastos_fijos']);
        $this->assertEqualsWithDelta(25.0 / 0.4 / 30, $data['agenciasResumen'][0]['ventas_por_dia'][60], 0.0001);
        $this->assertEqualsWithDelta(25.0 / 0.3, $data['agenciasResumen'][0]['meta_venta'][70], 0.0001);
        $this->assertSame(350.0, $data['resumen']['balance']);
        $this->assertSame(2, $data['resumen']['terminales_balance_positivo']);
        $this->assertSame(0, $data['resumen']['terminales_balance_negativo']);
        $this->assertStringNotContainsString("'Costos' => \$resumen['costos']", file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v4.blade.php')));
        $this->assertStringNotContainsString('<th class="text-end">Premios pagados</th>', file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v4.blade.php')));
        $this->assertStringContainsString('<th class="text-end">Premios sacados</th>', file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v4.blade.php')));
        $this->assertStringContainsString('Terminales con balance positivo', file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v4.blade.php')));
        $this->assertStringContainsString('Terminales con balance negativo', file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v4.blade.php')));
        $this->assertStringContainsString('<th class="text-end">Ventas por día</th>', file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v4.blade.php')));
        $this->assertStringContainsString('<th class="text-end">Meta de venta</th>', file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v4.blade.php')));
        $this->assertStringContainsString('<th>Acción</th>', file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v4.blade.php')));
        $this->assertStringContainsString('<td>{{ $fila[\'terminal\'] }}</td>', file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v4.blade.php')));
        $this->assertStringContainsString('data-terminal="{{ $fila[\'terminal\'] }}">Ver</a>', file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v4.blade.php')));
        $this->assertStringContainsString('id="modalDetalleTerminalRentabilidadV4"', file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v4.blade.php')));
        $this->assertStringContainsString('class="btn btn-sm btn-outline-secondary ver-cuentas-terminal-v4"', file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v4.blade.php')));
        $this->assertStringContainsString('id="modalCuentasRentabilidadV4"', file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v4.blade.php')));
        $this->assertStringContainsString('document.body.appendChild(modalElemento);', file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v4.blade.php')));
        $this->assertStringContainsString("@include('gerencia.rentabilidad-agencia-v4-detalle')", file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v4.blade.php')));
        $this->assertStringContainsString('id="detalleTerminalRentabilidadV4"', file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v4-detalle.blade.php')));
        $viewSource = file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v4.blade.php'));
        $this->assertMatchesRegularExpression('/<th[^>]*>Ventas<\/th><th[^>]*>Ventas por día<\/th><th[^>]*>Meta de venta<\/th><th[^>]*>Premios sacados<\/th><th[^>]*>Ingreso real<\/th><th[^>]*>Gasto fijo<\/th><th[^>]*>Gastos<\/th><th[^>]*>Balance<\/th><th>Acción<\/th>/', $viewSource);
        $this->assertStringContainsString("'alcance' => 'terminal'", file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v4.blade.php')));
    }

    public function test_company_filter_limits_summary_and_terminal_detail(): void
    {
        DB::table('agencias')->insert([
            ['terminal' => '1001', 'sistema' => 'LOTOBET', 'nombre_agencia' => 'Agencia A', 'empresa' => 'Grupo Joselito'],
            ['terminal' => '2002', 'sistema' => 'LOTOBET', 'nombre_agencia' => 'Agencia B', 'empresa' => 'Negosur'],
        ]);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 200],
            ['agencia_id' => '2002', 'fecha' => '2026-09-01', 'monto' => 300],
        ]);
        DB::table('pagos_misma_empresa_bet')->insert([
            ['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 80],
            ['agencia_id' => '2002', 'fecha' => '2026-09-01', 'monto' => 100],
        ]);

        $request = ConsultarRentabilidadAgenciaV4Request::create('/gerencia/rentabilidad-agencia-v4', 'GET', [
            'mes' => '2026-09', 'alcance' => 'todas', 'empresa' => 'Grupo Joselito',
        ]);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();
        $data = app(RentabilidadAgenciaV4Controller::class)->index($request)->getData();

        $this->assertSame('Grupo Joselito', $data['empresa']);
        $this->assertSame(['Grupo Joselito', 'Negosur'], $data['empresas']->all());
        $this->assertSame(['1001'], $data['agenciasResumen']->pluck('terminal')->all());
        $this->assertSame(200.0, $data['resumen']['ingreso_real']);

        $detailRequest = ConsultarRentabilidadAgenciaV4Request::create('/gerencia/rentabilidad-agencia-v4', 'GET', [
            'mes' => '2026-09', 'alcance' => 'terminal', 'terminal' => '2002', 'empresa' => 'Grupo Joselito',
        ]);
        $detailRequest->setContainer(app())->setRedirector(app('redirect'));
        $detailRequest->validateResolved();
        $detail = app(RentabilidadAgenciaV4Controller::class)->index($detailRequest)->getData();

        $this->assertNull($detail['agencia']);
        $this->assertNull($detail['resumen']);
    }

    public function test_v4_counts_positive_and_negative_balances_without_counting_zero(): void
    {
        DB::table('agencias')->insert([
            ['terminal' => '1001', 'sistema' => 'LOTOBET'],
            ['terminal' => '2002', 'sistema' => 'LOTOBET'],
            ['terminal' => '3003', 'sistema' => 'LOTOBET'],
        ]);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 100],
            ['agencia_id' => '2002', 'fecha' => '2026-09-01', 'monto' => 50],
            ['agencia_id' => '3003', 'fecha' => '2026-09-01', 'monto' => 30],
        ]);
        DB::table('premios_bet')->insert([
            ['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 20],
            ['agencia_id' => '2002', 'fecha' => '2026-09-01', 'monto' => 70],
            ['agencia_id' => '3003', 'fecha' => '2026-09-01', 'monto' => 30],
        ]);

        $request = ConsultarRentabilidadAgenciaV4Request::create('/gerencia/rentabilidad-agencia-v4', 'GET', ['mes' => '2026-09', 'alcance' => 'todas']);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();
        $resumen = app(RentabilidadAgenciaV4Controller::class)->index($request)->getData()['resumen'];

        $this->assertSame(3, $resumen['terminales']);
        $this->assertSame(1, $resumen['terminales_balance_positivo']);
        $this->assertSame(1, $resumen['terminales_balance_negativo']);
    }

    public function test_executive_pdf_distinguishes_daily_and_monthly_goal_compliance(): void
    {
        config(['clasificacion_cuentas.cuentas' => ['6001' => 'FIJO']]);
        DB::table('agencias')->insert([
            ['terminal' => '1001', 'sistema' => 'LOTOBET', 'nombre_agencia' => 'Agencia A'],
            ['terminal' => '2002', 'sistema' => 'LOTOBET', 'nombre_agencia' => 'Agencia B'],
        ]);
        DB::table('cuentas_contables')->insert(['cuenta' => '6001', 'tipo' => 'Gasto']);
        DB::table('entradas_diario')->insert([
            ['id_viejo' => '1001', 'fecha' => '2026-09-01', 'cuenta' => '6001', 'debito' => 120, 'credito' => 0],
            ['id_viejo' => '2002', 'fecha' => '2026-09-01', 'cuenta' => '6001', 'debito' => 120, 'credito' => 0],
        ]);
        for ($dia = 1; $dia <= 30; $dia++) {
            DB::table('vt_usuarios_bet')->insert(['agencia_id' => '1001', 'fecha' => sprintf('2026-09-%02d', $dia), 'monto' => 12]);
            if ($dia <= 20) {
                DB::table('vt_usuarios_bet')->insert(['agencia_id' => '2002', 'fecha' => sprintf('2026-09-%02d', $dia), 'monto' => 20]);
            }
        }
        DB::table('premios_bet')->insert([
            ['agencia_id' => '1001', 'fecha' => '2026-09-30', 'monto' => 100],
            ['agencia_id' => '2002', 'fecha' => '2026-09-30', 'monto' => 350],
        ]);

        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->with('a4')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->with('informe_ejecutivo_rentabilidad_agencia_v4_2026-09.pdf')->andReturn(new Response('%PDF-test'));
        Pdf::shouldReceive('loadView')->once()->withArgs(function (string $vista, array $datos): bool {
            $this->assertSame('gerencia.rentabilidad-agencia-v4-informe-ejecutivo-pdf', $vista);
            $informe = $datos['informe'];
            $this->assertSame(2, $informe['terminales_vendieron']);
            $this->assertSame(1, $informe['terminales_cumplieron_diario']);
            $this->assertSame(2, $informe['terminales_cumplieron_mes']);
            $this->assertSame(50, $informe['jornadas_cumplidas']);
            $this->assertSame(60, $informe['jornadas_totales']);
            $this->assertEqualsWithDelta(83.3333, $informe['cumplimiento_diario_porcentaje'], 0.001);
            $this->assertSame(760.0, $informe['ventas']);
            $this->assertSame(310.0, $informe['beneficio_bruto']);
            $this->assertSame(70.0, $informe['balance']);
            $this->assertSame(1, $informe['terminales_balance_positivo']);
            $this->assertSame(1, $informe['terminales_balance_negativo']);
            $this->assertSame(140.0, $informe['balance_positivo']);
            $this->assertSame(-70.0, $informe['balance_negativo']);
            $contenido = view($vista, $datos)->render();
            $this->assertStringNotContainsString('<td>Costos</td>', $contenido);
            $this->assertStringContainsString('Total de gastos, incluido R20', $contenido);

            return true;
        })->andReturn($pdf);

        $request = GenerarInformeEjecutivoRentabilidadAgenciaV4Request::create('/gerencia/rentabilidad-agencia-v4/informe-ejecutivo', 'GET', [
            'mes' => '2026-09', 'porcentaje_pago' => 60,
        ]);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();

        $this->assertSame('%PDF-test', app(RentabilidadAgenciaV4Controller::class)->informeEjecutivo($request)->getContent());
    }

    public function test_executive_pdf_renders_and_requires_a_closed_month(): void
    {
        $this->withoutMiddleware();
        DB::table('agencias')->insert(['terminal' => '1001', 'sistema' => 'LOTOBET', 'nombre_agencia' => 'Agencia de prueba']);
        DB::table('vt_usuarios_bet')->insert(['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 100]);
        DB::table('pagos_misma_empresa_bet')->insert(['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 150]);
        $response = $this->get(route('gerencia.rentabilidad-agencia-v4.informe-ejecutivo', [
            'mes' => '2026-09', 'porcentaje_pago' => 60,
        ]))->assertOk();

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('informe_ejecutivo_rentabilidad_agencia_v4_2026-09.pdf', $response->headers->get('Content-Disposition'));
        $this->assertTrue(\Illuminate\Support\Facades\Validator::make([
            'mes' => now()->format('Y-m'), 'porcentaje_pago' => 60,
        ], (new GenerarInformeEjecutivoRentabilidadAgenciaV4Request)->rules())->fails());
    }

    public function test_product_checks_filter_sales_and_matching_prizes_in_both_scopes(): void
    {
        DB::table('agencias')->insert(['terminal' => '1001', 'sistema' => 'LOTOBET', 'nombre_agencia' => 'Agencia A']);
        DB::table('catalogo_juegos')->insert([
            ['producto_id' => '1', 'tipo' => 'Tradicional'],
            ['producto_id' => '2', 'tipo' => 'No Tradicional'],
        ]);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 100, 'tipo' => 'Tradicional', 'producto_id' => '1'],
            ['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 200, 'tipo' => 'No Tradicional', 'producto_id' => '2'],
            ['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 50, 'tipo' => 'Recarga', 'producto_id' => '-1'],
        ]);
        DB::table('pagos_misma_empresa_bet')->insert([
            ['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 40, 'producto_id' => '1'],
            ['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 70, 'producto_id' => '2'],
        ]);
        DB::table('pagos_aotra_empresa_bet')->insert([
            ['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 10, 'producto_id' => '1'],
            ['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 20, 'producto_id' => '2'],
        ]);
        DB::table('premios_bet')->insert([
            ['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 25, 'producto_id' => '1'],
            ['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 35, 'producto_id' => '2'],
        ]);

        foreach ([
            [['Tradicional'], 100.0, 50.0, 25.0],
            [['No Tradicional'], 200.0, 90.0, 35.0],
            [['Recarga'], 50.0, 0.0, 0.0],
            [['Tradicional', 'Recarga'], 150.0, 50.0, 25.0],
            [['No Tradicional', 'Recarga'], 250.0, 90.0, 35.0],
            [[], 0.0, 0.0, 0.0],
        ] as [$productos, $venta, $premios, $premiosSacados]) {
            foreach (['todas', 'terminal'] as $alcance) {
                $request = ConsultarRentabilidadAgenciaV4Request::create('/gerencia/rentabilidad-agencia-v4', 'GET', [
                    'mes' => '2026-09', 'alcance' => $alcance, 'terminal' => '1001',
                    'productos_filtrados' => 1, 'productos' => $productos,
                ]);
                $request->setContainer(app())->setRedirector(app('redirect'));
                $request->validateResolved();
                $data = app(RentabilidadAgenciaV4Controller::class)->index($request)->getData();

                $this->assertSame($productos, $data['productosSeleccionados']->all());
                $this->assertSame($venta, $data['resumen']['venta']);
                $this->assertArrayNotHasKey('premios', $data['resumen']);
                $this->assertSame($premiosSacados, $data['resumen']['premios_sacados']);
                $this->assertSame($venta - $premiosSacados, $data['resumen']['ingreso_real']);
                $this->assertSame($venta - $premiosSacados, $data['resumen']['balance']);
            }
        }

        $view = file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v4.blade.php'));
        $this->assertStringContainsString('name="productos[]"', $view);
        $this->assertStringContainsString("'productos' => \$productosSeleccionados->all()", $view);
    }
}
