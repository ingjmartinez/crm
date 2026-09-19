<?php

namespace Tests\Feature;

use App\Services\RecursosHumanos\NominaDomingoService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class NominaDomingoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        View::share('errors', new ViewErrorBag);

        Schema::create('nomina_domingo_configuraciones', function (Blueprint $table): void {
            $table->id();
            $table->decimal('horas_requeridas', 5, 2);
            $table->decimal('monto_fijo', 12, 2);
            $table->timestamps();
        });
        Schema::create('gestion_agencias_ventas', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal');
            $table->string('usuario_venta');
            $table->dateTime('fecha_transaccion');
            $table->string('tipo')->nullable();
            $table->decimal('total_apostado', 12, 2)->nullable();
        });
        Schema::create('vt_usuarios_bet', function (Blueprint $table): void {
            $table->id('vt_usuario_id');
            $table->string('agencia_id')->nullable();
            $table->string('cedula')->nullable();
            $table->date('fecha')->nullable();
            $table->string('tipo')->nullable();
            $table->decimal('monto', 12, 2)->nullable();
        });
        Schema::create('asistencias_bet', function (Blueprint $table): void {
            $table->id();
            $table->date('fecha');
            $table->string('agencia_id');
            $table->string('cedula');
            $table->string('usuario')->nullable();
            $table->dateTime('primer_login')->nullable();
            $table->dateTime('ultimo_login')->nullable();
        });
        Schema::create('asistencias_net', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal');
            $table->string('identificacion');
            $table->string('username')->nullable();
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
        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal')->nullable();
            $table->string('empresa')->nullable();
            $table->string('coordinador')->nullable();
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
            $table->foreignId('coordinador_operador_id');
            $table->foreignId('agencia_id');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        foreach (['coordinador_operador_agencia', 'coordinador_operador', 'agencias', 'empleados', 'asistencias_net', 'asistencias_bet', 'vt_usuarios_bet', 'gestion_agencias_ventas', 'nomina_domingo_configuraciones'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
        parent::tearDown();
    }

    public function test_uses_later_transaction_as_effective_exit_and_pays_when_hours_are_met(): void
    {
        DB::table('nomina_domingo_configuraciones')->insert(['id' => 1, 'horas_requeridas' => 8, 'monto_fijo' => 1500]);
        DB::table('empleados')->insert(['cedula' => '00112345678', 'nombres' => 'Ana', 'apellidos' => 'Pérez']);
        DB::table('asistencias_bet')->insert(['fecha' => '2026-09-13', 'agencia_id' => '0012', 'cedula' => '00112345678', 'usuario' => 'Ana', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 14:00:00']);
        DB::table('gestion_agencias_ventas')->insert(['terminal' => '12', 'usuario_venta' => '001-1234567-8', 'fecha_transaccion' => '2026-09-13 16:30:00']);

        $fila = app(NominaDomingoService::class)->generar(Carbon::parse('2026-09-13'))->first();

        $this->assertSame('Última transacción', $fila['fuente_salida']);
        $this->assertSame('00112345678', $fila['cedula']);
        $this->assertSame('Ana Pérez', $fila['empleado']);
        $this->assertSame(8.5, $fila['horas_trabajadas']);
        $this->assertSame('Cumple', $fila['estatus']);
        $this->assertSame(1500.0, $fila['monto_pagar']);
    }

    public function test_employee_without_enough_hours_does_not_receive_payment(): void
    {
        DB::table('nomina_domingo_configuraciones')->insert(['id' => 1, 'horas_requeridas' => 8, 'monto_fijo' => 1500]);
        DB::table('asistencias_bet')->insert(['fecha' => '2026-09-13', 'agencia_id' => '5', 'cedula' => '00100000001', 'usuario' => 'Empleado prueba', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 12:00:00']);

        $fila = app(NominaDomingoService::class)->generar(Carbon::parse('2026-09-13'))->first();

        $this->assertSame('No cumple', $fila['estatus']);
        $this->assertSame(0.0, $fila['monto_pagar']);
    }

    public function test_reconciles_file_and_api_sales_by_type(): void
    {
        DB::table('gestion_agencias_ventas')->insert([
            ['terminal' => '12', 'usuario_venta' => '1', 'fecha_transaccion' => '2026-09-13 12:00:00', 'tipo' => 'Tradicional', 'total_apostado' => 1000],
            ['terminal' => '12', 'usuario_venta' => '1', 'fecha_transaccion' => '2026-09-13 12:00:00', 'tipo' => 'No Tradicional', 'total_apostado' => 400],
        ]);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '12', 'cedula' => '1', 'fecha' => '2026-09-13', 'tipo' => 'tradicional', 'monto' => 1000],
            ['agencia_id' => '12', 'cedula' => '1', 'fecha' => '2026-09-13', 'tipo' => 'no_tradicional', 'monto' => 350],
        ]);

        $conciliacion = app(NominaDomingoService::class)->conciliacionVentas(Carbon::parse('2026-09-13'));

        $this->assertSame(['archivo' => 1000.0, 'api' => 1000.0, 'diferencia' => 0.0], $conciliacion['tradicional']);
        $this->assertSame(['archivo' => 400.0, 'api' => 350.0, 'diferencia' => 50.0], $conciliacion['no_tradicional']);

        $this->get(route('recursos-humanos.nomina-domingo.index', ['fecha' => '2026-09-13', 'consultar' => 1]))
            ->assertOk()
            ->assertSee('Conciliación Tradicional')
            ->assertSee('Conciliación No Tradicional')
            ->assertSee('RD$ 1,000.00')
            ->assertSee('RD$ 350.00');
    }

    public function test_uses_lotonet_punches_to_calculate_worked_hours(): void
    {
        DB::table('nomina_domingo_configuraciones')->insert(['id' => 1, 'horas_requeridas' => 8, 'monto_fijo' => 1500]);
        DB::table('asistencias_net')->insert([
            'terminal' => '25',
            'identificacion' => '00100000002',
            'username' => 'Empleado Lotonet',
            'entrada' => '2026-09-13 07:30:00',
            'salida' => '2026-09-13 16:00:00',
        ]);

        $fila = app(NominaDomingoService::class)->generar(Carbon::parse('2026-09-13'))->first();

        $this->assertSame('Ponche', $fila['fuente_salida']);
        $this->assertSame(8.5, $fila['horas_trabajadas']);
        $this->assertSame('Cumple', $fila['estatus']);
    }

    public function test_uses_last_login_when_it_is_later_than_last_transaction(): void
    {
        DB::table('nomina_domingo_configuraciones')->insert(['id' => 1, 'horas_requeridas' => 8, 'monto_fijo' => 1500]);
        DB::table('asistencias_bet')->insert([
            'fecha' => '2026-09-13',
            'agencia_id' => '12',
            'cedula' => '00112345678',
            'usuario' => 'Ana',
            'primer_login' => '2026-09-13 07:00:00',
            'ultimo_login' => '2026-09-13 17:00:00',
        ]);
        DB::table('gestion_agencias_ventas')->insert([
            'terminal' => '12',
            'usuario_venta' => '001-1234567-8',
            'fecha_transaccion' => '2026-09-13 16:50:00',
        ]);

        $fila = app(NominaDomingoService::class)->generar(Carbon::parse('2026-09-13'))->first();

        $this->assertSame('Ponche', $fila['fuente_salida']);
        $this->assertSame('2026-09-13 17:00:00', $fila['salida_efectiva']);
        $this->assertSame(10.0, $fila['horas_trabajadas']);
    }

    public function test_adds_five_minutes_to_last_transaction_when_exit_punch_is_missing(): void
    {
        DB::table('nomina_domingo_configuraciones')->insert(['id' => 1, 'horas_requeridas' => 8, 'monto_fijo' => 1500]);
        DB::table('asistencias_bet')->insert([
            'fecha' => '2026-09-13',
            'agencia_id' => '51405',
            'cedula' => '40238675553',
            'usuario' => 'Leidy Suero Brito',
            'primer_login' => '2026-09-13 09:30:00',
            'ultimo_login' => null,
        ]);
        DB::table('gestion_agencias_ventas')->insert([
            'terminal' => '51405',
            'usuario_venta' => '40238675553',
            'fecha_transaccion' => '2026-09-13 17:41:00',
        ]);

        $fila = app(NominaDomingoService::class)->generar(Carbon::parse('2026-09-13'))->first();

        $this->assertSame('Última transacción + 5 minutos', $fila['fuente_salida']);
        $this->assertSame('2026-09-13 17:46:00', $fila['salida_efectiva']);
        $this->assertSame(8.27, $fila['horas_trabajadas']);
        $this->assertSame('Cumple', $fila['estatus']);
        $this->assertSame(1500.0, $fila['monto_pagar']);
    }

    public function test_report_can_filter_employees_by_compliance(): void
    {
        DB::table('nomina_domingo_configuraciones')->insert(['id' => 1, 'horas_requeridas' => 8, 'monto_fijo' => 1500]);
        DB::table('asistencias_bet')->insert([
            ['fecha' => '2026-09-13', 'agencia_id' => '5', 'cedula' => '00100000001', 'usuario' => 'Empleado cumple', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 16:00:00'],
            ['fecha' => '2026-09-13', 'agencia_id' => '6', 'cedula' => '00100000002', 'usuario' => 'Empleado no cumple', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 12:00:00'],
        ]);

        $this->get(route('recursos-humanos.nomina-domingo.index', ['fecha' => '2026-09-13', 'consultar' => 1, 'estatus' => 'cumple']))
            ->assertOk()
            ->assertSee('Empleado cumple')
            ->assertViewHas('filas', fn ($filas): bool => $filas->pluck('empleado')->all() === ['Empleado cumple']);

        $this->get(route('recursos-humanos.nomina-domingo.index', ['fecha' => '2026-09-13', 'consultar' => 1, 'estatus' => 'no_cumple']))
            ->assertOk()
            ->assertSee('Empleado no cumple')
            ->assertViewHas('filas', fn ($filas): bool => $filas->pluck('empleado')->all() === ['Empleado no cumple']);
    }

    public function test_report_shows_coordinator_and_filters_by_company(): void
    {
        DB::table('agencias')->insert([
            ['id' => 1, 'terminal' => '5', 'empresa' => 'Empresa Norte', 'coordinador' => 'Nombre anterior'],
            ['id' => 2, 'terminal' => '6', 'empresa' => 'Empresa Sur', 'coordinador' => 'Nombre anterior'],
        ]);
        DB::table('coordinador_operador')->insert([
            ['id' => 1, 'nombre' => 'Coordinadora', 'apellido' => 'Uno', 'puesto' => 'coordinador'],
            ['id' => 2, 'nombre' => 'Coordinador', 'apellido' => 'Dos', 'puesto' => 'coordinador'],
        ]);
        DB::table('coordinador_operador_agencia')->insert([
            ['coordinador_operador_id' => 1, 'agencia_id' => 1],
            ['coordinador_operador_id' => 2, 'agencia_id' => 2],
        ]);
        DB::table('asistencias_bet')->insert([
            ['fecha' => '2026-09-13', 'agencia_id' => '5', 'cedula' => '00100000001', 'usuario' => 'Empleado Norte', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 16:00:00'],
            ['fecha' => '2026-09-13', 'agencia_id' => '6', 'cedula' => '00100000002', 'usuario' => 'Empleado Sur', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 16:00:00'],
        ]);

        $this->get(route('recursos-humanos.nomina-domingo.index', [
            'fecha' => '2026-09-13', 'consultar' => 1, 'empresa' => 'Empresa Norte',
        ]))
            ->assertOk()
            ->assertSee('Coordinadora Uno')
            ->assertSee('Empleado Norte')
            ->assertDontSee('Coordinador Dos')
            ->assertDontSee('Empleado Sur');
    }

    public function test_coordinator_modal_summarizes_each_agency_once(): void
    {
        DB::table('agencias')->insert([
            ['id' => 1, 'terminal' => '5', 'empresa' => 'Empresa Norte', 'coordinador' => 'Nombre anterior'],
            ['id' => 2, 'terminal' => '6', 'empresa' => 'Empresa Norte', 'coordinador' => 'Nombre anterior'],
        ]);
        DB::table('coordinador_operador')->insert(['id' => 1, 'nombre' => 'Coordinadora', 'apellido' => 'Uno', 'puesto' => 'coordinador']);
        DB::table('coordinador_operador_agencia')->insert([
            ['coordinador_operador_id' => 1, 'agencia_id' => 1],
            ['coordinador_operador_id' => 1, 'agencia_id' => 2],
        ]);
        DB::table('asistencias_bet')->insert([
            ['fecha' => '2026-09-13', 'agencia_id' => '5', 'cedula' => '00100000001', 'usuario' => 'Cumple Uno', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 16:00:00'],
            ['fecha' => '2026-09-13', 'agencia_id' => '5', 'cedula' => '00100000002', 'usuario' => 'No cumple misma agencia', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 12:00:00'],
            ['fecha' => '2026-09-13', 'agencia_id' => '6', 'cedula' => '00100000003', 'usuario' => 'No cumple otra agencia', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 12:00:00'],
        ]);

        $response = $this->get(route('recursos-humanos.nomina-domingo.index', ['fecha' => '2026-09-13', 'consultar' => 1]));

        $response->assertOk()
            ->assertSee('Resumen por coordinador')
            ->assertSee('Agencias cumplieron')
            ->assertSee('Agencias no cumplieron')
            ->assertSee('Empleados cumplieron')
            ->assertSee('Empleados no cumplieron')
            ->assertViewHas('resumenCoordinadores', function ($resumen): bool {
                $coordinador = $resumen->firstWhere('coordinador', 'Coordinadora Uno');

                return $coordinador['agencias_asignadas'] === 2
                    && $coordinador['agencias_cumplieron'] === 1
                    && $coordinador['agencias_no_cumplieron'] === 1
                    && $coordinador['empleados_cumplieron'] === 1
                    && $coordinador['empleados_no_cumplieron'] === 2
                    && count($coordinador['detalle_empleados_cumplieron']) === 1
                    && count($coordinador['detalle_empleados_no_cumplieron']) === 2;
            });
    }

    public function test_report_ignores_operator_assignments_and_legacy_coordinator_name(): void
    {
        DB::table('agencias')->insert(['id' => 1, 'terminal' => '5', 'empresa' => 'Empresa Norte', 'coordinador' => 'Coordinador Antiguo']);
        DB::table('coordinador_operador')->insert(['id' => 1, 'nombre' => 'Operador', 'apellido' => 'Uno', 'puesto' => 'operador']);
        DB::table('coordinador_operador_agencia')->insert(['coordinador_operador_id' => 1, 'agencia_id' => 1]);
        DB::table('asistencias_bet')->insert([
            'fecha' => '2026-09-13', 'agencia_id' => '5', 'cedula' => '00100000001', 'usuario' => 'Empleado Norte',
            'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 16:00:00',
        ]);

        $fila = app(NominaDomingoService::class)->generar(Carbon::parse('2026-09-13'))->first();

        $this->assertSame('Sin coordinador', $fila['coordinador']);
    }

    public function test_coordinator_report_can_be_sent_by_telegram(): void
    {
        config()->set('services.telegram.bot_token', 'test-token');
        Http::fake([
            'https://api.telegram.org/bottest-token/sendDocument' => Http::response(['ok' => true], 200),
        ]);
        DB::table('agencias')->insert(['id' => 1, 'terminal' => '5', 'empresa' => 'Empresa Norte', 'coordinador' => 'Nombre anterior']);
        DB::table('coordinador_operador')->insert(['id' => 1, 'nombre' => 'Coordinadora', 'apellido' => 'Uno', 'puesto' => 'coordinador']);
        DB::table('coordinador_operador_agencia')->insert(['coordinador_operador_id' => 1, 'agencia_id' => 1]);
        DB::table('asistencias_bet')->insert([
            ['fecha' => '2026-09-13', 'agencia_id' => '5', 'cedula' => '00100000001', 'usuario' => 'Empleado Cumple', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 16:00:00'],
            ['fecha' => '2026-09-13', 'agencia_id' => '5', 'cedula' => '00100000002', 'usuario' => 'Empleado No Cumple', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 12:00:00'],
        ]);

        $this->postJson(route('recursos-humanos.nomina-domingo.enviar-telegram'), [
            'chat_id' => '123456789',
            'coordinador' => 'Coordinadora Uno',
            'fecha' => '2026-09-13',
            'empresa' => 'Empresa Norte',
        ])->assertOk()->assertJson(['enviado' => true, 'documentos' => 2]);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.telegram.org/bottest-token/sendDocument');
    }

    public function test_telegram_send_requires_chat_id(): void
    {
        $this->postJson(route('recursos-humanos.nomina-domingo.enviar-telegram'), [
            'coordinador' => 'Coordinadora Uno',
            'fecha' => '2026-09-13',
        ])->assertInvalid(['chat_id']);
    }

    public function test_report_identifies_records_without_first_login_in_a_modal(): void
    {
        DB::table('nomina_domingo_configuraciones')->insert(['id' => 1, 'horas_requeridas' => 8, 'monto_fijo' => 1500]);
        DB::table('gestion_agencias_ventas')->insert([
            ['terminal' => '99', 'usuario_venta' => '00100000009', 'fecha_transaccion' => '2026-09-13 08:15:00'],
            ['terminal' => '99', 'usuario_venta' => '00100000009', 'fecha_transaccion' => '2026-09-13 17:15:00'],
        ]);

        $this->get(route('recursos-humanos.nomina-domingo.index', ['fecha' => '2026-09-13', 'consultar' => 1]))
            ->assertOk()
            ->assertSee('Ver registros')
            ->assertSee('Registros sin primer login')
            ->assertSee('Primera transacción')
            ->assertSee('13/09/2026 08:15 AM')
            ->assertSee('00100000009')
            ->assertSee('Primer login: No disponible');
    }

    public function test_report_screen_contains_requested_datatable_and_configuration(): void
    {
        $this->get(route('recursos-humanos.nomina-domingo.index'))
            ->assertOk()
            ->assertSee('Nómina Domingo')
            ->assertSee('Datos limpios')
            ->assertSee('Horas trabajadas')
            ->assertSee('Monto a pagar')
            ->assertSee('Configurar nómina')
            ->assertSee('Cumplimiento')
            ->assertSee('Coordinador')
            ->assertSee('Empresa')
            ->assertSee('Cumplen')
            ->assertSee('No cumplen')
            ->assertSee('Generando data...')
            ->assertSee('Descargar Excel');
    }

    public function test_configuration_can_be_updated(): void
    {
        $this->post(route('recursos-humanos.nomina-domingo.configuracion'), [
            'horas_requeridas_horas' => 7,
            'horas_requeridas_minutos' => 30,
            'monto_fijo' => 1200,
        ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('nomina_domingo_configuraciones', ['id' => 1, 'horas_requeridas' => 7.5, 'monto_fijo' => 1200]);
    }

    public function test_configuration_rejects_sixty_minutes(): void
    {
        $this->post(route('recursos-humanos.nomina-domingo.configuracion'), [
            'horas_requeridas_horas' => 7,
            'horas_requeridas_minutos' => 60,
            'monto_fijo' => 1200,
        ])->assertInvalid(['horas_requeridas_minutos']);
    }

    public function test_configuration_preserves_minute_precision_when_reading_decimal_storage(): void
    {
        $this->post(route('recursos-humanos.nomina-domingo.configuracion'), [
            'horas_requeridas_horas' => 7,
            'horas_requeridas_minutos' => 1,
            'monto_fijo' => 1200,
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(7 + (1 / 60), app(NominaDomingoService::class)->configuracion()['horas_requeridas'], 0.00001);
    }
}
