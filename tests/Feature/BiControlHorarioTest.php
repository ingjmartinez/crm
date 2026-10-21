<?php

namespace Tests\Feature;

use App\Exceptions\LotobetTokenRequiredException;
use App\Models\Token;
use App\Services\AsistenciaAgenteVentaEndpointService;
use App\Services\Lotobet\LotobetSessionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BiControlHorarioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();

        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal');
            $table->string('nombre_agencia')->nullable();
            $table->string('agencia')->nullable();
            $table->string('empresa')->nullable();
            $table->string('zona')->nullable();
            $table->string('ruta')->nullable();
            $table->string('sistema')->nullable();
            $table->integer('estatus')->default(1);
        });
        Schema::create('asistencias_bet', function (Blueprint $table): void {
            $table->id('asistencia_id');
            $table->date('fecha');
            $table->string('cedula');
            $table->string('agencia_id');
            $table->dateTime('primer_login')->nullable();
            $table->dateTime('ultimo_login')->nullable();
        });
        Schema::create('asistencias_net', function (Blueprint $table): void {
            $table->id('asistencia_id');
            $table->string('identificacion');
            $table->string('terminal');
            $table->dateTime('entrada')->nullable();
            $table->dateTime('salida')->nullable();
        });
        Schema::create('empleados', function (Blueprint $table): void {
            $table->id();
            $table->string('cedula');
            $table->string('nombres');
            $table->string('apellidos');
            $table->date('fechasalida')->nullable();
        });
        (require database_path('migrations/2026_10_14_125659_create_ponche_por_cedula_ventas_table.php'))->up();
        (require database_path('migrations/2026_10_21_153703_create_bi_horarios_table.php'))->up();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        foreach (['bi_horarios', 'ponche_por_cedula_ventas', 'empleados', 'asistencias_net', 'asistencias_bet', 'agencias'] as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    public function test_it_filters_punches_and_calculates_early_arrival_and_late_exit(): void
    {
        DB::table('agencias')->insert([
            ['terminal' => '0012', 'nombre_agencia' => 'Central', 'empresa' => 'A', 'zona' => 'Norte', 'ruta' => 'R1'],
            ['terminal' => '0013', 'nombre_agencia' => 'Sur', 'empresa' => 'B', 'zona' => 'Sur', 'ruta' => 'R2'],
        ]);
        DB::table('empleados')->insert(['cedula' => '402-4046149-7', 'nombres' => 'Ana', 'apellidos' => 'Pérez']);
        DB::table('asistencias_bet')->insert([
            ['fecha' => '2026-10-10', 'cedula' => '40240461497', 'agencia_id' => '12', 'primer_login' => '2026-10-10 07:45:00', 'ultimo_login' => '2026-10-10 16:50:00'],
            ['fecha' => '2026-10-10', 'cedula' => '00100000001', 'agencia_id' => '13', 'primer_login' => '2026-10-10 08:30:00', 'ultimo_login' => '2026-10-10 17:00:00'],
        ]);
        DB::table('ponche_por_cedula_ventas')->insert(['terminal' => '12', 'usuario_venta' => '40240461497', 'fecha_transaccion' => '2026-10-10 17:10:00', 'tipo' => 'Tradicional', 'estatus' => 'Aplicado']);

        $this->getJson(route('bi.control-horario', [
            'desde' => '2026-10-10', 'hasta' => '2026-10-10', 'entrada' => '08:00', 'salida' => '17:00',
            'sistema' => 'todos', 'empresa' => 'A', 'zona' => 'Norte', 'ruta' => 'R1',
        ]))->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.nombre', 'Ana Pérez')
            ->assertJsonPath('0.minutos_entrada', -15)
            ->assertJsonPath('0.minutos_salida', 15)
            ->assertJsonPath('0.salida_calculada', '2026-10-10 17:15:00');
    }

    public function test_it_rejects_invalid_filters(): void
    {
        $this->getJson(route('bi.control-horario', [
            'desde' => '2026-10-11', 'hasta' => '2026-10-10', 'entrada' => '25:00', 'salida' => '17:00', 'sistema' => 'todos',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['hasta', 'entrada']);
    }

    public function test_employee_terminal_schedule_overrides_general_hours(): void
    {
        DB::table('agencias')->insert(['terminal' => '0012', 'nombre_agencia' => 'Central']);
        DB::table('asistencias_net')->insert(['identificacion' => '40240461497', 'terminal' => '12', 'entrada' => '2026-10-10 08:10:00', 'salida' => '2026-10-10 18:15:00']);

        $this->post(route('bi.horarios.guardar'), [
            'tipo' => 'empleado_terminal', 'cedula' => '402-4046149-7', 'terminal' => '0012',
            'entrada' => '08:30', 'salida' => '18:00',
        ])->assertRedirect();

        $this->getJson(route('bi.control-horario', [
            'desde' => '2026-10-10', 'hasta' => '2026-10-10', 'entrada' => '08:00', 'salida' => '17:00', 'sistema' => 'lotonet',
        ]))->assertOk()->assertJsonPath('0.horario_tipo', 'empleado_terminal')
            ->assertJsonPath('0.minutos_entrada', -20)
            ->assertJsonPath('0.minutos_salida', 15);
    }

    public function test_employee_schedule_takes_precedence_over_terminal_and_can_be_deleted(): void
    {
        DB::table('agencias')->insert(['terminal' => '12', 'nombre_agencia' => 'Central']);
        DB::table('asistencias_net')->insert(['identificacion' => '40240461497', 'terminal' => '12', 'entrada' => '2026-10-10 08:10:00', 'salida' => '2026-10-10 17:10:00']);
        $this->post(route('bi.horarios.guardar'), ['tipo' => 'terminal', 'terminal' => '12', 'entrada' => '08:00', 'salida' => '17:00'])->assertRedirect();
        $this->post(route('bi.horarios.guardar'), ['tipo' => 'empleado', 'cedula' => '40240461497', 'entrada' => '08:30', 'salida' => '17:30'])->assertRedirect();
        $filters = ['desde' => '2026-10-10', 'hasta' => '2026-10-10', 'entrada' => '09:00', 'salida' => '18:00', 'sistema' => 'lotonet'];

        $this->getJson(route('bi.control-horario', $filters))->assertJsonPath('0.horario_tipo', 'empleado')
            ->assertJsonPath('0.minutos_entrada', -20);

        $this->delete(route('bi.horarios.eliminar', DB::table('bi_horarios')->where('tipo', 'empleado')->value('id')))->assertRedirect();
        $this->getJson(route('bi.control-horario', $filters))->assertJsonPath('0.horario_tipo', 'terminal')
            ->assertJsonPath('0.minutos_entrada', 10);
    }

    public function test_live_watch_uses_endpoints_and_marks_active_terminals_without_morning_punch_as_unstaffed(): void
    {
        Carbon::setTestNow('2026-10-21 09:00:00');
        DB::table('agencias')->insert([
            ['terminal' => '0012', 'nombre_agencia' => 'Central', 'empresa' => 'A', 'zona' => 'Norte', 'ruta' => 'R1', 'sistema' => 'LOTOBET', 'estatus' => 1],
            ['terminal' => '0013', 'nombre_agencia' => 'Norte', 'empresa' => 'A', 'zona' => 'Norte', 'ruta' => 'R1', 'sistema' => 'LOTENET', 'estatus' => 1],
            ['terminal' => '0014', 'nombre_agencia' => 'Cerrada', 'empresa' => 'A', 'zona' => 'Norte', 'ruta' => 'R1', 'sistema' => 'LOTOBET', 'estatus' => 0],
            ['terminal' => '0015', 'nombre_agencia' => 'Este', 'empresa' => 'A', 'zona' => 'Norte', 'ruta' => 'R1', 'sistema' => 'LOTOBET', 'estatus' => 1],
        ]);
        $service = $this->mock(AsistenciaAgenteVentaEndpointService::class);
        $service->shouldReceive('prepararAcceso')->once()->with('todos');
        $service->shouldReceive('consultar')->once()->with('2026-10-21', 'todos')->andReturn([
            ['terminal' => '12', 'cedula' => '40240461497', 'nombre' => 'Ana', 'entrada' => '08:10:00'],
            ['terminal' => '12', 'cedula' => '40240461497', 'nombre' => 'Ana', 'entrada' => '07:50:00'],
            ['terminal' => '13', 'cedula' => '00100000001', 'nombre' => 'Luis', 'entrada' => '08:20:00'],
            ['terminal' => '15', 'cedula' => '00100000002', 'nombre' => 'Tarde', 'entrada' => '13:00:00'],
        ]);

        $response = $this->getJson(route('bi.vigilancia-horario', ['entrada' => '08:00', 'sistema' => 'todos', 'empresa' => 'A']));

        $response->assertOk()->assertJsonPath('fecha', '2026-10-21')->assertJsonCount(3, 'terminales')
            ->assertJsonPath('terminales.0.terminal', '15')
            ->assertJsonPath('terminales.0.estado', 'sin_personal')
            ->assertJsonPath('terminales.1.terminal', '13')
            ->assertJsonPath('terminales.1.minutos_entrada', 20)
            ->assertJsonPath('terminales.2.terminal', '12')
            ->assertJsonPath('terminales.2.minutos_entrada', -10);
    }

    public function test_live_watch_reports_endpoint_failure_instead_of_marking_every_terminal_unstaffed(): void
    {
        $service = $this->mock(AsistenciaAgenteVentaEndpointService::class);
        $service->shouldReceive('prepararAcceso')->once()->andThrow(new LotobetTokenRequiredException('Token vencido.'));

        $this->getJson(route('bi.vigilancia-horario', ['entrada' => '08:00', 'sistema' => 'lotobet']))
            ->assertStatus(409)->assertJsonPath('code', 'LOTOBET_TOKEN_REQUIRED')
            ->assertJsonMissingPath('terminales');
    }

    public function test_live_watch_applies_terminal_schedule_and_system_filter(): void
    {
        Carbon::setTestNow('2026-10-21 09:00:00');
        DB::table('agencias')->insert([
            ['terminal' => '0012', 'nombre_agencia' => 'Bet', 'empresa' => 'A', 'zona' => 'Norte', 'ruta' => 'R1', 'sistema' => 'LOTOBET', 'estatus' => 1],
            ['terminal' => '0013', 'nombre_agencia' => 'Net', 'empresa' => 'A', 'zona' => 'Norte', 'ruta' => 'R1', 'sistema' => 'LOTENET', 'estatus' => 1],
        ]);
        $this->post(route('bi.horarios.guardar'), ['tipo' => 'terminal', 'terminal' => '13', 'entrada' => '08:30', 'salida' => '17:00'])->assertRedirect();
        $service = $this->mock(AsistenciaAgenteVentaEndpointService::class);
        $service->shouldReceive('prepararAcceso')->once()->with('lotonet');
        $service->shouldReceive('consultar')->once()->with('2026-10-21', 'lotonet')->andReturn([
            ['terminal' => '13', 'cedula' => '00100000001', 'nombre' => 'Luis', 'entrada' => '08:10:00'],
        ]);

        $this->getJson(route('bi.vigilancia-horario', ['entrada' => '08:00', 'sistema' => 'lotonet', 'zona' => 'Norte']))
            ->assertOk()->assertJsonCount(1, 'terminales')
            ->assertJsonPath('terminales.0.terminal', '13')
            ->assertJsonPath('terminales.0.minutos_entrada', -20)
            ->assertJsonPath('terminales.0.estado', 'temprano');
    }

    public function test_lotobet_token_action_can_regenerate_the_token_for_live_watch(): void
    {
        $service = $this->mock(LotobetSessionService::class);
        $service->shouldReceive('clearSession')->once();
        $service->shouldReceive('generateToken')->once()->andReturn(new Token);

        $this->getJson(route('token.generate'))
            ->assertOk()
            ->assertJsonPath('success', 'Token generado y guardado correctamente.');
    }
}
