<?php

namespace Tests\Feature;

use App\ViewPermissionCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PonchePorCedulaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();

        Schema::create('asistencias_bet', function (Blueprint $table): void {
            $table->id('asistencia_id');
            $table->date('fecha');
            $table->string('cedula');
            $table->string('agencia_id')->nullable();
            $table->string('usuario')->nullable();
            $table->dateTime('primer_login')->nullable();
            $table->dateTime('ultimo_login')->nullable();
        });
        Schema::create('asistencias_net', function (Blueprint $table): void {
            $table->id('asistencia_id');
            $table->string('identificacion');
            $table->string('terminal')->nullable();
            $table->string('usuario')->nullable();
            $table->string('username')->nullable();
            $table->dateTime('entrada')->nullable();
            $table->dateTime('salida')->nullable();
        });
        Schema::create('empleados', function (Blueprint $table): void {
            $table->id();
            $table->string('cedula');
            $table->string('empleadoid')->nullable();
            $table->string('nombres')->nullable();
            $table->string('apellidos')->nullable();
            $table->date('fechasalida')->nullable();
        });
        Schema::create('nomina_domingo_ventas', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal')->nullable();
            $table->string('usuario_venta')->nullable();
            $table->dateTime('fecha_transaccion')->nullable();
            $table->string('tipo')->nullable();
            $table->decimal('total_apostado', 12, 2)->nullable();
            $table->string('estatus')->nullable();
        });
        (require database_path('migrations/2026_10_14_125659_create_ponche_por_cedula_ventas_table.php'))->up();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('ponche_por_cedula_ventas');
        Schema::dropIfExists('nomina_domingo_ventas');
        Schema::dropIfExists('empleados');
        Schema::dropIfExists('asistencias_net');
        Schema::dropIfExists('asistencias_bet');
        parent::tearDown();
    }

    public function test_report_groups_first_and_last_login_by_day_and_system(): void
    {
        DB::table('empleados')->insert(['cedula' => '402-4046149-7', 'nombres' => 'Ana', 'apellidos' => 'Pérez']);
        DB::table('asistencias_bet')->insert([
            ['fecha' => '2026-10-10', 'cedula' => '402-4046149-7', 'agencia_id' => '0012', 'primer_login' => '2026-10-10 08:00:00', 'ultimo_login' => '2026-10-10 12:00:00'],
            ['fecha' => '2026-10-10', 'cedula' => '40240461497', 'agencia_id' => '0012', 'primer_login' => '2026-10-10 13:00:00', 'ultimo_login' => '2026-10-10 17:30:00'],
            ['fecha' => '2026-10-11', 'cedula' => '40240461497', 'agencia_id' => '0012', 'primer_login' => '2026-10-11 08:00:00', 'ultimo_login' => null],
            ['fecha' => '2026-10-10', 'cedula' => '00100000001', 'agencia_id' => '0013', 'primer_login' => '2026-10-10 08:00:00', 'ultimo_login' => '2026-10-10 18:00:00'],
        ]);
        DB::table('asistencias_net')->insert([
            ['identificacion' => '40240461497', 'entrada' => '2026-10-10 09:00:00', 'salida' => '2026-10-10 18:00:00'],
            ['identificacion' => '00100000001', 'entrada' => '2026-10-10 08:00:00', 'salida' => '2026-10-10 18:00:00'],
        ]);

        $this->assertStringContainsString('Primer login', file_get_contents(resource_path('views/recursos_humanos/ponche_por_cedula.blade.php')));
        $this->assertStringContainsString('id="totalHoras"', file_get_contents(resource_path('views/recursos_humanos/ponche_por_cedula.blade.php')));

        $this->getJson(route('recursos-humanos.ponche-por-cedula.list', [
            'cedula' => '402-4046149-7', 'sistema' => 'todos', 'fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-11',
        ]))->assertOk()
            ->assertJsonCount(3)
            ->assertJsonPath('0.sistema', 'Lotobet')
            ->assertJsonPath('0.nombre', 'Ana Pérez')
            ->assertJsonPath('0.primer_login', '2026-10-10 08:00:00')
            ->assertJsonPath('0.ultimo_login', '2026-10-10 17:30:00')
            ->assertJsonPath('0.total_horas', 9.5)
            ->assertJsonPath('1.sistema', 'Lotonet')
            ->assertJsonPath('1.nombre', 'Ana Pérez')
            ->assertJsonPath('1.total_horas', 9)
            ->assertJsonPath('2.total_horas', 0);
    }

    public function test_report_filters_system_and_rejects_invalid_range(): void
    {
        DB::table('asistencias_net')->insert(['identificacion' => '40240461497', 'entrada' => '2026-10-10 09:00:00', 'salida' => '2026-10-10 18:00:00']);

        $this->getJson(route('recursos-humanos.ponche-por-cedula.list', [
            'cedula' => '40240461497', 'sistema' => 'lotobet', 'fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-11',
        ]))->assertOk()->assertExactJson([]);

        $this->getJson(route('recursos-humanos.ponche-por-cedula.list', [
            'cedula' => '40240461497', 'sistema' => 'lotonet', 'fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-11',
        ]))->assertOk()->assertJsonPath('0.nombre', 'No identificado');

        $this->getJson(route('recursos-humanos.ponche-por-cedula.list', [
            'cedula' => '40240461497', 'sistema' => 'todos', 'fecha_inicio' => '2026-10-11', 'fecha_fin' => '2026-10-10',
        ]))->assertUnprocessable()->assertJsonValidationErrors('fecha_fin');
    }

    public function test_sunday_uses_payroll_exit_adjusted_to_last_sale_plus_five_minutes(): void
    {
        DB::table('asistencias_bet')->insert([
            'fecha' => '2026-10-11', 'agencia_id' => '0012', 'cedula' => '40240461497', 'usuario' => '40240461497',
            'primer_login' => '2026-10-11 08:00:00', 'ultimo_login' => '2026-10-11 14:00:00',
        ]);
        DB::table('ponche_por_cedula_ventas')->insert([
            ['terminal' => '12', 'usuario_venta' => '402-4046149-7', 'fecha_transaccion' => '2026-10-11 16:30:00', 'tipo' => 'Tradicional', 'total_apostado' => 100, 'estatus' => 'Aplicado'],
            ['terminal' => '99', 'usuario_venta' => '402-4046149-7', 'fecha_transaccion' => '2026-10-11 20:00:00', 'tipo' => 'Tradicional', 'total_apostado' => 100, 'estatus' => 'Aplicado'],
        ]);

        $this->getJson(route('recursos-humanos.ponche-por-cedula.list', [
            'cedula' => '40240461497', 'sistema' => 'lotobet', 'fecha_inicio' => '2026-10-11', 'fecha_fin' => '2026-10-11',
        ]))->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.primer_login', '2026-10-11 08:00:00')
            ->assertJsonPath('0.ultimo_login', '2026-10-11 14:00:00')
            ->assertJsonPath('0.salida_calculada', '2026-10-11 16:35:00')
            ->assertJsonMissingPath('0.estado')
            ->assertJsonPath('0.total_horas', 8.58);
    }

    public function test_sunday_keeps_last_login_when_sales_are_earlier_or_pending(): void
    {
        DB::table('asistencias_bet')->insert([
            ['fecha' => '2026-10-11', 'agencia_id' => '0013', 'cedula' => '40240461497', 'usuario' => '40240461497', 'primer_login' => '2026-10-11 08:00:00', 'ultimo_login' => '2026-10-11 18:00:00'],
            ['fecha' => '2026-10-12', 'agencia_id' => '0013', 'cedula' => '40240461497', 'usuario' => '40240461497', 'primer_login' => '2026-10-12 08:00:00', 'ultimo_login' => '2026-10-12 18:00:00'],
        ]);
        DB::table('ponche_por_cedula_ventas')->insert([
            ['terminal' => '13', 'usuario_venta' => '40240461497', 'fecha_transaccion' => '2026-10-11 17:00:00', 'tipo' => 'Tradicional', 'estatus' => 'Aplicado'],
            ['terminal' => '13', 'usuario_venta' => '40240461497', 'fecha_transaccion' => '2026-10-11 19:00:00', 'tipo' => 'Recargas', 'estatus' => 'Pendiente'],
            ['terminal' => '13', 'usuario_venta' => '00100000001', 'fecha_transaccion' => '2026-10-11 20:00:00', 'tipo' => 'Tradicional', 'estatus' => 'Aplicado'],
            ['terminal' => '13', 'usuario_venta' => '40240461497', 'fecha_transaccion' => '2026-10-12 20:00:00', 'tipo' => 'Tradicional', 'estatus' => 'Aplicado'],
        ]);

        $this->getJson(route('recursos-humanos.ponche-por-cedula.list', [
            'cedula' => '40240461497', 'sistema' => 'lotobet', 'fecha_inicio' => '2026-10-11', 'fecha_fin' => '2026-10-12',
        ]))->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.salida_calculada', '2026-10-11 18:00:00')
            ->assertJsonPath('0.total_horas', 10)
            ->assertJsonPath('1.salida_calculada', '2026-10-12 20:05:00')
            ->assertJsonPath('1.total_horas', 12.08);
    }

    public function test_report_has_its_own_permission_on_both_routes(): void
    {
        $permission = 'recursos_humanos.ponche_por_cedula.view';
        $item = collect(config('recursos_humanos'))->firstWhere('url', '/recursos-humanos/ponche-por-cedula');

        $this->assertSame($permission, app(ViewPermissionCatalog::class)->permission('recursos_humanos', $item));
        $this->assertContains('permission:'.$permission, Route::getRoutes()->getByName('recursos-humanos.ponche-por-cedula.index')->gatherMiddleware());
        $this->assertContains('permission:'.$permission, Route::getRoutes()->getByName('recursos-humanos.ponche-por-cedula.list')->gatherMiddleware());
        $this->assertContains('permission:'.$permission, Route::getRoutes()->getByName('recursos-humanos.ponche-por-cedula.cargar-ventas')->gatherMiddleware());
    }

    public function test_weekday_upload_is_exclusive_and_replaces_only_its_date(): void
    {
        DB::table('asistencias_bet')->insert([
            'fecha' => '2026-10-12', 'agencia_id' => '0012', 'cedula' => '40240461497',
            'primer_login' => '2026-10-12 08:00:00', 'ultimo_login' => '2026-10-12 14:00:00',
        ]);
        DB::table('nomina_domingo_ventas')->insert([
            'terminal' => '12', 'usuario_venta' => '40240461497', 'fecha_transaccion' => '2026-10-12 22:00:00',
            'tipo' => 'Tradicional', 'estatus' => 'Validos',
        ]);

        $this->post(route('recursos-humanos.ponche-por-cedula.cargar-ventas'), $this->archivosVentas('2026-10-12', '16:30:00'))
            ->assertRedirect(route('recursos-humanos.ponche-por-cedula.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, DB::table('nomina_domingo_ventas')->count());
        $this->assertSame(3, DB::table('ponche_por_cedula_ventas')->count());
        $this->getJson(route('recursos-humanos.ponche-por-cedula.list', [
            'cedula' => '40240461497', 'sistema' => 'lotobet', 'fecha_inicio' => '2026-10-12', 'fecha_fin' => '2026-10-12',
        ]))->assertOk()->assertJsonPath('0.salida_calculada', '2026-10-12 16:35:00');

        $this->post(route('recursos-humanos.ponche-por-cedula.cargar-ventas'), $this->archivosVentas('2026-10-12', '17:30:00'))
            ->assertRedirect(route('recursos-humanos.ponche-por-cedula.index'));
        $this->assertSame(3, DB::table('ponche_por_cedula_ventas')->count());

        $archivos = $this->archivosVentas('2026-10-12', '18:30:00');
        $archivos['no_tradicional'] = UploadedFile::fake()->createWithContent('no_tradicional.csv', "Agencia,Estatus,Fecha,Id Terminal,Usr. Venta,Total Apostado\nAgencia 12,Validos,2026-10-13 13:00:00,12,40240461497,400\n");
        $this->post(route('recursos-humanos.ponche-por-cedula.cargar-ventas'), $archivos)
            ->assertSessionHasErrors('no_tradicional');
        $this->assertSame(3, DB::table('ponche_por_cedula_ventas')->count());
        $this->assertTrue(DB::table('ponche_por_cedula_ventas')->where('fecha_transaccion', '2026-10-12 17:30:00')->exists());
    }

    public function test_upload_inserts_rows_after_a_full_two_thousand_row_batch(): void
    {
        $archivos = $this->archivosVentas('2026-10-12', '16:30:00');
        $fila = "2026-10-12 16:30:00,Agencia 12,1000,Validos,12,40240461497\n";
        $archivos['tradicional'] = UploadedFile::fake()->createWithContent(
            'tradicional.csv',
            "Fecha,Agencia,Total Apostado,Estatus,Terminal,Usr. Venta\n".str_repeat($fila, 2001)
        );

        $this->post(route('recursos-humanos.ponche-por-cedula.cargar-ventas'), $archivos)
            ->assertRedirect(route('recursos-humanos.ponche-por-cedula.index'));

        $this->assertSame(2001, DB::table('ponche_por_cedula_ventas')->where('tipo', 'Tradicional')->count());
        $this->assertSame(2003, DB::table('ponche_por_cedula_ventas')->count());
    }

    /** @return array<string, mixed> */
    private function archivosVentas(string $fecha, string $hora): array
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A10', 'Estatus: Confirmada');
        foreach (['A' => 'Agencia', 'K' => 'Fecha', 'R' => 'Monto', 'S' => 'Usr. Venta'] as $columna => $encabezado) {
            $sheet->setCellValue($columna.'13', $encabezado);
        }
        $sheet->setCellValue('A17', '20267');
        $sheet->setCellValue('K17', $fecha.' 03:00:00 PM');
        $sheet->setCellValue('R17', 150);
        $sheet->setCellValue('S17', '40240461497');
        $path = tempnam(sys_get_temp_dir(), 'recargas_');
        (new Xlsx($spreadsheet))->save($path);
        $recargas = UploadedFile::fake()->createWithContent('recargas.xlsx', file_get_contents($path));
        unlink($path);
        $spreadsheet->disconnectWorksheets();

        return [
            'fecha' => $fecha,
            'tradicional' => UploadedFile::fake()->createWithContent('tradicional.csv', "Fecha,Agencia,Total Apostado,Estatus,Terminal,Usr. Venta\n$fecha $hora,Agencia 12,1000,Validos,12,40240461497\n"),
            'no_tradicional' => UploadedFile::fake()->createWithContent('no_tradicional.csv', "Agencia,Estatus,Fecha,Id Terminal,Usr. Venta,Total Apostado\nAgencia 12,Validos,$fecha 13:00:00,12,40240461497,400\n"),
            'recargas' => $recargas,
        ];
    }

    public function test_migration_creates_permission_and_grants_it_to_human_resources(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('rh', 'web');
        Role::findOrCreate('admin', 'web');
        $migration = require database_path('migrations/2026_10_14_102322_create_ponche_por_cedula_permission.php');
        $migration->up();
        $migration->up();

        $this->assertSame(1, Permission::query()->where('name', 'recursos_humanos.ponche_por_cedula.view')->count());
        $this->assertTrue(Role::findByName('rh', 'web')->hasPermissionTo('recursos_humanos.ponche_por_cedula.view'));
        $this->assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('recursos_humanos.ponche_por_cedula.view'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
}
