<?php

namespace Tests\Feature;

use App\Http\Middleware\ExpireInactiveSession;
use App\Http\Middleware\ForcePasswordChange;
use App\Models\User;
use App\Models\UserFavorito;
use App\Services\FavoritoCatalogoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserFavoritoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ForcePasswordChange::class, ExpireInactiveSession::class]);

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('must_change_password')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('user_favoritos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('favorito_key');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'favorito_key']);
        });

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

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('user_favoritos');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    public function test_usuario_puede_agregar_y_quitar_un_reporte_favorito(): void
    {
        $usuario = $this->usuario('usuario@example.com');
        $key = 'contabilidad:contabilidad/reportes/estado-resultado';

        $this->actingAs($usuario)
            ->postJson(route('favoritos.toggle'), ['favorito_key' => $key])
            ->assertOk()
            ->assertJsonPath('activo', true)
            ->assertJsonPath('favorito.nombre', 'Estado de Resultado');

        $this->assertDatabaseHas('user_favoritos', ['user_id' => $usuario->id, 'favorito_key' => $key]);

        $this->actingAs($usuario)
            ->postJson(route('favoritos.toggle'), ['favorito_key' => $key])
            ->assertOk()
            ->assertJsonPath('activo', false);

        $this->assertDatabaseMissing('user_favoritos', ['user_id' => $usuario->id, 'favorito_key' => $key]);
    }

    public function test_favoritos_son_independientes_por_usuario(): void
    {
        $usuarioUno = $this->usuario('uno@example.com');
        $usuarioDos = $this->usuario('dos@example.com');
        $key = 'contabilidad:contabilidad/reportes/estado-resultado';
        UserFavorito::query()->create(['user_id' => $usuarioUno->id, 'favorito_key' => $key, 'orden' => 1]);

        $service = app(FavoritoCatalogoService::class);

        $this->assertSame([$key], $service->favoritos($usuarioUno)->pluck('key')->all());
        $this->assertSame([], $service->favoritos($usuarioDos)->pluck('key')->all());
    }

    public function test_no_permite_guardar_una_pagina_que_no_esta_en_el_catalogo(): void
    {
        $usuario = $this->usuario('usuario@example.com');

        $this->actingAs($usuario)
            ->postJson(route('favoritos.toggle'), ['favorito_key' => 'reporte:inventado'])
            ->assertNotFound();
    }

    public function test_usuario_puede_agregar_un_reporte_del_catalogo_general(): void
    {
        $usuario = $this->usuario('reportes@example.com');
        $key = 'reportes:reportes-compensacion';

        $this->actingAs($usuario)
            ->postJson(route('favoritos.toggle'), ['favorito_key' => $key])
            ->assertOk()
            ->assertJsonPath('activo', true)
            ->assertJsonPath('favorito.nombre', 'Compensacion')
            ->assertJsonPath('favorito.modulo', 'Reportes');

        $this->assertDatabaseHas('user_favoritos', [
            'user_id' => $usuario->id,
            'favorito_key' => $key,
        ]);
    }

    public function test_usuario_de_recursos_humanos_puede_agregar_un_reporte_favorito(): void
    {
        $usuario = $this->usuario('recursos-humanos@example.com');
        Role::query()->create(['name' => 'rh', 'guard_name' => 'web']);
        $usuario->assignRole('rh');
        $key = 'recursos-humanos:recursos-humanos/novedades-horario';

        $this->actingAs($usuario)
            ->get(route('recursos-humanos.index'))
            ->assertOk()
            ->assertSee('data-favorito-key="'.$key.'"', false);

        $this->actingAs($usuario)
            ->postJson(route('favoritos.toggle'), ['favorito_key' => $key])
            ->assertOk()
            ->assertJsonPath('activo', true)
            ->assertJsonPath('favorito.nombre', 'Novedades de Horario')
            ->assertJsonPath('favorito.modulo', 'Recursos Humanos');

        $this->assertDatabaseHas('user_favoritos', [
            'user_id' => $usuario->id,
            'favorito_key' => $key,
        ]);
    }

    public function test_usuario_sin_acceso_no_puede_agregar_un_reporte_de_recursos_humanos(): void
    {
        $usuario = $this->usuario('sin-recursos-humanos@example.com');

        $this->actingAs($usuario)
            ->postJson(route('favoritos.toggle'), [
                'favorito_key' => 'recursos-humanos:recursos-humanos/novedades-horario',
            ])
            ->assertNotFound();
    }

    public function test_hub_muestra_estrellas_y_barra_superior_con_favoritos(): void
    {
        $layout = file_get_contents(resource_path('views/app.blade.php'));
        $hub = file_get_contents(resource_path('views/module-hub/index.blade.php'));
        $reportes = file_get_contents(resource_path('views/reportes/index.blade.php'));
        $recursosHumanos = file_get_contents(resource_path('views/recursos_humanos/index.blade.php'));

        $this->assertStringContainsString('Mis favoritos', $layout);
        $this->assertStringContainsString('const FAVORITES_TOGGLE_URL', $layout);
        $this->assertStringContainsString('renderTopbarFavorites', $layout);
        $this->assertStringContainsString('btn-app-favorito', $hub);
        $this->assertStringContainsString('data-favorito-variant="icon"', $hub);
        $this->assertStringContainsString('btn-app-favorito', $reportes);
        $this->assertStringContainsString("data-favorito-key=\"{{ \$reporte['favorito_key'] }}\"", $reportes);
        $this->assertStringContainsString('btn-app-favorito', $recursosHumanos);
        $this->assertStringContainsString("data-favorito-key=\"{{ \$modulo['favorito_key'] }}\"", $recursosHumanos);

        $this->assertTrue(Route::has('favoritos.toggle'));
    }

    private function usuario(string $email): User
    {
        return User::query()->create([
            'name' => 'Usuario Favoritos',
            'email' => $email,
            'password' => Hash::make('password'),
            'must_change_password' => false,
        ]);
    }
}
