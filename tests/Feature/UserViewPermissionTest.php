<?php

namespace Tests\Feature;

use App\Http\Middleware\ExpireInactiveSession;
use App\Http\Middleware\ForcePasswordChange;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserViewPermissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ExpireInactiveSession::class, ForcePasswordChange::class]);

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
        Schema::create('user_view_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('permission_id');
            $table->boolean('allowed');
            $table->timestamps();
            $table->unique(['user_id', 'permission_id']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('recursos_humanos.view', 'web');
        Permission::findOrCreate('recursos_humanos.nomina_domingo.view', 'web');
        Permission::findOrCreate('gerencia.gerencial.view', 'web');
        Permission::findOrCreate('usuarios.view', 'web');
        Permission::findOrCreate('usuarios.edit', 'web');
        Permission::findOrCreate('usuarios.create', 'web');
        $role = Role::findOrCreate('rh', 'web');
        $role->givePermissionTo(['recursos_humanos.view', 'recursos_humanos.nomina_domingo.view']);
        Role::findOrCreate('superadmin', 'web');
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['user_view_permissions', 'role_has_permissions', 'model_has_permissions', 'model_has_roles', 'permissions', 'roles', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_user_can_be_blocked_and_restored_even_when_role_allows_a_page(): void
    {
        $target = $this->user('target@example.com');
        $target->assignRole('rh');
        $this->actingAs($this->administrator());

        $this->get(route('usuarios.edit', $target))->assertOk()->assertSee('Acceso a páginas')->assertSee('Bloquear');

        $this->put(route('usuarios.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => ['rh'],
            'view_permissions' => [['permission' => 'recursos_humanos.nomina_domingo.view', 'access' => 'deny']],
        ])->assertSessionHasNoErrors();

        $target = $target->fresh();
        $this->assertFalse($target->can('recursos_humanos.nomina_domingo.view'));
        $this->actingAs($target)->get(route('recursos-humanos.nomina-domingo.index'))->assertForbidden();

        $this->actingAs($this->administrator())->put(route('usuarios.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => ['rh'],
            'view_permissions' => [['permission' => 'recursos_humanos.nomina_domingo.view', 'access' => 'inherit']],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('user_view_permissions', 0);
        $this->assertTrue($target->fresh()->can('recursos_humanos.nomina_domingo.view'));
    }

    public function test_user_can_receive_page_access_when_role_does_not_grant_it(): void
    {
        $target = $this->user('target@example.com');
        $this->actingAs($this->administrator());

        $this->put(route('usuarios.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'view_permissions' => [['permission' => 'recursos_humanos.nomina_domingo.view', 'access' => 'allow']],
        ])->assertSessionHasNoErrors();

        $target = $target->fresh();
        $this->assertTrue($target->can('recursos_humanos.nomina_domingo.view'));
        $this->assertTrue($target->can('recursos_humanos.view'));
        $this->actingAs($target)->get(route('recursos-humanos.index'))
            ->assertOk()
            ->assertSee('Nómina Domingo');
    }

    public function test_explicit_page_access_appears_in_navigation_for_a_restricted_role(): void
    {
        $target = $this->user('target@example.com');
        $target->assignRole('rh');
        $this->actingAs($this->administrator())->put(route('usuarios.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => ['rh'],
            'view_permissions' => [['permission' => 'gerencia.gerencial.view', 'access' => 'allow']],
        ])->assertSessionHasNoErrors();

        $this->actingAs($target->fresh())->get(route('recursos-humanos.index'))
            ->assertOk()
            ->assertSee('data-key="t-gerencia"', false);
    }

    public function test_user_creation_accepts_page_decisions_and_rejects_unknown_permissions(): void
    {
        Mail::fake();
        $this->actingAs($this->administrator());
        $this->get(route('usuarios.create'))->assertOk()->assertSee('Acceso a páginas');

        $this->post(route('usuarios.store'), [
            'name' => 'Nuevo usuario',
            'email' => 'nuevo@example.com',
            'roles' => ['rh'],
            'view_permissions' => [['permission' => 'recursos_humanos.nomina_domingo.view', 'access' => 'deny']],
        ])->assertSessionHasNoErrors();

        $this->assertFalse(User::query()->where('email', 'nuevo@example.com')->firstOrFail()->can('recursos_humanos.nomina_domingo.view'));

        $this->post(route('usuarios.store'), [
            'name' => 'Inválido',
            'email' => 'invalido@example.com',
            'view_permissions' => [['permission' => 'inventado.view', 'access' => 'allow']],
        ])->assertInvalid(['view_permissions.0.permission']);
    }

    private function user(string $email): User
    {
        return User::query()->create(['name' => 'Usuario', 'email' => $email, 'password' => 'password']);
    }

    private function administrator(): User
    {
        $user = $this->user(uniqid('admin-', true).'@example.com');
        $user->assignRole('superadmin');

        return $user;
    }
}
