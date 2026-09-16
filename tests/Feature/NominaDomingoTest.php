<?php

namespace Tests\Feature;

use App\Services\RecursosHumanos\NominaDomingoService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
    }

    protected function tearDown(): void
    {
        foreach (['empleados', 'asistencias_net', 'asistencias_bet', 'gestion_agencias_ventas', 'nomina_domingo_configuraciones'] as $tabla) {
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
            ->assertDontSee('Empleado no cumple');

        $this->get(route('recursos-humanos.nomina-domingo.index', ['fecha' => '2026-09-13', 'consultar' => 1, 'estatus' => 'no_cumple']))
            ->assertOk()
            ->assertSee('Empleado no cumple')
            ->assertDontSee('Empleado cumple');
    }

    public function test_report_identifies_records_without_first_login_in_a_modal(): void
    {
        DB::table('nomina_domingo_configuraciones')->insert(['id' => 1, 'horas_requeridas' => 8, 'monto_fijo' => 1500]);
        DB::table('gestion_agencias_ventas')->insert([
            'terminal' => '99',
            'usuario_venta' => '00100000009',
            'fecha_transaccion' => '2026-09-13 17:15:00',
        ]);

        $this->get(route('recursos-humanos.nomina-domingo.index', ['fecha' => '2026-09-13', 'consultar' => 1]))
            ->assertOk()
            ->assertSee('Ver registros')
            ->assertSee('Registros sin primer login')
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
            ->assertSee('Cumplen')
            ->assertSee('No cumplen')
            ->assertSee('Generando data...')
            ->assertSee('Descargar Excel');
    }

    public function test_configuration_can_be_updated(): void
    {
        $this->post(route('recursos-humanos.nomina-domingo.configuracion'), ['horas_requeridas' => 7.5, 'monto_fijo' => 1200])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('nomina_domingo_configuraciones', ['id' => 1, 'horas_requeridas' => 7.5, 'monto_fijo' => 1200]);
    }
}
