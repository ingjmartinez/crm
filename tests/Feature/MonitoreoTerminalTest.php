<?php

namespace Tests\Feature;

use App\Exceptions\LotobetTokenRequiredException;
use App\Http\Middleware\ExpireInactiveSession;
use App\Http\Middleware\ForcePasswordChange;
use App\Models\Token;
use App\Services\AsistenciaTerminalEndpointService;
use App\Services\Lotobet\LotobetSessionService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
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

        Schema::dropIfExists('monitoreo_terminal_agencia_plazas');
        Schema::dropIfExists('monitoreo_terminal_horarios');
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
            $table->string('empresa')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('ruta')->nullable();
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

        Schema::create('monitoreo_terminal_horarios', function (Blueprint $table): void {
            $table->id();
            $table->string('hora', 5);
            $table->string('tipo_horario', 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['hora', 'tipo_horario']);
        });

        Schema::create('monitoreo_terminal_agencia_plazas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('agencia_id')->unique();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();
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
                '07:30|AM' => '07:30 AM - Horario AM',
                '14:29|AM' => '02:29 PM - Horario AM',
                '14:00|PM' => '02:00 PM - Horario PM',
                '14:30|PM' => '02:30 PM - Horario PM',
                '21:30|PM' => '09:30 PM - Horario PM',
            ])
            ->assertSee('Generar monitoreo de asistencia')
            ->assertSee('Estado de asistencia')
            ->assertSee('Faltas')
            ->assertSee('Cumplen')
            ->assertSee('Avisos')
            ->assertSee('Sin agente de venta')
            ->assertSee('filtro-estado-asistencia-opcion', false)
            ->assertSee('mostrarTodosEstadosButton', false)
            ->assertSee('function applyAttendanceFilter()', false)
            ->assertSee("selectedStates.join('|')", false)
            ->assertSee('table.column(5).search', false)
            ->assertSee('Leyenda de estados')
            ->assertSee('Ponche registrado hasta 5 minutos después de la apertura.')
            ->assertSee('Ponche registrado entre 6 y 10 minutos después de la apertura.')
            ->assertSee('Ponche registrado más de 10 minutos después de la apertura.')
            ->assertSee('No existe ningún ponche registrado para esa terminal en la fecha consultada.')
            ->assertDontSee('PENDIENTE')
            ->assertDontSee('resumenPendientes', false)
            ->assertSee('detalleEstadoTerminalesModal', false)
            ->assertSee('Descargar PDF')
            ->assertSee('Descargar Excel')
            ->assertSee('exportarMonitoreoExcelButton', false)
            ->assertSee('exportarMonitoreoPdfButton', false)
            ->assertSee('compartirMonitoreoPdfButton', false)
            ->assertSee('filtroEmpresaMonitoreo', false)
            ->assertSee('filtroCiudadMonitoreo', false)
            ->assertSee('filtroRutaMonitoreo', false)
            ->assertSee('filtroCoordinadorMonitoreo', false)
            ->assertSee('aplicarFiltrosMonitoreoButton', false)
            ->assertSee('limpiarFiltrosMonitoreoButton', false)
            ->assertSee('Aplicar filtro')
            ->assertSee('showGeneratingMonitoringAlert', false)
            ->assertSee('Generando información')
            ->assertSee('Estamos consultando las asistencias y preparando el monitoreo.')
            ->assertSee('Swal.showLoading()', false)
            ->assertSee('async function validateShareFilters()', false)
            ->assertSee('Antes de compartir debe seleccionar:', false)
            ->assertSee('Debe aplicar los filtros')
            ->assertSee('function updateFilteredSummary()', false)
            ->assertSee("rows.filter(row => row.estado === 'CUMPLE').length", false)
            ->assertSee("rows.filter(row => row.estado === 'SIN AGENTE DE VENTA').length", false)
            ->assertSee('const companyRows = rows.filter', false)
            ->assertSee('const cityRows = companyRows.filter', false)
            ->assertSee('const routeRows = cityRows.filter', false)
            ->assertSee('navigator.share', false)
            ->assertSee('const exportUrl =', false)
            ->assertSee('Configurar hora')
            ->assertSee('Hora evaluada')
            ->assertSee('Agencias en plaza')
            ->assertSee('generarTokenLotobetButton', false)
            ->assertSee('btn btn-warning text-dark fw-semibold shadow-sm', false)
            ->assertSee("generateTokenButton.addEventListener('click'", false)
            ->assertSee('Generar token')
            ->assertSee('¿Qué agencias deseas evaluar?', false)
            ->assertSee('Todas las agencias')
            ->assertSee('Solo agencias en plaza')
            ->assertDontSee('Todas las agencias Lotobet')
            ->assertSee('agenciasPlazaModal', false)
            ->assertSee('reconocerAgenciasPlazaButton', false)
            ->assertSee('Descargar plantilla')
            ->assertSee('Si la selección queda vacía, el monitoreo conservará el comportamiento actual')
            ->assertSee('storeMonitoringTimeUrl', false)
            ->assertSee('deleteMonitoringTimeUrl', false)
            ->assertSee('swalAgregarHorario', false)
            ->assertSee('swalHorariosLista', false)
            ->assertSee('fs-6 fw-bold', false)
            ->assertSee('tablaMonitoreoTerminales', false)
            ->assertSee('comentarioTerminalModal', false)
            ->assertSee('LOTOBET_TOKEN_REQUIRED', false)
            ->assertSee('const generateTokenUrl =', false)
            ->assertSee('parseMonitoringJsonResponse', false)
            ->assertSee('/generar-token', false);
    }

    public function test_manual_token_endpoint_clears_the_expired_session_before_generating_a_new_token(): void
    {
        $service = $this->mock(LotobetSessionService::class);
        $service->shouldReceive('clearSession')->once();
        $service->shouldReceive('generateToken')->once()->andReturn(new Token);

        $this->getJson(route('token.generate'))
            ->assertOk()
            ->assertJsonPath('success', 'Token generado y guardado correctamente.');
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
                '07:30|AM' => '07:30 AM - Horario AM',
                '08:00|AM' => '08:00 AM - Horario AM',
                '09:30|AM' => '09:30 AM - Horario AM',
                '14:29|AM' => '02:29 PM - Horario AM',
                '14:30|PM' => '02:30 PM - Horario PM',
                '21:30|PM' => '09:30 PM - Horario PM',
            ])
            ->assertSee('Seleccione, agregue o elimine los horarios disponibles para el monitoreo.', false);
    }

    public function test_monitoring_times_can_be_added_deleted_and_restored_from_the_modal_endpoints(): void
    {
        $this->insertAgency([
            'horario_am' => '8:00 AM / 2:00 PM',
            'horario_pm' => null,
        ]);

        $this->postJson(route('tecnologia.monitoreo-terminales.horarios.store'), [
            'hora' => '10:15',
            'tipo_horario' => 'AM',
        ])->assertOk()
            ->assertJsonPath('message', 'Horario agregado correctamente.')
            ->assertJsonPath('data.10:15|AM', '10:15 AM - Horario AM');

        $this->postJson(route('tecnologia.monitoreo-terminales.horarios.store'), [
            'hora' => '10:15',
            'tipo_horario' => 'PM',
        ])->assertOk()
            ->assertJsonPath('data.10:15|PM', '10:15 AM - Horario PM');

        $this->deleteJson(route('tecnologia.monitoreo-terminales.horarios.destroy'), [
            'hora' => '08:00',
            'tipo_horario' => 'AM',
        ])->assertOk()
            ->assertJsonMissingPath('data.08:00|AM');

        $this->get(route('tecnologia.monitoreo-terminales.index'))
            ->assertOk()
            ->assertViewHas('horariosMonitoreo', function (array $horarios): bool {
                return isset($horarios['10:15|AM'], $horarios['10:15|PM'])
                    && ! isset($horarios['08:00|AM']);
            });

        $this->postJson(route('tecnologia.monitoreo-terminales.horarios.store'), [
            'hora' => '08:00',
            'tipo_horario' => 'AM',
        ])->assertOk()
            ->assertJsonPath('data.08:00|AM', '08:00 AM - Horario AM');

        $this->assertDatabaseHas('monitoreo_terminal_horarios', [
            'hora' => '08:00',
            'tipo_horario' => 'AM',
            'activo' => true,
        ]);
        $this->assertDatabaseCount('monitoreo_terminal_horarios', 3);
    }

    public function test_monitoring_time_configuration_requires_a_valid_time(): void
    {
        $this->postJson(route('tecnologia.monitoreo-terminales.horarios.store'), [
            'hora' => '25:80',
            'tipo_horario' => 'OTRO',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['hora', 'tipo_horario']);

        $this->deleteJson(route('tecnologia.monitoreo-terminales.horarios.destroy'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hora', 'tipo_horario']);
    }

    public function test_agencies_in_plaza_can_be_recognized_saved_listed_and_cleared_independently(): void
    {
        $primeraAgenciaId = $this->insertAgency([
            'terminal' => '001',
            'nombre_agencia' => 'Agencia Central',
        ]);
        $segundaAgenciaId = $this->insertAgency([
            'terminal' => '002',
            'nombre_agencia' => 'Agencia Norte',
        ]);
        $agenciaLotonetId = $this->insertAgency([
            'terminal' => '003',
            'nombre_agencia' => 'Agencia Lotonet',
            'sistema' => 'LOTENET',
        ]);

        $this->postJson(route('tecnologia.monitoreo-terminales.agencias-plaza.reconocer'), [
            'terminales_manual' => "0001,\n002; 999",
        ])->assertOk()
            ->assertJsonPath('encontradas', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.terminal_normalizada', '1')
            ->assertJsonPath('data.1.terminal_normalizada', '2')
            ->assertJsonPath('no_encontradas.0', '999');

        $this->putJson(route('tecnologia.monitoreo-terminales.agencias-plaza.update'), [
            'agencias' => [$primeraAgenciaId, $segundaAgenciaId],
        ])->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('aplica_filtro', true);

        $this->getJson(route('tecnologia.monitoreo-terminales.agencias-plaza.index'))
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonCount(2, 'data');

        $this->putJson(route('tecnologia.monitoreo-terminales.agencias-plaza.update'), [
            'agencias' => [$agenciaLotonetId],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('agencias.0');

        $this->putJson(route('tecnologia.monitoreo-terminales.agencias-plaza.update'), [
            'agencias' => [],
        ])->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonPath('aplica_filtro', false)
            ->assertJsonPath(
                'message',
                'La selección fue limpiada. El monitoreo analizará todas las agencias.'
            );

        $this->assertDatabaseCount('monitoreo_terminal_agencia_plazas', 0);
    }

    public function test_agencies_in_plaza_template_can_be_downloaded(): void
    {
        $this->get(route('tecnologia.monitoreo-terminales.agencias-plaza.plantilla'))
            ->assertOk()
            ->assertDownload('plantilla_agencias_en_plaza.xlsx');
    }

    public function test_agencies_in_plaza_can_be_recognized_from_a_csv_file(): void
    {
        $this->insertAgency([
            'terminal' => '001',
            'nombre_agencia' => 'Agencia Central',
        ]);
        $archivo = UploadedFile::fake()->createWithContent(
            'agencias-en-plaza.csv',
            "Terminal\n0001\n999\n"
        );

        $this->post(
            route('tecnologia.monitoreo-terminales.agencias-plaza.reconocer'),
            ['archivo' => $archivo],
            ['Accept' => 'application/json']
        )->assertOk()
            ->assertJsonPath('total_filas', 3)
            ->assertJsonPath('terminales_leidas', 2)
            ->assertJsonPath('encontradas', 1)
            ->assertJsonPath('data.0.terminal_normalizada', '1')
            ->assertJsonPath('no_encontradas.0', '999');
    }

    public function test_generate_only_analyzes_configured_agencies_in_plaza(): void
    {
        $agenciaEnPlazaId = $this->insertAgency([
            'terminal' => '001',
            'nombre_agencia' => 'Agencia en plaza',
        ]);
        $agenciaFueraDePlazaId = $this->insertAgency([
            'terminal' => '002',
            'nombre_agencia' => 'Agencia fuera de plaza',
        ]);

        $this->putJson(route('tecnologia.monitoreo-terminales.agencias-plaza.update'), [
            'agencias' => [$agenciaEnPlazaId],
        ])->assertOk();

        $service = $this->mock(AsistenciaTerminalEndpointService::class);
        $service->shouldReceive('terminalesConPonche')
            ->twice()
            ->andReturn([
                '1' => ['fuente' => 'BET', 'entrada' => '2026-07-22 07:25:00'],
                '2' => ['fuente' => 'BET', 'entrada' => '2026-07-22 07:25:00'],
            ]);

        $this->getJson(route('tecnologia.monitoreo-terminales.generar', [
            'fecha_inicio' => '2026-07-22',
            'fecha_fin' => '2026-07-22',
            'hora_monitoreo' => '08:00',
            'tipo_horario' => 'AM',
            'alcance_agencias' => 'plaza',
        ]))->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('alcance_agencias', 'plaza')
            ->assertJsonPath('alcance_label', 'Solo agencias en plaza')
            ->assertJsonFragment(['agencia_id' => $agenciaEnPlazaId])
            ->assertJsonMissing(['agencia_id' => $agenciaFueraDePlazaId]);

        $this->getJson(route('tecnologia.monitoreo-terminales.generar', [
            'fecha_inicio' => '2026-07-22',
            'fecha_fin' => '2026-07-22',
            'hora_monitoreo' => '08:00',
            'tipo_horario' => 'AM',
            'alcance_agencias' => 'todas',
        ]))->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('alcance_agencias', 'todas')
            ->assertJsonPath('alcance_label', 'Todas las agencias')
            ->assertJsonFragment(['agencia_id' => $agenciaEnPlazaId])
            ->assertJsonFragment(['agencia_id' => $agenciaFueraDePlazaId]);
    }

    public function test_generate_rejects_plaza_scope_when_no_agencies_are_configured(): void
    {
        $this->getJson(route('tecnologia.monitoreo-terminales.generar', [
            'fecha_inicio' => '2026-07-22',
            'fecha_fin' => '2026-07-22',
            'hora_monitoreo' => '08:00',
            'tipo_horario' => 'AM',
            'alcance_agencias' => 'plaza',
        ]))->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Debe agregar al menos una agencia en plaza antes de usar este alcance.'
            );
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
            'tipo_horario' => 'AM',
        ]))->assertConflict()
            ->assertJsonPath('code', 'LOTOBET_TOKEN_REQUIRED')
            ->assertJsonPath('message', 'El token de Lotobet está vencido.');
    }

    public function test_generate_marks_agencies_with_punch_as_compliant_and_missing_punch_as_absent(): void
    {
        $agenciaConPonche = $this->insertAgency([
            'terminal' => '001',
            'nombre_agencia' => 'Agencia Central',
            'empresa' => 'Empresa Uno',
            'ciudad' => 'Santo Domingo',
            'ruta' => 'Ruta Metropolitana',
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
            ]);

        $response = $this->getJson(route('tecnologia.monitoreo-terminales.generar', [
            'fecha_inicio' => '2026-07-22',
            'fecha_fin' => '2026-07-22',
            'hora_monitoreo' => '08:00',
            'tipo_horario' => 'AM',
        ]));

        $response->assertJsonFragment([
            'agencia_id' => $agenciaConPonche,
            'empresa' => 'Empresa Uno',
            'ciudad' => 'Santo Domingo',
            'ruta' => 'Ruta Metropolitana',
        ]);

        $response->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('cumplen', 1)
            ->assertJsonPath('faltas', 0)
            ->assertJsonPath('avisos', 0)
            ->assertJsonPath('sin_agente', 1)
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
                'estado' => 'SIN AGENTE DE VENTA',
            ])
            ->assertJsonMissing(['agencia_id' => $agenciaLotonet]);
    }

    public function test_generate_applies_tolerance_levels_and_identifies_terminals_without_an_agent(): void
    {
        $cumpleId = $this->insertAgency(['terminal' => '001']);
        $avisoId = $this->insertAgency(['terminal' => '002']);
        $faltaId = $this->insertAgency(['terminal' => '003']);
        $sinAgenteId = $this->insertAgency(['terminal' => '004']);

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
            'tipo_horario' => 'AM',
        ]));

        $response->assertOk()
            ->assertJsonPath('total', 4)
            ->assertJsonPath('cumplen', 1)
            ->assertJsonPath('avisos', 1)
            ->assertJsonPath('faltas', 1)
            ->assertJsonPath('sin_agente', 1)
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
                'agencia_id' => $sinAgenteId,
                'hora_ponche' => null,
                'minutos_tardanza' => null,
                'estado' => 'SIN AGENTE DE VENTA',
            ]);
    }

    public function test_existing_punch_is_evaluated_even_when_it_is_after_the_selected_time(): void
    {
        $agenciaId = $this->insertAgency([
            'terminal' => '001',
            'horario_am' => '7:30 AM / 2:30 PM',
        ]);

        $service = $this->mock(AsistenciaTerminalEndpointService::class);
        $service->shouldReceive('terminalesConPonche')
            ->once()
            ->with('2026-07-22')
            ->andReturn([
                '1' => ['fuente' => 'BET', 'entrada' => '2026-07-22 08:10:00'],
            ]);

        $this->getJson(route('tecnologia.monitoreo-terminales.generar', [
            'fecha_inicio' => '2026-07-22',
            'fecha_fin' => '2026-07-22',
            'hora_monitoreo' => '07:30',
            'tipo_horario' => 'AM',
        ]))->assertOk()
            ->assertJsonPath('faltas', 1)
            ->assertJsonPath('sin_agente', 0)
            ->assertJsonFragment([
                'agencia_id' => $agenciaId,
                'hora_apertura' => '07:30 AM',
                'hora_ponche' => '08:10 AM',
                'minutos_tardanza' => 40,
                'estado' => 'FALTA',
            ]);
    }

    public function test_generate_uses_the_selected_schedule_type_during_the_overlap(): void
    {
        $agenciaId = $this->insertAgency([
            'horario_am' => '1:30 PM / 2:30 PM',
            'horario_pm' => '2:00 PM / 9:00 PM',
        ]);

        $service = $this->mock(AsistenciaTerminalEndpointService::class);
        $service->shouldReceive('terminalesConPonche')
            ->once()
            ->with('2026-07-21')
            ->andReturn([]);

        $this->getJson(route('tecnologia.monitoreo-terminales.generar', [
            'fecha_inicio' => '2026-07-21',
            'fecha_fin' => '2026-07-21',
            'hora_monitoreo' => '14:20',
            'tipo_horario' => 'PM',
        ]))->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonFragment([
                'agencia_id' => $agenciaId,
                'hora_apertura' => '02:00 PM',
                'hora_monitoreo' => '02:20 PM',
                'tipo_horario' => 'PM',
                'estado' => 'SIN AGENTE DE VENTA',
            ]);
    }

    public function test_pm_monitoring_ignores_the_am_punch_and_uses_the_pm_punch(): void
    {
        $agenciaId = $this->insertAgency([
            'terminal' => '001',
            'horario_am' => '7:30 AM / 2:00 PM',
            'horario_pm' => '2:00 PM / 9:00 PM',
        ]);

        $service = $this->mock(AsistenciaTerminalEndpointService::class);
        $service->shouldReceive('terminalesConPonche')
            ->once()
            ->with('2026-07-21')
            ->andReturn([
                '1' => [
                    'fuente' => 'BET',
                    'entrada' => '2026-07-21 07:25:00',
                    'entradas' => [
                        '2026-07-21 07:25:00',
                        '2026-07-21 14:04:00',
                    ],
                ],
            ]);

        $this->getJson(route('tecnologia.monitoreo-terminales.generar', [
            'fecha_inicio' => '2026-07-21',
            'fecha_fin' => '2026-07-21',
            'hora_monitoreo' => '14:20',
            'tipo_horario' => 'PM',
        ]))->assertOk()
            ->assertJsonPath('cumplen', 1)
            ->assertJsonPath('sin_agente', 0)
            ->assertJsonFragment([
                'agencia_id' => $agenciaId,
                'hora_apertura' => '02:00 PM',
                'hora_ponche' => '02:04 PM',
                'minutos_tardanza' => 4,
                'tipo_horario' => 'PM',
                'estado' => 'CUMPLE',
            ]);
    }

    public function test_pm_monitoring_does_not_count_an_am_punch_as_pm_attendance(): void
    {
        $agenciaId = $this->insertAgency([
            'terminal' => '001',
            'horario_am' => '7:30 AM / 2:00 PM',
            'horario_pm' => '2:00 PM / 9:00 PM',
        ]);

        $service = $this->mock(AsistenciaTerminalEndpointService::class);
        $service->shouldReceive('terminalesConPonche')
            ->once()
            ->with('2026-07-21')
            ->andReturn([
                '1' => [
                    'fuente' => 'BET',
                    'entrada' => '2026-07-21 07:25:00',
                    'entradas' => ['2026-07-21 07:25:00'],
                ],
            ]);

        $this->getJson(route('tecnologia.monitoreo-terminales.generar', [
            'fecha_inicio' => '2026-07-21',
            'fecha_fin' => '2026-07-21',
            'hora_monitoreo' => '14:20',
            'tipo_horario' => 'PM',
        ]))->assertOk()
            ->assertJsonPath('cumplen', 0)
            ->assertJsonPath('sin_agente', 1)
            ->assertJsonFragment([
                'agencia_id' => $agenciaId,
                'hora_ponche' => null,
                'minutos_tardanza' => null,
                'tipo_horario' => 'PM',
                'estado' => 'SIN AGENTE DE VENTA',
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

        $registro['estado'] = 'SIN AGENTE DE VENTA';
        $registro['hora_ponche'] = null;
        $registro['minutos_tardanza'] = null;

        $this->post(route('tecnologia.monitoreo-terminales.exportar'), [
            'estado' => 'SIN AGENTE DE VENTA',
            'formato' => 'excel',
            'registros' => [$registro],
        ])->assertOk()
            ->assertDownload('monitoreo_sin_agente_venta_20260722_100000.xlsx');
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

    public function test_complete_monitoring_report_can_be_downloaded_as_pdf_and_excel(): void
    {
        $registros = [
            [
                'agencia' => '001 - Agencia Central',
                'terminal' => '001',
                'coordinador' => 'Ana Pérez',
                'comentario' => 'Validado por tecnología.',
                'fecha' => '22/07/2026',
                'hora_apertura' => '07:30 AM',
                'hora_ponche' => '07:34 AM',
                'hora_monitoreo' => '07:40 AM',
                'tipo_horario' => 'AM',
                'minutos_tardanza' => 4,
                'estado' => 'CUMPLE',
            ],
            [
                'agencia' => '002 - Agencia Norte',
                'terminal' => '002',
                'coordinador' => 'Luis Pérez',
                'comentario' => null,
                'fecha' => '22/07/2026',
                'hora_apertura' => '07:30 AM',
                'hora_ponche' => '07:42 AM',
                'hora_monitoreo' => '07:40 AM',
                'tipo_horario' => 'AM',
                'minutos_tardanza' => 12,
                'estado' => 'FALTA',
            ],
        ];

        foreach (['pdf', 'excel'] as $formato) {
            $extension = $formato === 'excel' ? 'xlsx' : 'pdf';

            $this->post(route('tecnologia.monitoreo-terminales.exportar'), [
                'estado' => 'TODOS',
                'formato' => $formato,
                'registros' => $registros,
            ])->assertOk()
                ->assertDownload("monitoreo_completo_20260722_100000.{$extension}");
        }
    }

    public function test_generate_marks_an_agency_without_a_punch_as_without_a_sales_agent(): void
    {
        $agenciaId = $this->insertAgency();

        $service = $this->mock(AsistenciaTerminalEndpointService::class);
        $service->shouldReceive('terminalesConPonche')->once()->andReturn([]);

        $this->getJson(route('tecnologia.monitoreo-terminales.generar', [
            'fecha_inicio' => '2026-07-22',
            'fecha_fin' => '2026-07-22',
            'hora_monitoreo' => '07:40',
            'tipo_horario' => 'AM',
        ]))->assertOk()
            ->assertJsonPath('sin_agente', 1)
            ->assertJsonFragment(['agencia_id' => $agenciaId, 'estado' => 'SIN AGENTE DE VENTA']);
    }

    public function test_generate_includes_group_joselito_terminal_without_system_as_lotobet(): void
    {
        $agenciaId = $this->insertAgency([
            'terminal' => '055903',
            'nombre_agencia' => 'Ag Parque Del Este 02',
            'empresa' => 'Grupo Joselito',
            'sistema' => null,
            'horario_am' => '7:30 AM / 2:30 PM',
        ]);
        $agenciaNegosurId = $this->insertAgency([
            'terminal' => '099999',
            'empresa' => 'Negosur',
            'sistema' => null,
        ]);

        $service = $this->mock(AsistenciaTerminalEndpointService::class);
        $service->shouldReceive('terminalesConPonche')->once()->andReturn([]);

        $this->getJson(route('tecnologia.monitoreo-terminales.generar', [
            'fecha_inicio' => '2026-07-22',
            'fecha_fin' => '2026-07-22',
            'hora_monitoreo' => '07:30',
            'tipo_horario' => 'AM',
            'alcance_agencias' => 'todas',
        ]))->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('sin_agente', 1)
            ->assertJsonFragment([
                'agencia_id' => $agenciaId,
                'terminal' => '055903',
                'hora_apertura' => '07:30 AM',
                'estado' => 'SIN AGENTE DE VENTA',
            ])
            ->assertJsonMissing(['agencia_id' => $agenciaNegosurId]);
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
            'tipo_horario' => 'AM',
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
            'tipo_horario' => 'AM',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_fin');

        $this->getJson(route('tecnologia.monitoreo-terminales.generar', [
            'fecha_inicio' => '2026-07-22',
            'fecha_fin' => '2026-07-22',
            'hora_monitoreo' => '11:00',
            'tipo_horario' => 'AM',
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
                    ['agencia' => '0001', 'primer_login' => '2026-07-22 14:04:00'],
                    ['agencia' => '0003', 'primer_login' => null],
                ],
            ]),
        ]);

        $terminales = app(AsistenciaTerminalEndpointService::class)->terminalesConPonche('2026-07-22');

        $this->assertSame('BET', $terminales['1']['fuente']);
        $this->assertSame('2026-07-22 07:20:00', $terminales['1']['entrada']);
        $this->assertSame([
            '2026-07-22 07:20:00',
            '2026-07-22 14:04:00',
        ], $terminales['1']['entradas']);
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

    public function test_attendance_service_requests_a_new_token_when_lotobet_rejects_the_current_one(): void
    {
        Schema::create('tokens', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('token');
            $table->dateTime('fecha');
        });
        DB::table('tokens')->insert([
            'id' => 1,
            'token' => 'token-rechazado',
            'fecha' => '2026-07-23 00:00:00',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://ltkadapi.lotobet.bet/*' => Http::response([
                'code' => 401,
                'message' => 'Token inválido.',
            ]),
        ]);

        $this->expectException(LotobetTokenRequiredException::class);
        $this->expectExceptionMessage('Token inválido.');

        app(AsistenciaTerminalEndpointService::class)->terminalesConPonche('2026-07-22');
    }

    public function test_attendance_service_detects_an_expired_session_even_when_lotobet_returns_http_200(): void
    {
        Schema::create('tokens', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('token');
            $table->dateTime('fecha');
        });
        DB::table('tokens')->insert([
            'id' => 1,
            'token' => 'token-aparentemente-vigente',
            'fecha' => '2026-07-23 00:00:00',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://ltkadapi.lotobet.bet/*' => Http::response([
                'code' => 200,
                'message' => 'Token vencido. Debe iniciar sesión nuevamente y volver a consultar.',
            ]),
        ]);

        $this->expectException(LotobetTokenRequiredException::class);
        $this->expectExceptionMessage('Token vencido. Debe iniciar sesión nuevamente y volver a consultar.');

        app(AsistenciaTerminalEndpointService::class)->terminalesConPonche('2026-07-22');
    }

    /** @param array<string, mixed> $overrides */
    private function insertAgency(array $overrides = []): int
    {
        return DB::table('agencias')->insertGetId(array_merge([
            'agencia' => '001',
            'terminal' => '001',
            'nombre_agencia' => 'Agencia Central',
            'empresa' => 'Empresa Uno',
            'ciudad' => 'Santo Domingo',
            'ruta' => 'Ruta Metropolitana',
            'coordinador' => null,
            'sistema' => 'LOTOBET',
            'horario_am' => '7:30 AM / 2:00 PM',
            'horario_pm' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
