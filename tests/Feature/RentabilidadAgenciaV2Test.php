<?php

namespace Tests\Feature;

use App\Http\Controllers\Gerencia\RentabilidadAgenciaV2Controller;
use App\Http\Requests\ConsultarRentabilidadAgenciaV2Request;
use App\Models\User;
use App\ViewPermissionCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RentabilidadAgenciaV2Test extends TestCase
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

    public function test_v2_calculates_actual_daily_income_and_monthly_balance(): void
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
        DB::table('premios_bet')->insert(['agencia_id' => '052232', 'fecha' => '2026-09-01', 'monto' => 999]);
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

        $request = ConsultarRentabilidadAgenciaV2Request::create('/gerencia/rentabilidad-agencia-v2', 'GET', ['mes' => '2026-09', 'terminal' => '052232']);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();
        $data = app(RentabilidadAgenciaV2Controller::class)->index($request)->getData();

        $this->assertSame(['fecha' => '2026-09-01', 'venta' => 200.0, 'premios' => 80.0, 'ingreso_real' => 120.0], $data['dias']->first());
        $this->assertSame(-50.0, $data['dias'][1]['ingreso_real']);
        $this->assertCount(30, $data['dias']);
        $this->assertSame([
            'venta' => 300.0,
            'premios' => 230.0,
            'ingreso_real' => 70.0,
            'costos' => 20.0,
            'gastos' => 15.0,
            'r20' => 5.0,
            'balance' => 35.0,
        ], $data['resumen']);
        $this->assertSame(35.0 / 0.4 / 30, $data['metasDiarias']->first()['meta_diaria']);
        $this->assertSame('/gerencia/rentabilidad-agencia-v2', route('gerencia.rentabilidad-agencia-v2', [], false));
        $item = app(ViewPermissionCatalog::class)->items()->firstWhere('path', '/gerencia/rentabilidad-agencia-v2');
        $this->assertSame('Rentabilidad de Agencia V2', $item['name']);
        $this->assertSame('gerencia.rentabilidad_de_agencia.view', $item['permission']);
    }

    public function test_v2_rejects_invalid_month(): void
    {
        $request = new ConsultarRentabilidadAgenciaV2Request;

        $this->assertTrue(\Illuminate\Support\Facades\Validator::make(['mes' => 'septiembre', 'terminal' => '052232'], $request->rules())->fails());
        $this->assertTrue(\Illuminate\Support\Facades\Validator::make(['mes' => '2026-09', 'alcance' => 'terminal'], $request->rules())->fails());
        $this->assertTrue(\Illuminate\Support\Facades\Validator::make(['productos_filtrados' => 1, 'productos' => ['No válido']], $request->rules())->fails());
    }

    public function test_v2_can_summarize_all_terminals_and_open_each_daily_detail(): void
    {
        DB::table('agencias')->insert([
            ['terminal' => '1001', 'sistema' => 'LOTOBET', 'nombre_agencia' => 'Agencia BET'],
            ['terminal' => '2002', 'sistema' => 'LOTONET', 'nombre_agencia' => 'Agencia NET'],
        ]);
        DB::table('vt_usuarios_bet')->insert(['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 200]);
        DB::table('pagos_misma_empresa_bet')->insert(['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 30]);
        DB::table('pagos_aotra_empresa_bet')->insert(['agencia_id' => '1001', 'fecha' => '2026-09-01', 'monto' => 50]);
        DB::table('vt_usuarios_net')->insert(['agencia_id' => '2002', 'fecha' => '2026-09-01', 'monto' => 300]);
        DB::table('pagos_misma_empresa_net')->insert(['agencia_id' => '2002', 'fecha' => '2026-09-01', 'monto' => 60]);
        DB::table('pagos_aotra_empresa_net')->insert(['agencia_id' => '2002', 'fecha' => '2026-09-01', 'monto' => 40]);
        DB::table('cuentas_contables')->insert(['cuenta' => '6001', 'tipo' => 'Gasto']);
        DB::table('entradas_diario')->insert(['id_viejo' => '1001', 'fecha' => '2026-09-01', 'cuenta' => '6001', 'debito' => 20, 'credito' => 0]);
        DB::table('rentabilidad_agencia_r20')->insert(['terminal' => '*', 'mes' => 'GLOBAL', 'monto' => 5]);

        $request = ConsultarRentabilidadAgenciaV2Request::create('/gerencia/rentabilidad-agencia-v2', 'GET', ['mes' => '2026-09', 'alcance' => 'todas']);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();
        $data = app(RentabilidadAgenciaV2Controller::class)->index($request)->getData();

        $this->assertCount(2, $data['agenciasResumen']);
        $this->assertSame(95.0, $data['agenciasResumen'][0]['balance']);
        $this->assertSame(195.0, $data['agenciasResumen'][1]['balance']);
        $this->assertSame(500.0, $data['resumen']['venta']);
        $this->assertSame(180.0, $data['resumen']['premios']);
        $this->assertSame(290.0, $data['resumen']['balance']);
        $this->assertStringContainsString('<th class="text-end">Premios pagados</th>', file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v2.blade.php')));
        $this->assertStringContainsString('<th>Acción</th>', file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v2.blade.php')));
        $this->assertStringContainsString('<td>{{ $fila[\'terminal\'] }}</td>', file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v2.blade.php')));
        $this->assertStringContainsString('data-terminal="{{ $fila[\'terminal\'] }}">Ver</a>', file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v2.blade.php')));
        $this->assertStringContainsString('id="modalDetalleTerminalRentabilidadV2"', file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v2.blade.php')));
        $this->assertStringContainsString('document.body.appendChild(modalElemento);', file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v2.blade.php')));
        $this->assertStringContainsString('id="detalleTerminalRentabilidadV2"', file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v2.blade.php')));
        $this->assertStringContainsString("'alcance' => 'terminal'", file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v2.blade.php')));
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

        $request = ConsultarRentabilidadAgenciaV2Request::create('/gerencia/rentabilidad-agencia-v2', 'GET', [
            'mes' => '2026-09', 'alcance' => 'todas', 'empresa' => 'Grupo Joselito',
        ]);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();
        $data = app(RentabilidadAgenciaV2Controller::class)->index($request)->getData();

        $this->assertSame('Grupo Joselito', $data['empresa']);
        $this->assertSame(['Grupo Joselito', 'Negosur'], $data['empresas']->all());
        $this->assertSame(['1001'], $data['agenciasResumen']->pluck('terminal')->all());
        $this->assertSame(120.0, $data['resumen']['ingreso_real']);

        $detailRequest = ConsultarRentabilidadAgenciaV2Request::create('/gerencia/rentabilidad-agencia-v2', 'GET', [
            'mes' => '2026-09', 'alcance' => 'terminal', 'terminal' => '2002', 'empresa' => 'Grupo Joselito',
        ]);
        $detailRequest->setContainer(app())->setRedirector(app('redirect'));
        $detailRequest->validateResolved();
        $detail = app(RentabilidadAgenciaV2Controller::class)->index($detailRequest)->getData();

        $this->assertNull($detail['agencia']);
        $this->assertNull($detail['resumen']);
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

        foreach ([
            [['Tradicional'], 100.0, 40.0],
            [['Recarga'], 50.0, 0.0],
            [['Tradicional', 'Recarga'], 150.0, 40.0],
            [[], 0.0, 0.0],
        ] as [$productos, $venta, $premios]) {
            foreach (['todas', 'terminal'] as $alcance) {
                $request = ConsultarRentabilidadAgenciaV2Request::create('/gerencia/rentabilidad-agencia-v2', 'GET', [
                    'mes' => '2026-09', 'alcance' => $alcance, 'terminal' => '1001',
                    'productos_filtrados' => 1, 'productos' => $productos,
                ]);
                $request->setContainer(app())->setRedirector(app('redirect'));
                $request->validateResolved();
                $data = app(RentabilidadAgenciaV2Controller::class)->index($request)->getData();

                $this->assertSame($productos, $data['productosSeleccionados']->all());
                $this->assertSame($venta, $data['resumen']['venta']);
                $this->assertSame($premios, $data['resumen']['premios']);
                $this->assertSame($venta - $premios, $data['resumen']['ingreso_real']);
            }
        }

        $view = file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v2.blade.php'));
        $this->assertStringContainsString('name="productos[]"', $view);
        $this->assertStringContainsString("'productos' => \$productosSeleccionados->all()", $view);
    }
}
