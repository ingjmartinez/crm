<?php

namespace Tests\Feature;

use App\Services\RecursosHumanos\NominaDomingoService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
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
        Schema::create('nomina_domingo_ventas', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal')->nullable();
            $table->string('usuario_venta')->nullable();
            $table->dateTime('fecha_transaccion')->nullable();
            $table->string('tipo')->nullable();
            $table->decimal('total_apostado', 12, 2)->nullable();
            $table->string('fecha_texto')->nullable();
            $table->string('agencia')->nullable();
            $table->string('terminal_clave')->nullable();
            $table->string('estatus')->nullable();
            $table->timestamps();
        });
        Schema::create('nomina_domingo_terminales_excluidas', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal')->unique();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
        Schema::create('gestion_agencias_ventas', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal')->nullable();
            $table->string('usuario_venta')->nullable();
            $table->dateTime('fecha_transaccion')->nullable();
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
        foreach (['coordinador_operador_agencia', 'coordinador_operador', 'agencias', 'empleados', 'asistencias_net', 'asistencias_bet', 'vt_usuarios_bet', 'gestion_agencias_ventas', 'nomina_domingo_terminales_excluidas', 'nomina_domingo_ventas', 'nomina_domingo_configuraciones'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
        parent::tearDown();
    }

    public function test_excluded_terminal_is_omitted_from_sunday_payroll(): void
    {
        DB::table('nomina_domingo_configuraciones')->insert(['id' => 1, 'horas_requeridas' => 8, 'monto_fijo' => 1500]);
        DB::table('asistencias_bet')->insert([
            ['fecha' => '2026-09-13', 'agencia_id' => '0012', 'cedula' => '00100000001', 'usuario' => 'Excluido', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 17:00:00'],
            ['fecha' => '2026-09-13', 'agencia_id' => '0013', 'cedula' => '00100000002', 'usuario' => 'Incluido', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 17:00:00'],
        ]);
        DB::table('nomina_domingo_terminales_excluidas')->insert(['terminal' => '0012']);

        $filas = app(NominaDomingoService::class)->generar(Carbon::parse('2026-09-13'));

        $this->assertCount(1, $filas);
        $this->assertSame('13', $filas->first()['terminal']);
        $this->assertSame(1500.0, $filas->sum('monto_pagar'));
    }

    public function test_terminal_exclusions_can_be_recognized_saved_listed_and_removed(): void
    {
        DB::table('agencias')->insert([
            ['terminal' => '12', 'empresa' => 'A'],
            ['terminal' => '13', 'empresa' => 'A'],
        ]);

        $this->postJson(route('recursos-humanos.nomina-domingo.terminales-excluidas.reconocer'), [
            'terminales_manual' => "12\n999",
        ])->assertOk()->assertJson([
            'terminales_encontradas' => ['12'],
            'terminales_no_encontradas' => ['999'],
        ]);

        $this->postJson(route('recursos-humanos.nomina-domingo.terminales-excluidas.store'), [
            'terminales' => ['12', '13'],
        ])->assertOk()->assertJson(['count' => 2]);
        $this->getJson(route('recursos-humanos.nomina-domingo.terminales-excluidas.index'))
            ->assertOk()->assertJson(['terminales' => ['12', '13'], 'count' => 2]);

        $this->postJson(route('recursos-humanos.nomina-domingo.terminales-excluidas.store'), [
            'terminales' => ['13'],
        ])->assertOk()->assertJson(['terminales' => ['13'], 'count' => 1]);
        $this->assertDatabaseMissing('nomina_domingo_terminales_excluidas', ['terminal' => '12']);
    }

    public function test_terminal_recognition_requires_a_file_or_manual_values(): void
    {
        $this->postJson(route('recursos-humanos.nomina-domingo.terminales-excluidas.reconocer'), [])
            ->assertUnprocessable()->assertJsonValidationErrors(['file', 'terminales_manual']);
    }

    public function test_sale_after_last_login_extends_only_the_exit_by_five_minutes(): void
    {
        DB::table('nomina_domingo_configuraciones')->insert(['id' => 1, 'horas_requeridas' => 8, 'monto_fijo' => 1500]);
        DB::table('empleados')->insert(['cedula' => '00112345678', 'nombres' => 'Ana', 'apellidos' => 'Pérez']);
        DB::table('asistencias_bet')->insert(['fecha' => '2026-09-13', 'agencia_id' => '0012', 'cedula' => '00112345678', 'usuario' => 'Ana', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 14:00:00']);
        DB::table('nomina_domingo_ventas')->insert(['terminal' => '12', 'usuario_venta' => '001-1234567-8', 'fecha_transaccion' => '2026-09-13 16:30:00']);

        $fila = app(NominaDomingoService::class)->generar(Carbon::parse('2026-09-13'))->first();

        $this->assertSame('Última venta + 5 minutos', $fila['fuente_salida']);
        $this->assertSame('00112345678', $fila['cedula']);
        $this->assertSame('Ana Pérez', $fila['empleado']);
        $this->assertSame('2026-09-13 08:00:00', $fila['entrada']);
        $this->assertSame('2026-09-13 14:00:00', $fila['salida_ponche']);
        $this->assertSame('2026-09-13 16:35:00', $fila['salida_efectiva']);
        $this->assertSame(8.58, $fila['horas_trabajadas']);
        $this->assertSame('8 h 35 min', $fila['horas_trabajadas_formato']);
        $this->assertNull($fila['incidencia']);
        $this->assertSame('Cumple', $fila['estatus']);
        $this->assertSame(1500.0, $fila['monto_pagar']);

        $this->get(route('recursos-humanos.nomina-domingo.index', ['fecha' => '2026-09-13', 'consultar' => 1]))
            ->assertOk()
            ->assertSee('8 h 35 min')
            ->assertSee('Terminales y colaboradoras sin empresa')
            ->assertSee('data-bs-target="#modalNominaSinEmpresa"', false)
            ->assertSee('Última venta + 5 minutos: 04:35 PM');
    }

    public function test_employee_without_enough_hours_does_not_receive_payment(): void
    {
        DB::table('nomina_domingo_configuraciones')->insert(['id' => 1, 'horas_requeridas' => 8, 'monto_fijo' => 1500]);
        DB::table('asistencias_bet')->insert(['fecha' => '2026-09-13', 'agencia_id' => '5', 'cedula' => '00100000001', 'usuario' => 'Empleado prueba', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 12:00:00']);

        $fila = app(NominaDomingoService::class)->generar(Carbon::parse('2026-09-13'))->first();

        $this->assertSame('No cumple', $fila['estatus']);
        $this->assertSame(0.0, $fila['monto_pagar']);
    }

    public function test_displayed_integer_minutes_are_used_to_decide_compliance(): void
    {
        DB::table('nomina_domingo_configuraciones')->insert(['id' => 1, 'horas_requeridas' => 7.75, 'monto_fijo' => 1500]);
        DB::table('asistencias_bet')->insert([
            ['fecha' => '2026-09-13', 'agencia_id' => '12', 'cedula' => '00100000001', 'usuario' => 'Cumple', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 15:44:31'],
            ['fecha' => '2026-09-13', 'agencia_id' => '13', 'cedula' => '00100000002', 'usuario' => 'No cumple', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 15:44:29'],
        ]);

        $filas = app(NominaDomingoService::class)->generar(Carbon::parse('2026-09-13'))->keyBy('terminal');

        $this->assertSame(465, $filas['12']['minutos_trabajados']);
        $this->assertSame('7 h 45 min', $filas['12']['horas_trabajadas_formato']);
        $this->assertSame('Cumple', $filas['12']['estatus']);
        $this->assertSame(1500.0, $filas['12']['monto_pagar']);
        $this->assertSame(464, $filas['13']['minutos_trabajados']);
        $this->assertSame('7 h 44 min', $filas['13']['horas_trabajadas_formato']);
        $this->assertSame('No cumple', $filas['13']['estatus']);
        $this->assertSame(0.0, $filas['13']['monto_pagar']);
    }

    public function test_calculates_compliance_and_noncompliance_percentages_by_company(): void
    {
        $resumen = app(NominaDomingoService::class)->resumenCumplimientoPorEmpresa(collect([
            ['empresa' => 'Empresa A', 'estatus' => 'Cumple'],
            ['empresa' => 'Empresa A', 'estatus' => 'Cumple'],
            ['empresa' => 'Empresa A', 'estatus' => 'No cumple'],
            ['empresa' => 'Empresa A', 'estatus' => 'Revisar'],
            ['empresa' => 'Empresa B', 'estatus' => 'Cumple'],
        ]));

        $this->assertSame([
            'empresa' => 'Empresa A',
            'total' => 4,
            'cumplen' => 2,
            'incumplen' => 2,
            'porcentaje_cumplimiento' => 50.0,
            'porcentaje_incumplimiento' => 50.0,
        ], $resumen->firstWhere('empresa', 'Empresa A'));
        $this->assertSame(100.0, $resumen->firstWhere('empresa', 'Empresa B')['porcentaje_cumplimiento']);
        $this->assertSame(0.0, $resumen->firstWhere('empresa', 'Empresa B')['porcentaje_incumplimiento']);
    }

    public function test_sales_user_matches_punch_user_and_uses_the_punch_identity(): void
    {
        DB::table('nomina_domingo_configuraciones')->insert(['id' => 1, 'horas_requeridas' => 8, 'monto_fijo' => 1500]);
        DB::table('empleados')->insert(['cedula' => '2300293707', 'nombres' => 'Mayra', 'apellidos' => 'De Leon']);
        DB::table('asistencias_bet')->insert([
            'fecha' => '2026-09-13', 'agencia_id' => '051558', 'cedula' => '2300293707', 'usuario' => '22300293707',
            'primer_login' => '2026-09-13 07:00:00', 'ultimo_login' => '2026-09-13 16:00:00',
        ]);
        DB::table('nomina_domingo_ventas')->insert([
            ['terminal' => '51558', 'usuario_venta' => '22300293707', 'fecha_transaccion' => '2026-09-13 08:00:00', 'tipo' => 'Tradicional', 'total_apostado' => 100],
            ['terminal' => '51558', 'usuario_venta' => '22300293707', 'fecha_transaccion' => '2026-09-13 15:00:00', 'tipo' => 'No Tradicional', 'total_apostado' => 50],
        ]);

        $filas = app(NominaDomingoService::class)->generar(Carbon::parse('2026-09-13'));

        $this->assertCount(1, $filas);
        $this->assertSame('02300293707', $filas[0]['cedula']);
        $this->assertSame('Mayra De Leon', $filas[0]['empleado']);
        $this->assertTrue($filas[0]['coincide_maestra']);
        $this->assertSame('Ponche', $filas[0]['origen_identidad']);
        $this->assertSame('22300293707', $filas[0]['usuario_venta']);
        $this->assertSame(1, $filas[0]['tradicional_cantidad']);
        $this->assertSame(100.0, $filas[0]['tradicional_monto']);
        $this->assertSame(1, $filas[0]['no_tradicional_cantidad']);
        $this->assertSame(50.0, $filas[0]['no_tradicional_monto']);
        $this->assertSame(9.0, $filas[0]['horas_trabajadas']);
        $this->assertSame('Cumple', $filas[0]['estatus']);
        $this->assertSame(1500.0, $filas[0]['monto_pagar']);
    }

    public function test_named_seller_without_punch_is_reviewed_and_shows_what_was_sold(): void
    {
        DB::table('empleados')->insert(['cedula' => '40238675553', 'nombres' => 'Leidy', 'apellidos' => 'Suero']);
        DB::table('nomina_domingo_ventas')->insert([
            ['terminal' => '51405', 'usuario_venta' => '40238675553', 'fecha_transaccion' => '2026-09-13 08:30:00', 'tipo' => 'Tradicional', 'total_apostado' => 100],
            ['terminal' => '51405', 'usuario_venta' => '40238675553', 'fecha_transaccion' => '2026-09-13 17:40:00', 'tipo' => 'No Tradicional', 'total_apostado' => 50],
        ]);

        $fila = app(NominaDomingoService::class)->generar(Carbon::parse('2026-09-13'))->first();

        $this->assertSame('Leidy Suero', $fila['empleado']);
        $this->assertTrue($fila['coincide_maestra']);
        $this->assertSame('Maestra de empleados', $fila['origen_identidad']);
        $this->assertSame('Sin primer login', $fila['incidencia']);
        $this->assertSame('Revisar', $fila['estatus']);
        $this->assertSame(0.0, $fila['horas_trabajadas']);
        $this->assertSame(0.0, $fila['monto_pagar']);
        $this->assertSame(100.0, $fila['tradicional_monto']);
        $this->assertSame(50.0, $fila['no_tradicional_monto']);
    }

    public function test_sale_before_first_login_requires_review(): void
    {
        DB::table('nomina_domingo_configuraciones')->insert(['id' => 1, 'horas_requeridas' => 8, 'monto_fijo' => 1500]);
        DB::table('asistencias_bet')->insert([
            'fecha' => '2026-09-13', 'agencia_id' => '12', 'cedula' => '00112345678', 'usuario' => '00112345678',
            'primer_login' => '2026-09-13 09:00:00', 'ultimo_login' => '2026-09-13 18:00:00',
        ]);
        DB::table('nomina_domingo_ventas')->insert([
            ['terminal' => '12', 'usuario_venta' => '00112345678', 'fecha_transaccion' => '2026-09-13 08:30:00', 'tipo' => 'Tradicional', 'total_apostado' => 100],
            ['terminal' => '12', 'usuario_venta' => '00112345678', 'fecha_transaccion' => '2026-09-13 19:00:00', 'tipo' => 'Tradicional', 'total_apostado' => 50],
        ]);

        $fila = app(NominaDomingoService::class)->generar(Carbon::parse('2026-09-13'))->first();

        $this->assertSame('Venta antes del primer login', $fila['incidencia']);
        $this->assertSame('Revisar', $fila['estatus']);
        $this->assertSame('2026-09-13 09:00:00', $fila['entrada']);
        $this->assertSame('2026-09-13 19:05:00', $fila['salida_efectiva']);
        $this->assertSame(10.08, $fila['horas_trabajadas']);
        $this->assertSame('10 h 5 min', $fila['horas_trabajadas_formato']);
        $this->assertSame(0.0, $fila['monto_pagar']);
    }

    public function test_reconciles_file_and_api_sales_by_type(): void
    {
        DB::table('nomina_domingo_ventas')->insert([
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
            ->assertSee(route('recursos-humanos.nomina-domingo.cargar-ventas'), false)
            ->assertSee('Conciliación Tradicional')
            ->assertSee('Conciliación No Tradicional')
            ->assertSee('RD$ 1,000.00')
            ->assertSee('RD$ 350.00');
    }

    public function test_uploading_sunday_files_does_not_replace_agency_report_sales(): void
    {
        DB::table('gestion_agencias_ventas')->insert([
            'terminal' => '99',
            'usuario_venta' => 'old',
            'fecha_transaccion' => '2026-09-13 10:00:00',
            'tipo' => 'Tradicional',
            'total_apostado' => 999,
        ]);

        $tradicional = "Fecha,Agencia,Total Apostado,Estatus,Terminal,Usr. Venta\n2026-09-13 12:00:00,Agencia 12,1000,Validos,12,00112345678\n";
        $noTradicional = "Agencia,Estatus,Fecha,Id Terminal,Usr. Venta,Total Apostado\nAgencia 12,Validos,2026-09-13 13:00:00,12,00112345678,400\n";

        $this->post(route('recursos-humanos.nomina-domingo.cargar-ventas'), [
            'destino' => 'nomina_domingo',
            'fecha_nomina' => '2026-09-13',
            'tradicional' => UploadedFile::fake()->createWithContent('tradicional.csv', $tradicional),
            'no_tradicional' => UploadedFile::fake()->createWithContent('no_tradicional.csv', $noTradicional),
        ])->assertRedirect(route('recursos-humanos.nomina-domingo.index', ['fecha' => '2026-09-13']))
            ->assertSessionHas('success');

        $this->assertSame(1, DB::table('gestion_agencias_ventas')->count());
        $this->assertSame(999.0, (float) DB::table('gestion_agencias_ventas')->sum('total_apostado'));
        $this->assertSame(2, DB::table('nomina_domingo_ventas')->count());

        $conciliacion = app(NominaDomingoService::class)->conciliacionVentas(Carbon::parse('2026-09-13'));
        $this->assertSame(1000.0, $conciliacion['tradicional']['archivo']);
        $this->assertSame(400.0, $conciliacion['no_tradicional']['archivo']);
    }

    public function test_reupload_replaces_only_the_selected_sunday(): void
    {
        DB::table('nomina_domingo_ventas')->insert([
            ['tipo' => 'Tradicional', 'fecha_transaccion' => '2026-09-13 10:00:00', 'total_apostado' => 999],
            ['tipo' => 'Tradicional', 'fecha_transaccion' => '2026-09-06 10:00:00', 'total_apostado' => 700],
        ]);

        $this->post(route('recursos-humanos.nomina-domingo.cargar-ventas'), [
            'destino' => 'nomina_domingo',
            'fecha_nomina' => '2026-09-13',
            'tradicional' => UploadedFile::fake()->createWithContent('tradicional.csv', "Fecha,Agencia,Total Apostado,Estatus,Terminal,Usr. Venta\n2026-09-13 12:00:00,Agencia 12,1000,Validos,12,00112345678\n"),
            'no_tradicional' => UploadedFile::fake()->createWithContent('no_tradicional.csv', "Agencia,Estatus,Fecha,Id Terminal,Usr. Venta,Total Apostado\nAgencia 12,Validos,2026-09-13 13:00:00,12,00112345678,400\n"),
        ])->assertRedirect();

        $this->assertSame(3, DB::table('nomina_domingo_ventas')->count());
        $this->assertSame(1400.0, (float) DB::table('nomina_domingo_ventas')->whereDate('fecha_transaccion', '2026-09-13')->sum('total_apostado'));
        $this->assertSame(700.0, (float) DB::table('nomina_domingo_ventas')->whereDate('fecha_transaccion', '2026-09-06')->sum('total_apostado'));
    }

    public function test_rejects_a_file_with_another_date_without_losing_previous_sales(): void
    {
        DB::table('nomina_domingo_ventas')->insert(['tipo' => 'Tradicional', 'fecha_transaccion' => '2026-09-13 10:00:00', 'total_apostado' => 999]);

        $this->from(route('recursos-humanos.nomina-domingo.index'))->post(route('recursos-humanos.nomina-domingo.cargar-ventas'), [
            'destino' => 'nomina_domingo',
            'fecha_nomina' => '2026-09-13',
            'tradicional' => UploadedFile::fake()->createWithContent('tradicional.csv', "Fecha,Agencia,Total Apostado,Estatus,Terminal,Usr. Venta\n2026-09-14 12:00:00,Agencia 12,1000,Validos,12,00112345678\n"),
            'no_tradicional' => UploadedFile::fake()->createWithContent('no_tradicional.csv', "Agencia,Estatus,Fecha,Id Terminal,Usr. Venta,Total Apostado\nAgencia 12,Validos,2026-09-13 13:00:00,12,00112345678,400\n"),
        ])->assertSessionHasErrors('tradicional');

        $this->assertSame(1, DB::table('nomina_domingo_ventas')->count());
        $this->assertSame(999.0, (float) DB::table('nomina_domingo_ventas')->sum('total_apostado'));
    }

    public function test_rejects_a_selected_date_that_is_not_sunday(): void
    {
        $this->post(route('recursos-humanos.nomina-domingo.cargar-ventas'), [
            'destino' => 'nomina_domingo',
            'fecha_nomina' => '2026-09-14',
            'tradicional' => UploadedFile::fake()->createWithContent('tradicional.csv', "Fecha,Agencia,Total Apostado,Estatus,Terminal,Usr. Venta\n2026-09-14 12:00:00,Agencia 12,1000,Validos,12,00112345678\n"),
            'no_tradicional' => UploadedFile::fake()->createWithContent('no_tradicional.csv', "Agencia,Estatus,Fecha,Id Terminal,Usr. Venta,Total Apostado\nAgencia 12,Validos,2026-09-14 13:00:00,12,00112345678,400\n"),
        ])->assertSessionHasErrors('fecha_nomina');

        $this->assertSame(0, DB::table('nomina_domingo_ventas')->count());
    }

    public function test_rejects_the_second_file_and_rolls_back_the_first_file(): void
    {
        DB::table('nomina_domingo_ventas')->insert(['tipo' => 'Tradicional', 'fecha_transaccion' => '2026-09-13 10:00:00', 'total_apostado' => 999]);

        $this->post(route('recursos-humanos.nomina-domingo.cargar-ventas'), [
            'destino' => 'nomina_domingo',
            'fecha_nomina' => '2026-09-13',
            'tradicional' => UploadedFile::fake()->createWithContent('tradicional.csv', "Fecha,Agencia,Total Apostado,Estatus,Terminal,Usr. Venta\n2026-09-13 12:00:00,Agencia 12,1000,Validos,12,00112345678\n"),
            'no_tradicional' => UploadedFile::fake()->createWithContent('no_tradicional.csv', "Agencia,Estatus,Fecha,Id Terminal,Usr. Venta,Total Apostado\nAgencia 12,Validos,2026-09-14 13:00:00,12,00112345678,400\n"),
        ])->assertSessionHasErrors('no_tradicional');

        $this->assertSame(1, DB::table('nomina_domingo_ventas')->count());
        $this->assertSame(999.0, (float) DB::table('nomina_domingo_ventas')->sum('total_apostado'));
    }

    public function test_empty_valid_sales_do_not_erase_a_previous_upload(): void
    {
        DB::table('nomina_domingo_ventas')->insert(['tipo' => 'Tradicional', 'fecha_transaccion' => '2026-09-13 10:00:00', 'total_apostado' => 999]);

        $this->post(route('recursos-humanos.nomina-domingo.cargar-ventas'), [
            'destino' => 'nomina_domingo',
            'fecha_nomina' => '2026-09-13',
            'tradicional' => UploadedFile::fake()->createWithContent('tradicional.csv', "Fecha,Agencia,Total Apostado,Estatus,Terminal,Usr. Venta\n2026-09-13 12:00:00,Agencia 12,1000,Anulados,12,00112345678\n"),
            'no_tradicional' => UploadedFile::fake()->createWithContent('no_tradicional.csv', "Agencia,Estatus,Fecha,Id Terminal,Usr. Venta,Total Apostado\nAgencia 12,Validos,2026-09-13 13:00:00,12,00112345678,400\n"),
        ])->assertSessionHasErrors('tradicional');

        $this->assertSame(1, DB::table('nomina_domingo_ventas')->count());
        $this->assertSame(999.0, (float) DB::table('nomina_domingo_ventas')->sum('total_apostado'));
    }

    public function test_ignores_an_old_paid_record_embedded_in_the_csv_header(): void
    {
        DB::table('nomina_domingo_ventas')->insert(['tipo' => 'Tradicional', 'fecha_transaccion' => '2026-09-20 10:00:00', 'total_apostado' => 999]);

        $noTradicional = "Ticket,Consorcio,Grupo,Agencia,Estatus,Fecha,Id Terminal,Serial,Usr. Venta,Total Apostado,Total Ganado,Usr. Pago,Fec. Pago,Empresa Pago,Usr. Anulo,Fec. Anulado123,Consorcio,Grupo,Agencia,Pagados,27-07-2026 12:55:47 PM,12,Serial,00112345678,25.00,100.00,Usuario,20-09-2026 09:57:29 AM,Empresa,,\n";
        $noTradicional .= "124,Consorcio,Grupo,Agencia,Validos,20-09-2026 12:00:00 PM,12,Serial,00112345678,400.00,0.00,,,,,,\n";

        $this->post(route('recursos-humanos.nomina-domingo.cargar-ventas'), [
            'destino' => 'nomina_domingo',
            'fecha_nomina' => '2026-09-20',
            'tradicional' => UploadedFile::fake()->createWithContent('tradicional.csv', "Fecha,Agencia,Total Apostado,Estatus,Terminal,Usr. Venta\n2026-09-20 12:00:00,Agencia 12,1000,Validos,12,00112345678\n"),
            'no_tradicional' => UploadedFile::fake()->createWithContent('no_tradicional.csv', $noTradicional),
        ])->assertRedirect(route('recursos-humanos.nomina-domingo.index', ['fecha' => '2026-09-20']));

        $this->assertSame(2, DB::table('nomina_domingo_ventas')->count());
        $this->assertSame(1400.0, (float) DB::table('nomina_domingo_ventas')->sum('total_apostado'));
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

        $this->assertSame('Último login', $fila['fuente_salida']);
        $this->assertSame(8.5, $fila['horas_trabajadas']);
        $this->assertSame('8 h 30 min', $fila['horas_trabajadas_formato']);
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
        DB::table('nomina_domingo_ventas')->insert([
            'terminal' => '12',
            'usuario_venta' => '001-1234567-8',
            'fecha_transaccion' => '2026-09-13 16:50:00',
        ]);

        $fila = app(NominaDomingoService::class)->generar(Carbon::parse('2026-09-13'))->first();

        $this->assertSame('Último login', $fila['fuente_salida']);
        $this->assertSame('2026-09-13 17:00:00', $fila['salida_efectiva']);
        $this->assertSame(10.0, $fila['horas_trabajadas']);
        $this->assertSame('10 h', $fila['horas_trabajadas_formato']);
    }

    public function test_missing_last_login_requires_review_and_does_not_use_last_sale_for_hours(): void
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
        DB::table('nomina_domingo_ventas')->insert([
            'terminal' => '51405',
            'usuario_venta' => '40238675553',
            'fecha_transaccion' => '2026-09-13 17:41:00',
        ]);

        $fila = app(NominaDomingoService::class)->generar(Carbon::parse('2026-09-13'))->first();

        $this->assertSame('Sin último login', $fila['fuente_salida']);
        $this->assertNull($fila['salida_efectiva']);
        $this->assertSame(0.0, $fila['horas_trabajadas']);
        $this->assertSame('Revisar', $fila['estatus']);
        $this->assertSame(0.0, $fila['monto_pagar']);
    }

    public function test_report_can_filter_employees_by_compliance(): void
    {
        DB::table('nomina_domingo_configuraciones')->insert(['id' => 1, 'horas_requeridas' => 8, 'monto_fijo' => 1500]);
        DB::table('asistencias_bet')->insert([
            ['fecha' => '2026-09-13', 'agencia_id' => '5', 'cedula' => '00100000001', 'usuario' => 'Empleado cumple', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 16:00:00'],
            ['fecha' => '2026-09-13', 'agencia_id' => '6', 'cedula' => '00100000002', 'usuario' => 'Empleado no cumple', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 12:00:00'],
        ]);
        DB::table('empleados')->insert(['cedula' => '00100000003', 'nombres' => 'Empleado', 'apellidos' => 'revisar']);
        DB::table('nomina_domingo_ventas')->insert(['terminal' => '7', 'usuario_venta' => '00100000003', 'fecha_transaccion' => '2026-09-13 10:00:00']);

        $this->get(route('recursos-humanos.nomina-domingo.index', ['fecha' => '2026-09-13', 'consultar' => 1, 'estatus' => 'cumple']))
            ->assertOk()
            ->assertSee('Empleado cumple')
            ->assertViewHas('filas', fn ($filas): bool => $filas->pluck('empleado')->all() === ['Empleado cumple']);

        $this->get(route('recursos-humanos.nomina-domingo.index', ['fecha' => '2026-09-13', 'consultar' => 1, 'estatus' => 'no_cumple']))
            ->assertOk()
            ->assertSee('Empleado no cumple')
            ->assertViewHas('filas', fn ($filas): bool => $filas->pluck('empleado')->all() === ['Empleado no cumple']);

        $this->get(route('recursos-humanos.nomina-domingo.index', ['fecha' => '2026-09-13', 'consultar' => 1, 'estatus' => 'revisar']))
            ->assertOk()
            ->assertSee('Empleado revisar')
            ->assertViewHas('filas', fn ($filas): bool => $filas->pluck('empleado')->all() === ['Empleado revisar']);
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

    public function test_excel_table_uses_company_and_compliance_filters_together(): void
    {
        DB::table('nomina_domingo_configuraciones')->insert(['id' => 1, 'horas_requeridas' => 8, 'monto_fijo' => 1500]);
        DB::table('agencias')->insert([
            ['terminal' => '5', 'empresa' => 'Empresa Norte'],
            ['terminal' => '6', 'empresa' => 'Empresa Sur'],
        ]);
        DB::table('asistencias_bet')->insert([
            ['fecha' => '2026-09-13', 'agencia_id' => '5', 'cedula' => '00100000001', 'usuario' => 'Norte cumple', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 16:00:00'],
            ['fecha' => '2026-09-13', 'agencia_id' => '5', 'cedula' => '00100000002', 'usuario' => 'Norte no cumple', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 12:00:00'],
            ['fecha' => '2026-09-13', 'agencia_id' => '6', 'cedula' => '00100000003', 'usuario' => 'Sur cumple', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 16:00:00'],
        ]);

        $this->get(route('recursos-humanos.nomina-domingo.index', [
            'fecha' => '2026-09-13', 'consultar' => 1, 'empresa' => 'Empresa Norte', 'estatus' => 'cumple',
        ]))
            ->assertOk()
            ->assertViewHas('filas', fn ($filas): bool => $filas->pluck('empleado')->all() === ['Norte cumple'])
            ->assertSee('>Norte cumple<br>', false)
            ->assertDontSee('>Norte no cumple<br>', false)
            ->assertDontSee('>Sur cumple<br>', false)
            ->assertSee("modifier: { search: 'applied', order: 'applied', page: 'all' }", false);
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
            ->assertSee('No cumplen o revisar')
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
        DB::table('nomina_domingo_ventas')->insert([
            ['terminal' => '99', 'usuario_venta' => '00100000009', 'fecha_transaccion' => '2026-09-13 08:15:00', 'tipo' => 'Tradicional', 'total_apostado' => 100],
            ['terminal' => '99', 'usuario_venta' => '00100000009', 'fecha_transaccion' => '2026-09-13 17:15:00', 'tipo' => 'No Tradicional', 'total_apostado' => 50],
            ['terminal' => '50192', 'usuario_venta' => '301175360', 'fecha_transaccion' => '2026-09-13 09:00:00', 'tipo' => 'Tradicional', 'total_apostado' => 25],
        ]);
        DB::table('empleados')->insert(['cedula' => '00301175360', 'nombres' => 'Ramona', 'apellidos' => 'Sanchez']);

        $this->get(route('recursos-humanos.nomina-domingo.index', ['fecha' => '2026-09-13', 'consultar' => 1]))
            ->assertOk()
            ->assertSee('Ver registros')
            ->assertSee('Registros sin primer login')
            ->assertSee("title: 'Nomina_Domingo_Sin_Primer_Login_2026-09-13'", false)
            ->assertSee("$('#tablaSinPrimerLogin').DataTable({", false)
            ->assertSee('<th>Cédula</th><th>ID</th>', false)
            ->assertSee('<td>00301175360</td>', false)
            ->assertSee('<td>301175360</td>', false)
            ->assertSee('<td></td>', false)
            ->assertSee('<th>Ventas</th>', false)
            ->assertSee('2 ventas<br><small>RD$ 150.00</small>', false)
            ->assertSee('Primera venta')
            ->assertSee('13/09/2026 08:15 AM')
            ->assertSee('00100000009')
            ->assertSee('Sin primer login');
    }

    public function test_report_waits_for_the_generate_action(): void
    {
        DB::table('nomina_domingo_ventas')->insert([
            'tipo' => 'Tradicional',
            'fecha_transaccion' => '2026-09-13 12:00:00',
            'total_apostado' => 1000,
        ]);

        $this->get(route('recursos-humanos.nomina-domingo.index', ['fecha' => '2026-09-13']))
            ->assertOk()
            ->assertViewHas('consultar', false)
            ->assertSee('Generar reporte')
            ->assertSee('id="formGenerarNominaDomingo"', false)
            ->assertSee('id="btnGenerarNominaDomingo"', false)
            ->assertSee('Generando reporte...')
            ->assertDontSee('Datos limpios')
            ->assertDontSee('Conciliación Tradicional');

        $this->get(route('recursos-humanos.nomina-domingo.index', ['fecha' => '2026-09-13', 'consultar' => 1]))
            ->assertOk()
            ->assertViewHas('consultar', true)
            ->assertSee('Datos limpios')
            ->assertSee('RD$ 1,000.00');
    }

    public function test_report_screen_contains_requested_datatable_and_configuration(): void
    {
        DB::table('asistencias_bet')->insert([
            'fecha' => '2026-09-13', 'agencia_id' => '12', 'cedula' => '00112345678',
            'usuario' => '00112345678', 'primer_login' => '2026-09-13 08:00:00', 'ultimo_login' => '2026-09-13 16:00:00',
        ]);
        DB::table('agencias')->insert(['terminal' => '12', 'empresa' => 'Empresa Norte']);

        $this->get(route('recursos-humanos.nomina-domingo.index', ['fecha' => '2026-09-13', 'consultar' => 1]))
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
            ->assertSee('Guardando ventas...')
            ->assertSee('id="formFiltrarNominaDomingo"', false)
            ->assertSee('id="btnFiltrarNominaDomingo"', false)
            ->assertSee('Aplicando el filtro...')
            ->assertSee('Descargar Excel')
            ->assertSee('<th>Cédula</th><th>Empleado</th><th>Empresa</th><th>Coordinador</th><th>Ponche resumido</th>', false)
            ->assertSee('<td>00112345678</td>', false)
            ->assertSee('class="empleado-sin-maestra"', false)
            ->assertSee('Validar usuario con la maestra de empleados')
            ->assertSee('<td>Empresa Norte</td>', false)
            ->assertSee('Primer login: 08:00 AM | Último login: 04:00 PM')
            ->assertSee('columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]');
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
