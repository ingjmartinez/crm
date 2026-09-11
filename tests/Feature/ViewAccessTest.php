<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureViewAccess;
use App\Http\Middleware\ExpireInactiveSession;
use App\Http\Middleware\ForcePasswordChange;
use App\Models\User;
use App\Services\AccesoVistaService;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ViewAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ExpireInactiveSession::class, ForcePasswordChange::class]);
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (require database_path('migrations/2026_02_23_000000_create_permission_tables.php'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(AccesoVistaService::class)->syncPermissions();
    }

    private function userWithViews(array $views, string $module = 'incentivos'): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate('consulta-'.$user->id, 'web');
        $role->givePermissionTo($module.'.view');
        foreach ($views as $view) {
            $role->givePermissionTo(app(AccesoVistaService::class)->permission($view));
        }
        $user->assignRole($role);

        return $user;
    }

    public function test_hub_and_sidebar_only_show_authorized_pages(): void
    {
        $user = $this->userWithViews(['/incentivos/reporte-pagos']);
        $this->actingAs($user)->get('/incentivos')->assertOk()
            ->assertSee('Reporte de Pagos')->assertDontSee('Procesar Incentivos')
            ->assertDontSee('href="'.url('/contabilidad').'"', false);
        $this->get('/incentivos/reporte-pagos')->assertOk();
        $this->get('/incentivos/gestion')->assertForbidden();
        $this->get('/contabilidad')->assertForbidden();
    }

    public function test_denies_data_exports_and_writes_for_other_views(): void
    {
        $user = $this->userWithViews(['/incentivos/reporte-pagos']);
        $this->actingAs($user)->getJson('/incentivos/reporte-nuevo-incentivo-v5')->assertForbidden();
        $this->get('/incentivos/rendimiento-coordinador/1/pdf')->assertForbidden();
        $this->postJson('/incentivos/reporte-nuevo-incentivo-v5/faltantes', [])->assertForbidden();
        $this->postJson('/incentivos/save', [])->assertForbidden();
        $this->getJson('/inicio/ventas-data')->assertForbidden();
    }

    public function test_module_permission_alone_does_not_grant_all_views(): void
    {
        $user = $this->userWithViews([]);
        $this->actingAs($user)->get('/incentivos')->assertForbidden();
        $this->get('/incentivos/reporte-pagos')->assertForbidden();
        $this->get('/')->assertOk()->assertSee('Selecciona uno de tus módulos');
    }

    public function test_view_requires_module_and_roles_are_additive(): void
    {
        $user = $this->userWithViews(['/incentivos/reporte-pagos']);
        $role = $user->roles->first();
        $role->revokePermissionTo('incentivos.view');
        $this->actingAs($user->fresh())->get('/incentivos/reporte-pagos')->assertForbidden();
        $moduleRole = Role::findOrCreate('entrada', 'web');
        $moduleRole->givePermissionTo('incentivos.view');
        $user->assignRole($moduleRole);
        $this->actingAs($user->fresh())->get('/incentivos/reporte-pagos')->assertOk();
    }

    public function test_superadmin_can_open_hubs_without_individual_permissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('superadmin', 'web'));
        $this->actingAs($user)->get('/incentivos')->assertOk()->assertSee('Procesar Incentivos');
        $this->get('/roles/create')->assertOk()->assertSee('Módulos y vistas disponibles');
    }

    public function test_role_form_saves_and_revokes_selected_views(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('superadmin', 'web'));
        $this->actingAs($admin)->post('/roles', [
            'name' => 'Solo pagos',
            'permissions' => ['incentivos.view', 'vistas.incentivos.reporte_pagos'],
        ])->assertRedirect();
        $role = Role::findByName('Solo pagos');
        $this->assertTrue($role->hasPermissionTo('vistas.incentivos.reporte_pagos'));
        $this->get('/roles/'.$role->id.'/edit')->assertOk()->assertSee('Buscar módulo o vista');
        $this->put('/roles/'.$role->id, ['name' => 'Solo pagos', '_access_form' => 1])->assertRedirect();
        $this->assertCount(0, $role->fresh()->permissions);
        $this->post('/roles', ['name' => 'Incorrecto', 'permissions' => ['inventado']])->assertSessionHasErrors('permissions.0');
    }

    public function test_restricted_user_cannot_edit_roles(): void
    {
        $user = $this->userWithViews(['/incentivos/reporte-pagos']);
        $this->actingAs($user)->get('/roles/create')->assertForbidden();
        $this->post('/roles', ['name' => 'No permitido'])->assertForbidden();
    }

    public function test_every_protected_route_has_an_access_mapping(): void
    {
        $access = app(AccesoVistaService::class);
        $hubs = array_column($access->modules(), 'url');
        $missing = [];
        foreach (Route::getRoutes() as $route) {
            if (! in_array(EnsureViewAccess::class, $route->middleware(), true)) {
                continue;
            }
            $path = '/'.ltrim($route->uri(), '/');
            if (in_array($path, $hubs, true) || in_array($path, ['/favoritos/toggle', '/accesos/{module}'], true) || str_starts_with($path, '/procesos/')) {
                continue;
            }
            if ($access->viewsForPath($path) === []) {
                $missing[] = $path;
            }
        }
        $this->assertSame([], array_values(array_unique($missing)), 'Rutas sin acceso: '.implode(', ', $missing));
    }

    public function test_unknown_route_is_denied_by_default(): void
    {
        Route::get('/pantalla-sin-catalogo', fn () => 'privado')->middleware(['web', 'auth', EnsureViewAccess::class]);
        $this->actingAs($this->userWithViews(['/incentivos/reporte-pagos']))->get('/pantalla-sin-catalogo')->assertForbidden();
    }
}
