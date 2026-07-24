<?php

namespace Tests\Feature;

use App\Http\Controllers\Gerencia\RentabilidadAgenciaController;
use App\Http\Requests\BuscarRentabilidadAgenciaRequest;
use App\Http\Requests\ConsultarRentabilidadAgenciaRequest;
use App\Models\RentabilidadAgencia;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RentabilidadAgenciaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('agencias');
        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('agencia')->nullable();
            $table->string('nombre_agencia')->nullable();
            $table->string('terminal')->nullable();
            $table->string('sistema')->nullable();
            $table->string('empresa')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('ruta')->nullable();
            $table->integer('estatus')->default(1);
            $table->timestamps();
        });
        foreach (['vt_usuarios_bet', 'vt_usuarios_net'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->string('agencia_id')->nullable();
                $table->decimal('monto', 15, 2);
                $table->date('fecha');
            });
        }
        Schema::create('cuentas_contables', function (Blueprint $table): void {
            $table->id();
            $table->string('cuenta')->unique();
            $table->string('tipo')->nullable();
        });
        Schema::create('entradas_diario', function (Blueprint $table): void {
            $table->id();
            $table->date('fecha');
            $table->string('cuenta');
            $table->decimal('debito', 15, 2)->default(0);
            $table->decimal('credito', 15, 2)->default(0);
            $table->string('id_viejo')->nullable();
        });
    }

    public function test_month_filter_rejects_an_invalid_format(): void
    {
        $request = new ConsultarRentabilidadAgenciaRequest;
        $searchRequest = new BuscarRentabilidadAgenciaRequest;

        $this->assertFalse(Validator::make(['mes' => '2026-06'], $request->rules())->fails());
        $this->assertTrue(Validator::make(['mes' => 'junio-2026'], $request->rules())->fails());
        $this->assertFalse(Validator::make(['empresa' => 'Empresa A'], $request->rules())->fails());
        $this->assertTrue(Validator::make(['empresa' => str_repeat('A', 256)], $request->rules())->fails());
        $this->assertFalse(Validator::make(['ciudad' => 'Santo Domingo', 'ruta' => 'Ruta 1'], $request->rules())->fails());
        $this->assertFalse(Validator::make(['mes' => '2026-06', 'buscar' => '9205'], $searchRequest->rules())->fails());
        $this->assertTrue(Validator::make(['mes' => '2026-06', 'buscar' => '9'], $searchRequest->rules())->fails());
    }

    public function test_monthly_query_form_displays_a_sweet_alert_loading_state(): void
    {
        $viewSource = file_get_contents(resource_path('views/gerencia/rentabilidad-agencia.blade.php'));

        $this->assertIsString($viewSource);
        $this->assertStringContainsString('formConsultarRentabilidadAgencia', $viewSource);
        $this->assertStringContainsString('(() => {', $viewSource);
        $this->assertStringContainsString('})();', $viewSource);
        $this->assertStringContainsString("title: 'Consultando datos'", $viewSource);
        $this->assertStringContainsString('Swal.showLoading()', $viewSource);
        $this->assertStringContainsString("'Cumple' : 'No cumple'", $viewSource);
        $this->assertStringContainsString("'badge fs-6'", $viewSource);
        $this->assertStringContainsString("Cumplen: {{ number_format(\$resumenCumplimiento['cumple']) }}", $viewSource);
        $this->assertStringContainsString("No cumplen: {{ number_format(\$resumenCumplimiento['no_cumple']) }}", $viewSource);
        $this->assertStringContainsString('Rentabilidad por ciudad', $viewSource);
        $this->assertStringContainsString('Rentabilidad por ruta', $viewSource);
        $this->assertStringContainsString('id="buscarAgenciaRentabilidad"', $viewSource);
        $this->assertStringContainsString('placeholder="Terminal o nombre de agencia"', $viewSource);
        $this->assertStringContainsString("'buscador' => 'buscarCiudadRentabilidad'", $viewSource);
        $this->assertStringContainsString("'buscador' => 'buscarRutaRentabilidad'", $viewSource);
        $this->assertStringContainsString('agenciasTable.clear().rows.add(agenciasRentabilidadData).search(searchTerm).draw()', $viewSource);
        $this->assertStringContainsString('summaryTable.column(0).search(this.value).draw()', $viewSource);
        $this->assertMatchesRegularExpression('/tableRentabilidadAgencias[\s\S]+?pageLength: 10/', $viewSource);
        $this->assertMatchesRegularExpression('/data-tabla-resumen[\s\S]+?pageLength: 15/', $viewSource);
        $this->assertStringContainsString("query.set('buscar', searchTerm)", $viewSource);
        $this->assertStringContainsString('Buscando en todas las agencias...', $viewSource);
        $this->assertStringContainsString('}, 350);', $viewSource);
        $this->assertStringContainsString('id="modalAgenciasGrupo"', $viewSource);
        $this->assertStringContainsString('data-agencias-grupo', $viewSource);
        $this->assertStringContainsString("addEventListener('show.bs.modal'", $viewSource);
        $this->assertStringContainsString('rows.replaceChildren()', $viewSource);
        $this->assertMatchesRegularExpression('/<\/div>\s*<\/div>\s*<\/div>\s*<div class="modal fade" id="modalAgenciasGrupo"/', $viewSource);
        $this->assertStringNotContainsString('colspan="7"', $viewSource);
    }

    public function test_report_does_not_load_data_until_the_month_is_consulted(): void
    {
        DB::table('agencias')->insert([
            'nombre_agencia' => 'Agencia pendiente de consulta',
            'terminal' => '3001',
            'sistema' => 'LOTOBET',
            'empresa' => 'Empresa C',
            'ciudad' => 'Santo Domingo',
            'ruta' => 'Ruta Central',
            'estatus' => 1,
        ]);

        $this->actingAs(User::factory()->make());
        $request = ConsultarRentabilidadAgenciaRequest::create('/gerencia/rentabilidad-agencia', 'GET');
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $view = app(RentabilidadAgenciaController::class)->index($request);
        $agencias = $view->getData()['agencias'];

        $this->assertFalse($view->getData()['consultaRealizada']);
        $this->assertSame('', $view->getData()['empresaSeleccionada']);
        $this->assertSame('', $view->getData()['ciudadSeleccionada']);
        $this->assertSame('', $view->getData()['rutaSeleccionada']);
        $this->assertSame(['Empresa C'], $view->getData()['empresas']->all());
        $this->assertSame(['Santo Domingo'], $view->getData()['ciudades']->all());
        $this->assertSame(['Ruta Central'], $view->getData()['rutas']->all());
        $this->assertSame(['cumple' => 0, 'no_cumple' => 0], $view->getData()['resumenCumplimiento']);
        $this->assertSame(['cumple' => 0, 'no_cumple' => 0], $view->getData()['resumenCiudades']);
        $this->assertSame(['cumple' => 0, 'no_cumple' => 0], $view->getData()['resumenRutas']);
        $this->assertCount(0, $view->getData()['ciudadesResumen']);
        $this->assertCount(0, $view->getData()['rutasResumen']);
        $this->assertCount(0, $agencias);
        $this->assertCount(0, $view->getData()['agenciasDataTable']);
    }

    public function test_report_can_filter_active_agencies_by_company(): void
    {
        DB::table('agencias')->insert([
            [
                'nombre_agencia' => 'Agencia Empresa A',
                'terminal' => '4001',
                'sistema' => 'LOTOBET',
                'empresa' => 'Empresa A',
                'ciudad' => 'Santo Domingo',
                'ruta' => 'Ruta Sur',
                'estatus' => 1,
            ],
            [
                'nombre_agencia' => 'Agencia Empresa B',
                'terminal' => '4002',
                'sistema' => 'LOTONET',
                'empresa' => 'Empresa B',
                'ciudad' => 'Santiago',
                'ruta' => 'Ruta Norte',
                'estatus' => 1,
            ],
        ]);

        $this->actingAs(User::factory()->make());
        $request = ConsultarRentabilidadAgenciaRequest::create(
            '/gerencia/rentabilidad-agencia',
            'GET',
            [
                'mes' => '2026-06',
                'empresa' => 'Empresa B',
                'ciudad' => 'Santiago',
                'ruta' => 'Ruta Norte',
            ]
        );
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $view = app(RentabilidadAgenciaController::class)->index($request);
        $agencias = $view->getData()['agencias'];

        $this->assertSame('Empresa B', $view->getData()['empresaSeleccionada']);
        $this->assertSame('Santiago', $view->getData()['ciudadSeleccionada']);
        $this->assertSame('Ruta Norte', $view->getData()['rutaSeleccionada']);
        $this->assertSame(['Empresa A', 'Empresa B'], $view->getData()['empresas']->all());
        $this->assertSame(['Santiago', 'Santo Domingo'], $view->getData()['ciudades']->all());
        $this->assertSame(['Ruta Norte', 'Ruta Sur'], $view->getData()['rutas']->all());
        $this->assertSame(['cumple' => 1, 'no_cumple' => 0], $view->getData()['resumenCumplimiento']);
        $this->assertCount(1, $view->getData()['ciudadesResumen']);
        $this->assertSame('Santiago', $view->getData()['ciudadesResumen']->first()['nombre']);
        $this->assertSame('4002', $view->getData()['agenciasDetalleGrupos']['ciudad'][0][0]['terminal']);
        $this->assertSame(['cumple' => 1, 'no_cumple' => 0], $view->getData()['resumenCiudades']);
        $this->assertCount(1, $view->getData()['rutasResumen']);
        $this->assertSame('Ruta Norte', $view->getData()['rutasResumen']->first()['nombre']);
        $this->assertSame(['cumple' => 1, 'no_cumple' => 0], $view->getData()['resumenRutas']);
        $this->assertCount(1, $agencias);
        $this->assertCount(1, $view->getData()['agenciasDataTable']);
        $this->assertSame('4002', $agencias->first()->terminal);
    }

    public function test_report_lists_only_active_agencies_with_name_and_terminal(): void
    {
        DB::table('agencias')->insert([
            [
                'agencia' => 'Nombre alternativo',
                'nombre_agencia' => 'Agencia Central',
                'terminal' => '1001',
                'sistema' => 'LOTOBET',
                'empresa' => 'Empresa A',
                'estatus' => 1,
            ],
            [
                'agencia' => 'Agencia Inactiva',
                'nombre_agencia' => 'Agencia Inactiva',
                'terminal' => '1002',
                'sistema' => 'LOTOBET',
                'empresa' => 'Empresa A',
                'estatus' => 0,
            ],
            [
                'agencia' => 'Agencia Net',
                'nombre_agencia' => 'Agencia Net',
                'terminal' => '2001',
                'sistema' => 'LOTENET',
                'empresa' => 'Empresa B',
                'estatus' => 1,
            ],
        ]);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '1001', 'monto' => 100000, 'fecha' => '2026-06-05'],
            ['agencia_id' => '1001', 'monto' => 50000, 'fecha' => '2026-06-20'],
            ['agencia_id' => '1001', 'monto' => 900000, 'fecha' => '2026-05-20'],
        ]);
        DB::table('vt_usuarios_net')->insert([
            ['agencia_id' => '1001', 'monto' => 700000, 'fecha' => '2026-06-10'],
            ['agencia_id' => '2001', 'monto' => 275000, 'fecha' => '2026-06-15'],
        ]);
        DB::table('cuentas_contables')->insert([
            ['cuenta' => '5001', 'tipo' => 'Costo'],
            ['cuenta' => '6001', 'tipo' => 'Gasto'],
            ['cuenta' => '4001', 'tipo' => 'Ingreso'],
        ]);
        DB::table('entradas_diario')->insert([
            ['fecha' => '2026-06-05', 'cuenta' => '5001', 'debito' => 10000, 'credito' => 1000, 'id_viejo' => '1001'],
            ['fecha' => '2026-06-06', 'cuenta' => '6001', 'debito' => 2500, 'credito' => 500, 'id_viejo' => '1001'],
            ['fecha' => '2026-05-06', 'cuenta' => '5001', 'debito' => 80000, 'credito' => 0, 'id_viejo' => '1001'],
            ['fecha' => '2026-06-07', 'cuenta' => '4001', 'debito' => 0, 'credito' => 50000, 'id_viejo' => '1001'],
            ['fecha' => '2026-06-08', 'cuenta' => '6001', 'debito' => 300000, 'credito' => 0, 'id_viejo' => '2001'],
        ]);

        $this->actingAs(User::factory()->make());
        $request = ConsultarRentabilidadAgenciaRequest::create(
            '/gerencia/rentabilidad-agencia',
            'GET',
            ['mes' => '2026-06']
        );
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $view = app(RentabilidadAgenciaController::class)->index($request);
        $agencias = $view->getData()['agencias'];
        $agenciasPorTerminal = $agencias->keyBy('terminal');

        $this->assertSame('agencias', (new RentabilidadAgencia)->getTable());
        $this->assertSame('2026-06', $view->getData()['mesSeleccionado']);
        $this->assertTrue($view->getData()['consultaRealizada']);
        $this->assertCount(2, $agencias);
        $this->assertCount(2, $view->getData()['agenciasDataTable']);
        $this->assertSame(['cumple' => 1, 'no_cumple' => 1], $view->getData()['resumenCumplimiento']);
        $this->assertSame('Agencia Central', $agenciasPorTerminal->get('1001')->nombre_mostrar);
        $this->assertSame(150000.0, $agenciasPorTerminal->get('1001')->venta_bruta_mes);
        $this->assertSame(275000.0, $agenciasPorTerminal->get('2001')->venta_bruta_mes);
        $this->assertSame(9000.0, $agenciasPorTerminal->get('1001')->costos_mes);
        $this->assertSame(2000.0, $agenciasPorTerminal->get('1001')->gastos_mes);
        $this->assertSame(139000.0, $agenciasPorTerminal->get('1001')->balance_mes);
        $this->assertTrue($agenciasPorTerminal->get('1001')->cumple);
        $this->assertSame(0.0, $agenciasPorTerminal->get('2001')->costos_mes);
        $this->assertSame(300000.0, $agenciasPorTerminal->get('2001')->gastos_mes);
        $this->assertSame(-25000.0, $agenciasPorTerminal->get('2001')->balance_mes);
        $this->assertFalse($agenciasPorTerminal->get('2001')->cumple);

        $ciudadResumen = $view->getData()['ciudadesResumen']->first();
        $this->assertSame('Sin ciudad', $ciudadResumen['nombre']);
        $this->assertSame(2, $ciudadResumen['cantidad_agencias']);
        $this->assertSame(425000.0, $ciudadResumen['venta_bruta_mes']);
        $this->assertSame(9000.0, $ciudadResumen['costos_mes']);
        $this->assertSame(302000.0, $ciudadResumen['gastos_mes']);
        $this->assertSame(114000.0, $ciudadResumen['balance_mes']);
        $this->assertTrue($ciudadResumen['cumple']);
        $this->assertSame(
            ['1001', '2001'],
            collect($ciudadResumen['agencias'])->pluck('terminal')->sort()->values()->all()
        );
        $this->assertSame(['cumple' => 1, 'no_cumple' => 0], $view->getData()['resumenCiudades']);
        $this->assertSame(['cumple' => 1, 'no_cumple' => 0], $view->getData()['resumenRutas']);
    }

    public function test_city_and_route_summaries_provide_all_rows_for_client_side_pagination(): void
    {
        $agencias = [];

        foreach (range(1, 11) as $indice) {
            $agencias[] = [
                'nombre_agencia' => 'Agencia '.$indice,
                'terminal' => (string) (5000 + $indice),
                'sistema' => 'LOTOBET',
                'empresa' => 'Empresa A',
                'ciudad' => 'Ciudad '.$indice,
                'ruta' => 'Ruta '.$indice,
                'estatus' => 1,
            ];
        }

        DB::table('agencias')->insert($agencias);

        $this->actingAs(User::factory()->make());
        $request = ConsultarRentabilidadAgenciaRequest::create(
            '/gerencia/rentabilidad-agencia',
            'GET',
            ['mes' => '2026-06']
        );
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $view = app(RentabilidadAgenciaController::class)->index($request);
        $ciudadesResumen = $view->getData()['ciudadesResumen'];
        $rutasResumen = $view->getData()['rutasResumen'];

        $this->assertCount(11, $ciudadesResumen);
        $this->assertCount(11, $rutasResumen);
        $this->assertSame(['cumple' => 11, 'no_cumple' => 0], $view->getData()['resumenCiudades']);
        $this->assertSame(['cumple' => 11, 'no_cumple' => 0], $view->getData()['resumenRutas']);
    }

    public function test_initial_agency_payload_is_limited_and_remote_search_finds_agencies_outside_it(): void
    {
        $agencias = [];

        foreach (range(1, 205) as $indice) {
            $agencias[] = [
                'nombre_agencia' => sprintf('Agencia %03d', $indice),
                'terminal' => (string) (9000 + $indice),
                'sistema' => 'LOTOBET',
                'empresa' => 'Empresa A',
                'ciudad' => 'Santo Domingo',
                'ruta' => 'Ruta Central',
                'estatus' => 1,
            ];
        }

        DB::table('agencias')->insert($agencias);
        $this->actingAs(User::factory()->make());

        $reportRequest = ConsultarRentabilidadAgenciaRequest::create(
            '/gerencia/rentabilidad-agencia',
            'GET',
            ['mes' => '2026-06']
        );
        $reportRequest->setContainer(app());
        $reportRequest->setRedirector(app('redirect'));
        $reportRequest->validateResolved();

        $view = app(RentabilidadAgenciaController::class)->index($reportRequest);
        $initialData = $view->getData()['agenciasDataTable'];

        $this->assertCount(205, $view->getData()['agencias']);
        $this->assertCount(200, $initialData);
        $this->assertFalse($initialData->contains('terminal', '9205'));

        $searchRequest = BuscarRentabilidadAgenciaRequest::create(
            '/gerencia/rentabilidad-agencia/buscar',
            'GET',
            ['mes' => '2026-06', 'buscar' => '9205']
        );
        $searchRequest->setContainer(app());
        $searchRequest->setRedirector(app('redirect'));
        $searchRequest->validateResolved();

        $response = app(RentabilidadAgenciaController::class)->buscar($searchRequest);
        $responseData = $response->getData(true);

        $this->assertSame(1, $responseData['total']);
        $this->assertSame('9205', $responseData['data'][0]['terminal']);
        $this->assertSame(0, $responseData['data'][0]['balance']);
        $this->assertTrue($responseData['data'][0]['cumple']);
    }
}
