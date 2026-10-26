<?php

namespace Tests\Feature;

use App\Http\Middleware\PreventDeletionForAdmin2;
use App\Models\User;
use App\ViewPermissionCatalog;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Admin2RoleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('must_change_password')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });
        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['role_has_permissions', 'model_has_permissions', 'model_has_roles', 'permissions', 'roles', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_admin2_has_all_configured_permissions_except_delete_permissions(): void
    {
        $permissions = Role::findByName('admin2')->permissions->pluck('name');

        $this->assertTrue($permissions->contains('dashboard.view'));
        $this->assertFalse($permissions->contains('usuarios.create'));
        $this->assertTrue($permissions->contains('usuarios.edit'));
        $this->assertFalse($permissions->contains('usuarios.delete'));
        $this->assertFalse($permissions->contains('roles.delete'));
        $this->assertFalse($permissions->contains('permissions.delete'));
    }

    public function test_task_assigner_role_can_create_tasks_and_viewers_cannot_assign(): void
    {
        $assignerRole = Role::findByName('asignador_tareas', 'web');
        $this->assertTrue($assignerRole->hasPermissionTo('tareas.assign'));
        $this->assertTrue($assignerRole->hasPermissionTo('tareas.panel.view'));

        $viewerRole = Role::findOrCreate('visor_tareas', 'web');
        $viewerRole->givePermissionTo(Permission::findByName('tareas.panel.view', 'web'));

        $viewer = User::query()->create(['name' => 'Visor', 'email' => 'visor@example.com', 'password' => 'password']);
        $viewer->assignRole($viewerRole);
        $this->actingAs($viewer)->post(route('tareas.store'), [])->assertForbidden();

        $assigner = User::query()->create(['name' => 'Asignador', 'email' => 'asignador@example.com', 'password' => 'password']);
        $assigner->assignRole($assignerRole);
        $this->actingAs($assigner)->postJson(route('tareas.store'), [])->assertUnprocessable();
    }

    public function test_task_assigner_migration_creates_role_and_permission(): void
    {
        Role::findByName('asignador_tareas', 'web')->delete();
        Permission::findByName('tareas.assign', 'web')->delete();

        $migration = require database_path('migrations/2026_10_25_234400_create_asignador_tareas_role_and_permission.php');
        $migration->up();

        $role = Role::findByName('asignador_tareas', 'web');
        $this->assertTrue($role->hasPermissionTo('tareas.assign'));
        $this->assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('tareas.assign'));
    }

    public function test_task_viewer_cannot_change_assignee_on_an_existing_task(): void
    {
        Schema::create('tareas', function (Blueprint $table): void {
            $table->id();
            $table->string('titulo');
            $table->unsignedBigInteger('asignado_id')->nullable();
            $table->timestamps();
        });

        $viewerRole = Role::findOrCreate('visor_tareas', 'web');
        $viewerRole->givePermissionTo(Permission::findByName('tareas.panel.view', 'web'));
        $viewer = User::query()->create(['name' => 'Visor', 'email' => 'visor@example.com', 'password' => 'password']);
        $viewer->assignRole($viewerRole);
        $task = \App\Models\Tarea::query()->create(['titulo' => 'Tarea', 'asignado_id' => $viewer->id]);

        $this->actingAs($viewer)
            ->putJson(route('tareas.update', $task), ['asignado_id' => $viewer->id + 1])
            ->assertForbidden();

        $this->assertSame($viewer->id, $task->fresh()->asignado_id);
        Schema::dropIfExists('tareas');
    }

    public function test_bi_tv_role_only_grants_the_tv_view(): void
    {
        $role = Role::findByName('bi-tv', 'web');
        $this->assertSame(['dashboard.bi_tv.view'], $role->permissions->pluck('name')->all());

        $user = User::query()->create(['name' => 'Pantalla TV', 'email' => 'tv@example.com', 'password' => 'password']);
        $user->assignRole($role);
        $catalog = app(ViewPermissionCatalog::class);

        $this->assertSame('/bi-tv', $catalog->firstAllowedPathFor($user));
        $this->assertTrue($catalog->allowsPath($user, '/bi-tv'));
        $this->assertFalse($catalog->allowsPath($user, '/bi'));
        $this->assertFalse($catalog->allowsPath($user, '/'));

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/bi-tv');
        $this->get('/bi')->assertForbidden();
    }

    public function test_bi_tv_migration_creates_role_and_permission_on_deploy(): void
    {
        Role::findByName('bi-tv', 'web')->delete();
        \Spatie\Permission\Models\Permission::findByName('dashboard.bi_tv.view', 'web')->delete();
        $migration = require database_path('migrations/2026_10_12_100522_create_bi_tv_role_and_permission.php');

        $migration->up();
        $migration->up();

        $this->assertSame(1, Role::query()->where('name', 'bi-tv')->count());
        $this->assertTrue(Role::findByName('bi-tv', 'web')->hasPermissionTo('dashboard.bi_tv.view'));
    }

    public function test_catalog_pages_have_permissions_and_roles_receive_their_module_pages(): void
    {
        $catalog = app(ViewPermissionCatalog::class);
        $items = $catalog->items();

        $this->assertCount(
            collect(config('module_hubs'))->sum(fn (array $hub): int => count($hub['items'] ?? []))
                + count(config('recursos_humanos'))
                + count(config('reportes'))
                + collect(config('view_permissions'))->sum(fn (array $pages): int => count($pages)),
            $items
        );
        $this->assertTrue($items->every(fn (array $item): bool => $item['permission'] !== ''));
        $this->assertTrue(Role::findByName('rh')->hasPermissionTo('recursos_humanos.nomina_domingo.view'));
        $this->assertTrue(Role::findByName('rh')->hasPermissionTo('reportes.ventas_por_cedula.view'));
        $this->assertFalse(Role::findByName('rh')->hasPermissionTo('gerencia.gerencial.view'));
    }

    public function test_page_permission_blocks_a_direct_request_without_the_required_permission(): void
    {
        $user = User::query()->create(['name' => 'Recursos humanos', 'email' => 'rh@example.com', 'password' => 'password']);
        $user->assignRole('rh');

        $allowed = Request::create('/recursos-humanos/nomina-domingo', 'GET');
        $allowed->setUserResolver(fn (): User => $user);
        $this->assertSame(200, app(\App\Http\Middleware\EnsureViewPermission::class)->handle($allowed, fn () => response('permitido'))->getStatusCode());

        $this->actingAs($user)->get(route('gerencia.gerencial'))->assertForbidden();
        $this->actingAs($user)->get(route('gerencia.index'))->assertForbidden();

        $limitedRole = Role::findOrCreate('solo-rh', 'web');
        $limitedRole->givePermissionTo('recursos_humanos.view');
        $limitedUser = User::query()->create(['name' => 'Acceso general RH', 'email' => 'solo-rh@example.com', 'password' => 'password']);
        $limitedUser->assignRole($limitedRole);
        $this->actingAs($limitedUser)->get(route('recursos-humanos.nomina-domingo.index'))->assertForbidden();

        $denied = Request::create('/gerencia/gerencial', 'GET');
        $denied->setUserResolver(fn (): User => $user);
        $this->expectException(HttpException::class);
        app(\App\Http\Middleware\EnsureViewPermission::class)->handle($denied, fn () => response('permitido'));
    }

    public function test_admin2_is_blocked_from_delete_requests(): void
    {
        $request = $this->requestForAdmin2('/operaciones/distribucion-gastos-ruta/rutas', 'DELETE');

        try {
            app(PreventDeletionForAdmin2::class)->handle($request, fn () => response('permitido'));
            $this->fail('El middleware permitio una solicitud DELETE para admin2.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_admin2_is_blocked_from_legacy_get_delete_endpoints(): void
    {
        $request = $this->requestForAdmin2('/delete-ventas-usuarios-lotobet', 'GET');

        try {
            app(PreventDeletionForAdmin2::class)->handle($request, fn () => response('permitido'));
            $this->fail('El middleware permitio un endpoint GET destructivo para admin2.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_admin2_cannot_create_users_even_with_a_direct_permission(): void
    {
        $request = $this->requestForAdmin2('/usuarios/create', 'GET');
        $user = $request->user();
        $user->givePermissionTo('usuarios.create');

        $this->assertFalse($user->can('usuarios.create'));
        $this->actingAs($user)->get(route('usuarios.create'))->assertForbidden();
        $this->actingAs($user)->post(route('usuarios.store'), [])->assertForbidden();

        $view = file_get_contents(resource_path('views/usuarios/index.blade.php'));
        $this->assertStringContainsString("! auth()->user()?->hasRole('admin2')", $view);
    }

    public function test_admin2_can_use_regular_pages_and_view_restricted_audit_routes(): void
    {
        $request = $this->requestForAdmin2('/recursos-humanos/nomina-domingo', 'GET');
        $response = app(PreventDeletionForAdmin2::class)->handle($request, fn () => response('permitido'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertContains('role:superadmin|admin2', Route::getRoutes()->getByName('coordinador-operador.auditoria')->gatherMiddleware());
        $this->assertContains('role:superadmin|admin2', Route::getRoutes()->getByName('incentivos.incentivo-administrativo.auditoria')->gatherMiddleware());
    }

    public function test_layout_hides_static_and_dynamic_delete_controls_for_admin2(): void
    {
        $layout = file_get_contents(resource_path('views/app.blade.php'));

        $this->assertStringContainsString("hasRole('admin2')", $layout);
        $this->assertStringContainsString('const patronEliminar = /(eliminar|borrar|delete|destroy)/i;', $layout);
        $this->assertStringContainsString('new MutationObserver(ocultarControlesEliminar)', $layout);
        $this->assertStringContainsString("metodoForzado === 'DELETE'", $layout);
    }

    private function requestForAdmin2(string $uri, string $method): Request
    {
        $user = User::query()->create([
            'name' => 'Administrador dos',
            'email' => uniqid('admin2-', true).'@example.com',
            'password' => 'password',
        ]);
        $user->assignRole('admin2');
        $request = Request::create($uri, $method);
        $request->setUserResolver(fn (): User => $user);

        return $request;
    }
}
