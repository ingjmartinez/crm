<?php

namespace Tests\Feature;

use App\Http\Middleware\ExpireInactiveSession;
use App\Http\Middleware\ForcePasswordChange;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoordinadorOperadorEmployeeFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            Authenticate::class,
            ForcePasswordChange::class,
            ExpireInactiveSession::class,
        ]);

        Schema::dropIfExists('coordinador_operador');
        Schema::dropIfExists('empleados');

        Schema::create('empleados', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('companyid');
            $table->unsignedInteger('empleadoid');
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('depto');
            $table->string('cedula')->nullable();
            $table->string('email')->nullable();
            $table->string('tel1')->nullable();
            $table->date('fechasalida')->nullable();
        });

        Schema::create('coordinador_operador', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 100);
            $table->string('apellido', 100);
            $table->string('correo', 150)->nullable();
            $table->string('cedula', 11)->unique();
            $table->string('telefono', 10)->nullable();
            $table->string('puesto');
            $table->timestamps();
        });
    }

    public function test_employee_search_only_returns_active_employees_from_selected_company_and_department(): void
    {
        $activeEmployeeId = $this->insertEmployee();
        $this->insertEmployee([
            'empleadoid' => 1002,
            'cedula' => '00111111112',
            'fechasalida' => '2026-07-01',
        ]);
        $this->insertEmployee([
            'empleadoid' => 1003,
            'cedula' => '00111111113',
            'depto' => 'Finanzas',
        ]);
        $this->insertEmployee([
            'companyid' => 169,
            'empleadoid' => 1004,
            'cedula' => '00111111114',
        ]);

        DB::table('coordinador_operador')->insert([
            'nombre' => 'Ana',
            'apellido' => 'Pérez',
            'cedula' => '00111111111',
            'puesto' => 'coordinador',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson(route('coordinador-operador.empleados', [
            'empresa' => 'Consorcio Joselito',
            'departamento' => 'Operaciones',
            'buscar' => 'Ana Pérez',
        ]));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeEmployeeId)
            ->assertJsonPath('data.0.nombre', 'Ana Pérez')
            ->assertJsonPath('data.0.cedula', '00111111111')
            ->assertJsonPath('data.0.correo', 'ana@example.com')
            ->assertJsonPath('data.0.coordinador.id', 1);
    }

    public function test_coordinator_is_created_from_the_selected_employee_data(): void
    {
        $employeeId = $this->insertEmployee();

        $response = $this->post(route('coordinador-operador.store'), [
            'empresa' => 'Consorcio Joselito',
            'departamento' => 'Operaciones',
            'empleado_id' => $employeeId,
            'nombre' => 'Nombre manipulado',
            'cedula' => '99999999999',
        ]);

        $response
            ->assertRedirect(route('coordinador-operador.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('coordinador_operador', [
            'nombre' => 'Ana',
            'apellido' => 'Pérez',
            'correo' => 'ana@example.com',
            'cedula' => '00111111111',
            'telefono' => '8095551212',
            'puesto' => 'coordinador',
        ]);
    }

    public function test_inactive_employee_cannot_be_registered_as_coordinator(): void
    {
        $employeeId = $this->insertEmployee(['fechasalida' => '2026-07-01']);

        $response = $this->from(route('coordinador-operador.index'))
            ->post(route('coordinador-operador.store'), [
                'empresa' => 'Consorcio Joselito',
                'departamento' => 'Operaciones',
                'empleado_id' => $employeeId,
            ]);

        $response
            ->assertRedirect(route('coordinador-operador.index'))
            ->assertSessionHasErrors('empleado_id');

        $this->assertDatabaseCount('coordinador_operador', 0);
    }

    public function test_existing_coordinator_can_be_updated_from_employee_selection(): void
    {
        $employeeId = $this->insertEmployee([
            'nombres' => 'Ana María',
            'email' => 'nuevo@example.com',
        ]);

        $coordinadorId = DB::table('coordinador_operador')->insertGetId([
            'nombre' => 'Ana',
            'apellido' => 'Pérez',
            'correo' => 'anterior@example.com',
            'cedula' => '00111111111',
            'telefono' => '8090000000',
            'puesto' => 'coordinador',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->put(route('coordinador-operador.update', $coordinadorId), [
            'empresa' => 'Consorcio Joselito',
            'departamento' => 'Operaciones',
            'empleado_id' => $employeeId,
        ]);

        $response
            ->assertRedirect(route('coordinador-operador.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('coordinador_operador', [
            'id' => $coordinadorId,
            'nombre' => 'Ana María',
            'correo' => 'nuevo@example.com',
            'cedula' => '00111111111',
        ]);
        $this->assertDatabaseCount('coordinador_operador', 1);
    }

    public function test_manual_coordinator_editing_is_not_available(): void
    {
        $coordinadorId = DB::table('coordinador_operador')->insertGetId([
            'nombre' => 'Ana',
            'apellido' => 'Pérez',
            'correo' => 'ana@example.com',
            'cedula' => '00111111111',
            'telefono' => '8095551212',
            'puesto' => 'coordinador',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get("/coordinador-operador/{$coordinadorId}/edit")
            ->assertNotFound();

        $response = $this->from(route('coordinador-operador.index'))
            ->put(route('coordinador-operador.update', $coordinadorId), [
                'nombre' => 'Nombre manual',
                'apellido' => 'Manipulado',
                'cedula' => '00111111111',
            ]);

        $response
            ->assertRedirect(route('coordinador-operador.index'))
            ->assertSessionHasErrors(['empresa', 'departamento', 'empleado_id']);

        $this->assertDatabaseHas('coordinador_operador', [
            'id' => $coordinadorId,
            'nombre' => 'Ana',
            'apellido' => 'Pérez',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function insertEmployee(array $overrides = []): int
    {
        return DB::table('empleados')->insertGetId(array_merge([
            'companyid' => 168,
            'empleadoid' => 1001,
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
            'depto' => 'Operaciones',
            'cedula' => '00111111111',
            'email' => 'ana@example.com',
            'tel1' => '809-555-1212',
            'fechasalida' => null,
        ], $overrides));
    }
}
