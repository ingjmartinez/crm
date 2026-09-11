<?php

namespace Tests\Feature;

use App\Http\Controllers\IncentivoConfiguracionController;
use App\Models\IncentivoAdministrativo;
use App\Models\IncentivoAdministrativoAuditoria;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class IncentivoAdministrativoAuditoriaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('must_change_password')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->unique(['name', 'guard_name']);
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->unique(['name', 'guard_name']);
            $table->timestamps();
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
        Schema::create('incentivo_administrativos', function (Blueprint $table): void {
            $table->id();
            $table->string('grupo');
            $table->string('nombre');
            $table->string('cedula')->nullable();
            $table->string('empresa');
            $table->decimal('pct_total', 10, 2);
            $table->timestamps();
        });
        Schema::dropIfExists('incentivo_administrativo_auditorias');
        Schema::create('incentivo_administrativo_auditorias', function (Blueprint $table): void {
            $table->id();
            $table->string('accion');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('usuario_nombre');
            $table->string('usuario_email');
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->string('empleado_nombre');
            $table->string('cedula')->nullable();
            $table->string('empresa');
            $table->json('datos');
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_it_records_who_registered_and_deleted_an_administrative_employee(): void
    {
        $user = User::factory()->create(['name' => 'Super Auditor']);
        $registro = IncentivoAdministrativo::query()->create([
            'grupo' => '6. Seguridad',
            'nombre' => 'Empleado Auditado',
            'cedula' => '00199999999',
            'empresa' => 'Consorcio Joselito',
            'pct_total' => 1500,
        ]);
        $request = Request::create('/incentivos/incentivo-administrativo', 'POST', server: [
            'REMOTE_ADDR' => '192.0.2.10',
            'HTTP_USER_AGENT' => 'Navegador de prueba',
        ]);
        $request->setUserResolver(fn (): User => $user);
        $controller = app(IncentivoConfiguracionController::class);
        $method = new ReflectionMethod($controller, 'registrarAuditoriaAdministrativa');

        $method->invoke($controller, $request, 'registrado', $registro);
        $controller->incentivoAdministrativoDestroy($request, $registro);

        $this->assertDatabaseHas('incentivo_administrativo_auditorias', [
            'accion' => 'registrado',
            'usuario_id' => $user->id,
            'usuario_nombre' => 'Super Auditor',
            'empleado_nombre' => 'Empleado Auditado',
            'ip' => '192.0.2.10',
        ]);
        $this->assertDatabaseHas('incentivo_administrativo_auditorias', [
            'accion' => 'eliminado',
            'usuario_id' => $user->id,
            'cedula' => '00199999999',
        ]);
        $this->assertSame(2, IncentivoAdministrativoAuditoria::query()->count());
        $this->assertDatabaseMissing('incentivo_administrativos', ['id' => $registro->id]);
    }

    public function test_only_superadmin_can_open_the_audit_report(): void
    {
        $superAdmin = User::factory()->create();
        $regularUser = User::factory()->create();
        $superAdmin->assignRole(Role::findOrCreate('superadmin'));

        $this->actingAs($regularUser)
            ->get(route('incentivos.incentivo-administrativo.auditoria'))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get(route('incentivos.incentivo-administrativo.auditoria'))
            ->assertOk()
            ->assertSee('Historial de Incentivo Administrativo');
    }
}
