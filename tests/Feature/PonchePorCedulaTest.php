<?php

namespace Tests\Feature;

use App\ViewPermissionCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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
            $table->dateTime('primer_login')->nullable();
            $table->dateTime('ultimo_login')->nullable();
        });
        Schema::create('asistencias_net', function (Blueprint $table): void {
            $table->id('asistencia_id');
            $table->string('identificacion');
            $table->dateTime('entrada')->nullable();
            $table->dateTime('salida')->nullable();
        });
        Schema::create('empleados', function (Blueprint $table): void {
            $table->id();
            $table->string('cedula');
            $table->string('nombres')->nullable();
            $table->string('apellidos')->nullable();
            $table->date('fechasalida')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('empleados');
        Schema::dropIfExists('asistencias_net');
        Schema::dropIfExists('asistencias_bet');
        parent::tearDown();
    }

    public function test_report_groups_first_and_last_login_by_day_and_system(): void
    {
        DB::table('empleados')->insert(['cedula' => '402-4046149-7', 'nombres' => 'Ana', 'apellidos' => 'Pérez']);
        DB::table('asistencias_bet')->insert([
            ['fecha' => '2026-10-10', 'cedula' => '402-4046149-7', 'primer_login' => '2026-10-10 08:00:00', 'ultimo_login' => '2026-10-10 12:00:00'],
            ['fecha' => '2026-10-10', 'cedula' => '40240461497', 'primer_login' => '2026-10-10 13:00:00', 'ultimo_login' => '2026-10-10 17:30:00'],
            ['fecha' => '2026-10-11', 'cedula' => '40240461497', 'primer_login' => '2026-10-11 08:00:00', 'ultimo_login' => null],
            ['fecha' => '2026-10-10', 'cedula' => '00100000001', 'primer_login' => '2026-10-10 08:00:00', 'ultimo_login' => '2026-10-10 18:00:00'],
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

    public function test_report_has_its_own_permission_on_both_routes(): void
    {
        $permission = 'recursos_humanos.ponche_por_cedula.view';
        $item = collect(config('recursos_humanos'))->firstWhere('url', '/recursos-humanos/ponche-por-cedula');

        $this->assertSame($permission, app(ViewPermissionCatalog::class)->permission('recursos_humanos', $item));
        $this->assertContains('permission:'.$permission, Route::getRoutes()->getByName('recursos-humanos.ponche-por-cedula.index')->gatherMiddleware());
        $this->assertContains('permission:'.$permission, Route::getRoutes()->getByName('recursos-humanos.ponche-por-cedula.list')->gatherMiddleware());
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
