<?php

namespace Tests\Feature;

use App\Http\Middleware\ExpireInactiveSession;
use App\Http\Middleware\ForcePasswordChange;
use App\Models\Token;
use App\Services\AsistenciaAgenteVentaEndpointService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MonitoreoAgenteVentaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-12 10:00:00');
        $this->withoutMiddleware([
            Authenticate::class,
            ForcePasswordChange::class,
            ExpireInactiveSession::class,
        ]);

        Schema::dropIfExists('coordinador_operador_agencia');
        Schema::dropIfExists('coordinador_operador');
        Schema::dropIfExists('empleados');
        Schema::dropIfExists('agencias');
        Schema::dropIfExists('tokens');

        Schema::create('agencias', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('agencia')->nullable();
            $table->string('terminal')->nullable();
            $table->string('nombre_agencia')->nullable();
            $table->string('empresa')->nullable();
            $table->string('coordinador')->nullable();
            $table->string('sistema')->nullable();
            $table->timestamps();
        });

        Schema::create('coordinador_operador', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('apellido');
            $table->string('puesto');
            $table->timestamps();
        });

        Schema::create('coordinador_operador_agencia', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('coordinador_operador_id');
            $table->unsignedInteger('agencia_id');
            $table->timestamps();
        });

        Schema::create('empleados', function (Blueprint $table): void {
            $table->id();
            $table->integer('companyid')->nullable();
            $table->string('cedula')->nullable();
            $table->string('nombres')->nullable();
            $table->string('apellidos')->nullable();
        });

        Schema::create('tokens', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->text('token');
            $table->dateTime('fecha');
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_page_routes_and_technology_card_are_available(): void
    {
        $this->assertTrue(Route::has('tecnologia.monitoreo-agentes-ventas.index'));
        $this->assertTrue(Route::has('tecnologia.monitoreo-agentes-ventas.generar'));
        $this->assertTrue(Route::has('tecnologia.monitoreo-agentes-ventas.exportar'));

        $card = collect(config('module_hubs.tecnologia.items'))->firstWhere('nombre', 'Monitoreo de agentes de ventas');

        $this->assertSame('/tecnologia/monitoreo-agentes-ventas', $card['url']);

        $this->get(route('tecnologia.monitoreo-agentes-ventas.index'))
            ->assertOk()
            ->assertViewIs('tecnologia.monitoreo-agentes-ventas')
            ->assertSee('Ponches de entrada y salida')
            ->assertSee('<option value="todos">Todos</option>', false)
            ->assertSee('Token Lotobet')
            ->assertSee('Sesión Lotonet')
            ->assertSee('Coordinador')
            ->assertSee('form.addEventListener', false)
            ->assertSee('Token de Lotobet requerido')
            ->assertSee("confirmButtonText: 'Generar token'", false)
            ->assertSee("data.code === 'LOTOBET_TOKEN_REQUIRED'", false)
            ->assertSee('showGeneratingReportAlert', false)
            ->assertSee('Estamos preparando el acceso y consultando las asistencias.')
            ->assertSee('showConfirmButton: false', false)
            ->assertSee('generateReport(true)', false)
            ->assertSeeInOrder(['await generateReport();', 'Swal.close();'], false)
            ->assertSee('paginacionAgentes', false)
            ->assertSee('const pageSize = 50;', false)
            ->assertSee('filtered.slice(firstRow, firstRow + pageSize)', false)
            ->assertSee('Página ${currentPage.toLocaleString', false)
            ->assertSee('monitoreo-agentes-ventas\/generar', false);
    }

    public function test_generate_requests_a_lotobet_token_when_it_is_missing(): void
    {
        $this->getJson(route('tecnologia.monitoreo-agentes-ventas.generar', [
            'fecha_inicio' => '2026-08-12',
            'fecha_fin' => '2026-08-12',
            'sistema' => 'lotobet',
        ]))->assertStatus(409)
            ->assertJsonPath('code', 'LOTOBET_TOKEN_REQUIRED')
            ->assertJsonPath('message', 'Debe generar un token de Lotobet para consultar las asistencias.');
    }

    public function test_generate_requests_a_new_token_when_the_lotobet_api_reports_it_as_expired(): void
    {
        Token::query()->create([
            'id' => 1,
            'token' => 'token-aparentemente-vigente',
            'fecha' => now()->addHour(),
        ]);

        Http::fake([
            'https://ltkadapi.lotobet.bet/*' => Http::response([
                'code' => '500',
                'msg' => 'Token vencido. Debe iniciar sesión nuevamente y volver a consultar.',
            ]),
        ]);

        $this->getJson(route('tecnologia.monitoreo-agentes-ventas.generar', [
            'fecha_inicio' => '2026-08-12',
            'fecha_fin' => '2026-08-12',
            'sistema' => 'lotobet',
        ]))->assertConflict()
            ->assertJsonPath('code', 'LOTOBET_TOKEN_REQUIRED')
            ->assertJsonPath('message', 'Token vencido. Debe iniciar sesión nuevamente y volver a consultar.');
    }

    public function test_generate_keeps_the_same_id_separated_by_company_and_terminal(): void
    {
        $joselitoId = $this->insertAgency('001', 'Sucursal Centro', 'Grupo Joselito', 'LOTOBET');
        $negosurId = $this->insertAgency('002', 'Sucursal Sur', 'Negosur', 'LOTONET');
        $this->assignCoordinator($joselitoId, 'Ana', 'Pérez');
        $this->assignCoordinator($negosurId, 'Luis', 'Santos');

        DB::table('empleados')->insert([
            ['companyid' => 168, 'cedula' => '1-0000000-1', 'nombres' => 'Agente', 'apellidos' => 'Joselito'],
            ['companyid' => 169, 'cedula' => '00100000001', 'nombres' => 'Agente', 'apellidos' => 'Negosur'],
        ]);

        $service = $this->mock(AsistenciaAgenteVentaEndpointService::class);
        $service->shouldReceive('prepararAcceso')->once()->with('todos');
        $service->shouldReceive('consultar')->once()->with('2026-08-12', 'todos')->andReturn([
            ['fecha' => '2026-08-12', 'sistema' => 'LOTOBET', 'cedula' => '00100000001', 'nombre' => 'Usuario API Bet', 'terminal' => '1', 'entrada' => '2026-08-12 07:25:00', 'salida' => '2026-08-12 14:02:00'],
            ['fecha' => '2026-08-12', 'sistema' => 'LOTONET', 'cedula' => '00100000001', 'nombre' => 'Usuario API Net', 'terminal' => '2', 'entrada' => '2026-08-12 08:00:00', 'salida' => null],
        ]);

        $this->getJson(route('tecnologia.monitoreo-agentes-ventas.generar', [
            'fecha_inicio' => '2026-08-12',
            'fecha_fin' => '2026-08-12',
            'sistema' => 'todos',
        ]))->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('completos', 1)
            ->assertJsonPath('sin_salida', 1)
            ->assertJsonFragment([
                'agente' => 'Agente Joselito',
                'terminal' => '001',
                'agencia' => '001 - Sucursal Centro',
                'empresa' => 'Grupo Joselito',
                'coordinador' => 'Ana Pérez',
                'estado' => 'COMPLETO',
            ])
            ->assertJsonFragment([
                'agente' => 'Agente Negosur',
                'terminal' => '002',
                'empresa' => 'Negosur',
                'coordinador' => 'Luis Santos',
                'estado' => 'SIN SALIDA',
            ]);
    }

    public function test_service_consolidates_first_entry_and_last_exit_without_mixing_systems(): void
    {
        Token::query()->create([
            'id' => 1,
            'token' => 'token-prueba',
            'fecha' => now()->addHour(),
        ]);

        Http::fake([
            'http://contable.apploteka.com/api/finan/sessions' => Http::response([], 200, [
                'Set-Cookie' => '_orkapi_session=sesion-renovada; path=/; HttpOnly',
            ]),
            'https://ltkadapi.lotobet.bet/*' => Http::response([
                'code' => '200',
                'Content' => [
                    ['agencia' => '0001', 'cedula' => '1-0000000-1', 'usuario' => 'María BET', 'primer_login' => '2026-08-12 07:40:00', 'ultimo_logout' => '2026-08-12 13:50:00'],
                    ['agencia' => '1', 'cedula' => '100000001', 'usuario' => 'María BET', 'primer_login' => '2026-08-12 07:20:00', 'ultimo_logout' => '2026-08-12 14:10:00'],
                ],
            ]),
            'http://contable.apploteka.com/*' => Http::response([
                'code' => '200',
                'data' => ['result' => [
                    ['terminal' => '0001', 'identificacion' => '1-0000000-1', 'username' => 'María NET', 'entrada' => '08:00:00', 'salida' => '15:00:00'],
                ]],
            ]),
        ]);

        $service = app(AsistenciaAgenteVentaEndpointService::class);
        $service->prepararAcceso('todos');
        $registros = $service->consultar('2026-08-12', 'todos');

        $this->assertCount(2, $registros);
        $this->assertSame('LOTOBET', $registros[0]['sistema']);
        $this->assertSame('2026-08-12 07:20:00', $registros[0]['entrada']);
        $this->assertSame('2026-08-12 14:10:00', $registros[0]['salida']);
        $this->assertSame('LOTONET', $registros[1]['sistema']);
        $this->assertSame('08:00:00', $registros[1]['entrada']);
        $this->assertSame('15:00:00', $registros[1]['salida']);
        Http::assertSentCount(3);
        Http::assertSent(fn ($request): bool => $request->url() === 'http://contable.apploteka.com/api/finan/sessions');
    }

    public function test_generate_validates_date_range_and_system(): void
    {
        $this->getJson(route('tecnologia.monitoreo-agentes-ventas.generar', [
            'fecha_inicio' => '2026-08-12',
            'fecha_fin' => '2026-08-12',
            'sistema' => 'otro',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('sistema');

        $this->getJson(route('tecnologia.monitoreo-agentes-ventas.generar', [
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-08-12',
            'sistema' => 'todos',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_fin');
    }

    public function test_report_can_be_exported_to_excel_and_pdf(): void
    {
        $registro = [
            'fecha' => '12/08/2026',
            'fecha_iso' => '2026-08-12',
            'sistema' => 'LOTOBET',
            'cedula' => '00100000001',
            'agente' => 'Agente Prueba',
            'entrada' => '07:20 AM',
            'salida' => '02:10 PM',
            'terminal' => '001',
            'agencia' => '001 - Agencia Prueba',
            'empresa' => 'Grupo Joselito',
            'coordinador' => 'Ana Pérez',
            'estado' => 'COMPLETO',
        ];

        foreach (['excel' => 'xlsx', 'pdf' => 'pdf'] as $formato => $extension) {
            $response = $this->post(route('tecnologia.monitoreo-agentes-ventas.exportar'), [
                'formato' => $formato,
                'registros' => [$registro],
            ]);

            $response->assertOk()->assertDownload("monitoreo_agentes_ventas_20260812_100000.{$extension}");
        }
    }

    private function insertAgency(string $terminal, string $nombre, string $empresa, string $sistema): int
    {
        return DB::table('agencias')->insertGetId([
            'agencia' => $terminal,
            'terminal' => $terminal,
            'nombre_agencia' => $nombre,
            'empresa' => $empresa,
            'coordinador' => null,
            'sistema' => $sistema,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assignCoordinator(int $agenciaId, string $nombre, string $apellido): void
    {
        $coordinadorId = DB::table('coordinador_operador')->insertGetId([
            'nombre' => $nombre,
            'apellido' => $apellido,
            'puesto' => 'coordinador',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('coordinador_operador_agencia')->insert([
            'coordinador_operador_id' => $coordinadorId,
            'agencia_id' => $agenciaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
