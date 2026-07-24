<?php

namespace Tests\Feature;

use App\Exceptions\LotobetTokenRequiredException;
use App\Http\Middleware\ExpireInactiveSession;
use App\Http\Middleware\ForcePasswordChange;
use App\Services\AsistenciaTerminalEndpointService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MonitoreoTerminalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-22 10:00:00');
        $this->withoutMiddleware([
            Authenticate::class,
            ForcePasswordChange::class,
            ExpireInactiveSession::class,
        ]);

        Schema::dropIfExists('monitoreo_terminal_comentarios');
        Schema::dropIfExists('agencia_horarios');
        Schema::dropIfExists('coordinador_operador_agencia');
        Schema::dropIfExists('coordinador_operador');
        Schema::dropIfExists('agencias');
        Schema::dropIfExists('tokens');

        Schema::create('agencias', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('agencia')->nullable();
            $table->string('terminal')->nullable();
            $table->string('nombre_agencia')->nullable();
            $table->string('coordinador')->nullable();
            $table->string('sistema')->nullable();
            $table->string('horario_am')->nullable();
            $table->string('horario_pm')->nullable();
            $table->timestamps();
        });

        Schema::create('agencia_horarios', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('agencia_id');
            $table->unsignedTinyInteger('dia_semana');
            $table->string('horario_am')->nullable();
            $table->string('horario_pm')->nullable();
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

        Schema::create('monitoreo_terminal_comentarios', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('agencia_id');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->text('comentario')->nullable();
            $table->date('fecha');
            $table->timestamps();
            $table->unique(['agencia_id', 'fecha']);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_page_contains_date_panel_status_filter_summary_and_comment_modal(): void
    {
        $this->insertAgency([
            'horario_am' => '7:30 AM / 2:00 PM',
            'horario_pm' => '2:00 PM / 9:00 PM',
        ]);

        $this->get(route('tecnologia.monitoreo-terminales.index'))
            ->assertOk()
            ->assertViewIs('tecnologia.monitoreo-terminales')
            ->assertViewHas('horariosMonitoreo', [
                '07:30' => '7:30 AM - Horario Am',
                '14:29' => '2:29 PM - Horario Am',
                '14:30' => '2:30 PM - Horario Pm',
                '21:30' => '9:30 PM - Horario Pm',
            ])
            ->assertSee('Generar monitoreo de asistencia')
            ->assertSee('Estado de asistencia')
            ->assertSee('Faltas')
            ->assertSee('Cumplen')
            ->assertSee('Avisos')
            ->assertSee('Requieren llamada')
            ->assertDontSee('resumenPendientes', false)
            ->assertSee('detalleEstadoTerminalesModal', false)
            ->assertSee('Descargar PDF')
            ->assertSee('Descargar Excel')
            ->assertSee('const exportUrl =', false)
            ->assertSee('Configurar hora')
            ->assertSee('Hora evaluada')
            ->assertSee('fs-6 fw-bold', false)
            ->assertSee('tablaMonitoreoTerminales', false)
            ->assertSee('comentarioTerminalModal', false)
            ->assertSee('LOTOBET_TOKEN_REQUIRED', false)
            ->assertSee('/generar-token', false);
    }

    public function test_page_loads_monitoring_times_from_agency_and_daily_schedules(): void
    {
        $agenciaId = $this->insertAgency([
            'horario_am' => '8:00 AM / 2:00 PM',
            'horario_pm' => null,
        ]);
        DB::table('agencia_horarios')->insert([
            'agencia_id' => $agenciaId,
            'dia_semana' => 7,
            'horario_am' => '9:30 AM / 1:00 PM',
            'horario_pm' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('tecnologia.monitoreo-terminales.index'))
            ->assertOk()
            ->assertViewHas('horariosMonitoreo', [
                '07:30' => '7:30 AM - Horario Am',
                '08:00' => '08:00 AM',
                '09:30' => '09:30 AM',
                '14:29' => '2:29 PM - Horario Am',
                '14:30' => '2:30 PM - Horario Pm',
                '21:30' => '9:30 PM - Horario Pm',
            ])
            ->assertSee('Seleccione una de las horas configuradas en las agencias.', false)
            ->assertSee('inputOptions: monitoringTimes', false);
    }

    public function test_generate_returns_a_controlled_conflict_when_lotobet_token_is_required(): void
    {
        $this->insertAgency();

        $service = $this->mock(AsistenciaTerminalEndpointService::class);
        $service->shouldReceive('terminalesConPonche')
            ->once()
            ->andThrow(new LotobetTokenRequiredException('El token de Lotobet está vencido.'));

        $this->getJson(route('tecnologia.monitoreo-terminales.generar', [
            'fecha_inicio' => '2026-07-22',
            'fecha_fin' => '2026-07-22',
            'hora_monitoreo' => '08:00',
        ]))->assertConflict()
            ->assertJsonPath('code', 'LOTOBET_TOKEN_REQUIRED')
            ->assertJsonPath('message', 'El token de Lotobet está vencido.');
    }

    public function test_generate_marks_agencies_with_punch_as_compliant_and_missing_punch_as_absent(): void
    {
        $agenciaConPonche = $this->insertAgency([
            'terminal' => '001',
            'nombre_agencia' => 'Agencia Central',
        ]);
        $agenciaSinPonche = $this->insertAgency([
            'terminal' => '002',
            'nombre_agencia' => 'Agencia Norte',
        ]);
        $agenciaLotonet = $this->insertAgency([
            'terminal' => '003',
            'nombre_agencia' => 'Agencia Lotonet',
            'sistema' => 'LOTENET',
        ]);
        $coordinadorId = DB::table('coordinador_operador')->insertGetId([
            'nombre' => 'Ana',
            'apellido' => 'Pérez',
            'puesto' => 'coordinador',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('coordinador_operador_agencia')->insert([
            'coordinador_operador_id' => $coordinadorId,
            'agencia_id' => $agenciaConPonche,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('monitoreo_terminal_comentarios')->insert([
            'agencia_id' => $agenciaSinPonche,
            'comentario' => 'Contactar al encargado.',
            'fecha' => '2026-07-22',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = $this->mock(AsistenciaTerminalEndpointService::class);
        $service->shouldReceive('terminalesConPonche')
            ->once()
            ->with('2026-07-22')
            ->andReturn([
                '1' => ['fuente' => 'BET', 'entrada' => '2026-07-22 07:20:00'],
                '2' => ['fuente' => 'BET', 'entrada' => '2026-07-22 08:30:00'],
            ]);

        $response = $this->getJson(route('tecnologia.monitoreo-terminales.generar', [
            'fecha_inicio' => '2026-07-22',
            'fecha_fin' => '2026-07-22',
            'hora_monitoreo' => '08:00',
        ]));

        $response->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('cumplen', 1)
            ->assertJsonPath('faltas', 0)
            ->assertJsonPath('avisos', 0)
            ->assertJsonPath('llamadas', 1)
            ->assertJsonPath('pendientes', 0)
            ->assertJsonFragment([
                'agencia_id' => $agenciaConPonche,
                'coordinador' => 'Ana Pérez',
                'estado' => 'CUMPLE',
            ])
            ->assertJsonFragment([
                'agencia_id' => $agenciaSinPonche,
                'comentario' => 'Contactar al encargado.',
                'fecha_iso' => '2026-07-22',
                'hora_monitoreo' => '08:00 AM',
                'estado' => 'REQUIERE LLAMADA',
            ])
            ->assertJsonMissing(['agencia_id' => $agenciaLotonet]);
    }

    public function test_generate_applies_tolerance_levels_and_requires_a_call_without_a_punch(): void
    {
        $cumpleId = $this->insertAgency(['terminal' => '001']);
        $avisoId = $this->insertAgency(['terminal' => '002']);
        $faltaId = $this->insertAgency(['terminal' => '003']);
        $llamadaId = $this->insertAgency(['terminal' => '004']);

        $service = $this->mock(AsistenciaTerminalEndpointService::class);
        $service->shouldReceive('terminalesConPonche')->once()->andReturn([
            '1' => ['fuente' => 'BET', 'entrada' => '2026-07-22 07:35:00'],
            '2' => ['fuente' => 'BET', 'entrada' => '2026-07-22 07:40:00'],
            '3' => ['fuente' => 'BET', 'entrada' => '2026-07-22 07:45:00'],
        ]);

        $response = $this->getJson(route('tecnologia.monitoreo-terminales.generar', [
            'fecha_inicio' => '2026-07-22',
            'fecha_fin' => '2026-07-22',
            'hora_monitoreo' => '08:00',
        ]));

        $response->assertOk()
            ->assertJsonPath('total', 4)
            ->assertJsonPath('cumplen', 1)
            ->assertJsonPath('avisos', 1)
            ->assertJsonPath('faltas', 1)
            ->assertJsonPath('llamadas', 1)
            ->assertJsonFragment(['agencia_id' => $cumpleId, 'estado' => 'CUMPLE'])
            ->assertJsonFragment([
                'agencia_id' => $avisoId,
                'terminal' => '002',
                'hora_apertura' => '07:30 AM',
                'hora_ponche' => '07:40 AM',
                'minutos_tardanza' => 10,
                'estado' => 'AVISO',
            ])
            ->assertJsonFragment([
                'agencia_id' => $faltaId,
                'minutos_tardanza' => 15,
                'estado' => 'FALTA',
            ])
            ->assertJsonFragment([
                'agencia_id' => $llamadaId,
                'hora_ponche' => null,
                'minutos_tardanza' => null,
                'estado' => 'REQUIERE LLAMADA',
            ]);
    }

    public function test_warning_and_call_details_can_be_downloaded_as_pdf_and_excel(): void
    {
        $registro = [
            'agencia' => '001 - Agencia Central',
            'terminal' => '001',
            'coordinador' => 'Ana Pérez',
            'fecha' => '22/07/2026',
            'hora_apertura' => '07:30 AM',
            'hora_ponche' => '07:40 AM',
            'minutos_tardanza' => 10,
            'estado' => 'AVISO',
        ];

        $this->post(route('tecnologia.monitoreo-terminales.exportar'), [
            'estado' => 'AVISO',
            'formato' => 'pdf',
            'registros' => [$registro],
        ])->assertOk()
            ->assertDownload('monitoreo_aviso_20260722_100000.pdf');

        $registro['estado'] = 'REQUIERE LLAMADA';
        $registro['hora_ponche'] = null;
        $registro['minutos_tardanza'] = null;

        $this->post(route('tecnologia.monitoreo-terminales.exportar'), [
            'estado' => 'REQUIERE LLAMADA',
            'formato' => 'excel',
            'registros' => [$registro],
        ])->assertOk()
            ->assertDownload('monitoreo_requiere_llamada_20260722_100000.xlsx');
    }

    public function test_detail_export_validates_status_format_and_rows(): void
    {
        $this->postJson(route('tecnologia.monitoreo-terminales.exportar'), [
            'estado' => 'FALTA',
            'formato' => 'csv',
            'registros' => [],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['estado', 'formato', 'registros']);
    }

    public function test_generate_keeps_agency_pending_during_the_fifteen_minute_call_window(): void
    {
        $agenciaId = $this->insertAgency();

        $service = $this->mock(AsistenciaTerminalEndpointService::class);
        $service->shouldReceive('terminalesConPonche')->once()->andReturn([]);

        $this->getJson(route('tecnologia.monitoreo-terminales.generar', [
            'fecha_inicio' => '2026-07-22',
            'fecha_fin' => '2026-07-22',
            'hora_monitoreo' => '07:40',
        ]))->assertOk()
            ->assertJsonPath('pendientes', 1)
            ->assertJsonFragment(['agencia_id' => $agenciaId, 'estado' => 'PENDIENTE']);
    }

    public function test_generate_only_evaluates_the_schedule_configured_for_the_day(): void
    {
        $agenciaId = $this->insertAgency([
            'terminal' => '003',
            'horario_am' => null,
        ]);
        DB::table('agencia_horarios')->insert([
            'agencia_id' => $agenciaId,
            'dia_semana' => 4,
            'horario_am' => '11:30 AM / 2:00 PM',
            'horario_pm' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = $this->mock(AsistenciaTerminalEndpointService::class);
        $service->shouldReceive('terminalesConPonche')->once()->andReturn([]);

        $this->getJson(route('tecnologia.monitoreo-terminales.generar', [
            'fecha_inicio' => '2026-07-22',
            'fecha_fin' => '2026-07-22',
            'hora_monitoreo' => '08:00',
        ]))->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_comment_is_created_and_updated_for_the_evaluated_date(): void
    {
        $agenciaId = $this->insertAgency();

        $this->postJson(route('tecnologia.monitoreo-terminales.comentario'), [
            'agencia_id' => $agenciaId,
            'fecha' => '2026-07-21',
            'comentario' => 'Primer detalle',
        ])->assertOk()
            ->assertJsonPath('data.fecha', '21/07/2026');

        $this->postJson(route('tecnologia.monitoreo-terminales.comentario'), [
            'agencia_id' => $agenciaId,
            'fecha' => '2026-07-21',
            'comentario' => 'Detalle actualizado',
        ])->assertOk();

        $this->assertDatabaseHas('monitoreo_terminal_comentarios', [
            'agencia_id' => $agenciaId,
            'comentario' => 'Detalle actualizado',
            'fecha' => '2026-07-21',
        ]);
        $this->assertDatabaseCount('monitoreo_terminal_comentarios', 1);
    }

    public function test_requests_validate_dates_agency_and_comment_length(): void
    {
        $this->getJson(route('tecnologia.monitoreo-terminales.generar', [
            'fecha_inicio' => '2026-06-01',
            'fecha_fin' => '2026-07-22',
            'hora_monitoreo' => '08:00',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_fin');

        $this->getJson(route('tecnologia.monitoreo-terminales.generar', [
            'fecha_inicio' => '2026-07-22',
            'fecha_fin' => '2026-07-22',
            'hora_monitoreo' => '11:00',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('hora_monitoreo');

        $this->postJson(route('tecnologia.monitoreo-terminales.comentario'), [
            'agencia_id' => 999,
            'fecha' => '2026-07-23',
            'comentario' => str_repeat('a', 2001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['agencia_id', 'fecha', 'comentario']);
    }

    public function test_attendance_service_returns_only_lotobet_punches(): void
    {
        Schema::create('tokens', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('token');
            $table->dateTime('fecha');
        });
        DB::table('tokens')->insert([
            'id' => 1,
            'token' => 'token-prueba',
            'fecha' => '2026-07-23 00:00:00',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://ltkadapi.lotobet.bet/*' => Http::response([
                'code' => 200,
                'Content' => [
                    ['agencia' => '0001', 'primer_login' => '2026-07-22 07:20:00'],
                    ['agencia' => '0003', 'primer_login' => null],
                ],
            ]),
        ]);

        $terminales = app(AsistenciaTerminalEndpointService::class)->terminalesConPonche('2026-07-22');

        $this->assertSame('BET', $terminales['1']['fuente']);
        $this->assertSame('2026-07-22 07:20:00', $terminales['1']['entrada']);
        $this->assertArrayNotHasKey('2', $terminales);
        $this->assertArrayNotHasKey('3', $terminales);
        Http::assertSentCount(1);
    }

    public function test_attendance_service_requests_token_when_it_does_not_exist(): void
    {
        Schema::create('tokens', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('token');
            $table->dateTime('fecha');
        });

        $this->expectException(LotobetTokenRequiredException::class);

        app(AsistenciaTerminalEndpointService::class)->terminalesConPonche('2026-07-22');
    }

    /** @param array<string, mixed> $overrides */
    private function insertAgency(array $overrides = []): int
    {
        return DB::table('agencias')->insertGetId(array_merge([
            'agencia' => '001',
            'terminal' => '001',
            'nombre_agencia' => 'Agencia Central',
            'coordinador' => null,
            'sistema' => 'LOTOBET',
            'horario_am' => '7:30 AM / 2:00 PM',
            'horario_pm' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
