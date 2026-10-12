<?php

namespace Tests\Feature;

use App\Http\Controllers\Bi\DashboardController as BiDashboardController;
use App\Http\Middleware\ExpireInactiveSession;
use App\Http\Middleware\ForcePasswordChange;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Mockery\MockInterface;
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

    public function test_user_with_only_bi_access_reaches_bi_and_other_pages_remain_forbidden(): void
    {
        Permission::findOrCreate('dashboard.bi.view', 'web');
        Permission::findOrCreate('dashboard.tablero_principal.view', 'web');
        Permission::findOrCreate('dashboard.lotobet_ventas.view', 'web');
        $user = $this->user('pantalla@example.com');
        $role = Role::findOrCreate('pantalla', 'web');
        $role->givePermissionTo('dashboard.bi.view');
        $user->assignRole($role);
        $this->assertFalse(app(\App\ViewPermissionCatalog::class)->allowsPath($user, '/bi-tv'));

        $this->partialMock(BiDashboardController::class, function (MockInterface $mock): void {
            $mock->shouldReceive('index')->once()->andReturn(Inertia::render('Bi/Dashboard'));
        });

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/bi');
        $this->get('/bi')->assertOk();
        $this->get('/')->assertRedirect('/bi');
        $this->get('/ventas-lotobet-dashboard')->assertForbidden();

        $this->get('/login')->assertRedirect('/bi');
    }

    public function test_login_uses_allowed_intended_path_and_rejects_forbidden_one(): void
    {
        Permission::findOrCreate('dashboard.bi.view', 'web');
        $user = $this->user('intended@example.com');
        $user->givePermissionTo('dashboard.bi.view');

        $this->withSession(['url.intended' => url('/ventas-lotobet-dashboard')])
            ->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/bi');

        auth()->logout();
        $this->withSession(['url.intended' => url('/bi')])
            ->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/bi');
    }

    public function test_user_with_dashboard_access_still_reaches_root_and_user_without_views_is_logged_out(): void
    {
        Permission::findOrCreate('dashboard.tablero_principal.view', 'web');
        $dashboardUser = $this->user('dashboard@example.com');
        $dashboardUser->givePermissionTo('dashboard.tablero_principal.view');

        $this->post('/login', ['email' => $dashboardUser->email, 'password' => 'password'])->assertRedirect('/');

        auth()->logout();
        $emptyUser = $this->user('empty@example.com');
        $this->post('/login', ['email' => $emptyUser->email, 'password' => 'password'])
            ->assertRedirect('/login')
            ->assertSessionHas('error', 'Tu usuario no tiene vistas asignadas. Contacta al administrador.');
        $this->assertGuest();
    }

    public function test_eight_digit_password_is_accepted_when_editing_a_user(): void
    {
        $target = $this->user('numeric@example.com');
        $this->actingAs($this->administrator());

        $this->put(route('usuarios.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('12345678', $target->fresh()->password));

        $this->put(route('usuarios.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => '1234',
            'password_confirmation' => '1234',
        ])->assertInvalid(['password']);
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
