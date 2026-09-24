<?php

namespace Tests\Feature;

use App\Http\Middleware\PreventDeletionForAdmin2;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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
        $this->assertTrue($permissions->contains('usuarios.create'));
        $this->assertTrue($permissions->contains('usuarios.edit'));
        $this->assertFalse($permissions->contains('usuarios.delete'));
        $this->assertFalse($permissions->contains('roles.delete'));
        $this->assertFalse($permissions->contains('permissions.delete'));
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
