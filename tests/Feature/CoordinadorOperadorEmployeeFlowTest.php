<?php

namespace Tests\Feature;

use App\Exports\CoordinadorOperadorExport;
use App\Http\Middleware\ExpireInactiveSession;
use App\Http\Middleware\ForcePasswordChange;
use App\Models\CoordinadorOperador;
use App\Services\CoordinadorEmpleadoMatcher;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
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

        Schema::dropIfExists('coordinador_operador_agencia');
        Schema::dropIfExists('coordinador_operador');
        Schema::dropIfExists('empleados');
        Schema::dropIfExists('agencias');

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
            $table->unsignedBigInteger('empleado_id')->nullable()->unique();
            $table->string('nombre', 100);
            $table->string('apellido', 100);
            $table->string('correo', 150)->nullable();
            $table->string('cedula', 11)->nullable()->unique();
            $table->string('telefono', 10)->nullable();
            $table->string('puesto');
            $table->timestamps();
        });

        Schema::create('coordinador_operador_agencia', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('coordinador_operador_id');
            $table->unsignedBigInteger('agencia_id');
            $table->timestamps();
        });

        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('agencia')->nullable();
            $table->string('nombre_agencia')->nullable();
            $table->string('terminal')->nullable();
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
            'empleado_id' => $employeeId,
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
            'empleado_id' => $employeeId,
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

    public function test_employee_can_be_assigned_to_an_unassigned_pool_without_losing_agencies(): void
    {
        $employeeId = $this->insertEmployee();
        $poolId = DB::table('coordinador_operador')->insertGetId([
            'empleado_id' => null,
            'nombre' => 'Pool Este',
            'apellido' => 'Sin coordinador',
            'cedula' => null,
            'puesto' => 'coordinador',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('coordinador_operador_agencia')->insert([
            [
                'coordinador_operador_id' => $poolId,
                'agencia_id' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'coordinador_operador_id' => $poolId,
                'agencia_id' => 11,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->put(route('coordinador-operador.update', $poolId), [
            'empresa' => 'Consorcio Joselito',
            'departamento' => 'Operaciones',
            'empleado_id' => $employeeId,
        ]);

        $response
            ->assertRedirect(route('coordinador-operador.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('coordinador_operador', [
            'id' => $poolId,
            'empleado_id' => $employeeId,
            'nombre' => 'Ana',
            'cedula' => '00111111111',
        ]);
        $this->assertDatabaseCount('coordinador_operador', 1);
        $this->assertDatabaseCount('coordinador_operador_agencia', 2);
        $this->assertDatabaseHas('coordinador_operador_agencia', [
            'coordinador_operador_id' => $poolId,
            'agencia_id' => 10,
        ]);
        $this->assertDatabaseHas('coordinador_operador_agencia', [
            'coordinador_operador_id' => $poolId,
            'agencia_id' => 11,
        ]);
    }

    public function test_employee_assigned_to_another_pool_cannot_be_reassigned_implicitly(): void
    {
        $employeeId = $this->insertEmployee();
        DB::table('coordinador_operador')->insert([
            'empleado_id' => $employeeId,
            'nombre' => 'Ana',
            'apellido' => 'Pérez',
            'cedula' => '00111111111',
            'puesto' => 'coordinador',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $unassignedPoolId = DB::table('coordinador_operador')->insertGetId([
            'empleado_id' => null,
            'nombre' => 'Pool Norte',
            'apellido' => 'Sin coordinador',
            'cedula' => null,
            'puesto' => 'coordinador',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->from(route('coordinador-operador.index'))
            ->put(route('coordinador-operador.update', $unassignedPoolId), [
                'empresa' => 'Consorcio Joselito',
                'departamento' => 'Operaciones',
                'empleado_id' => $employeeId,
            ]);

        $response
            ->assertRedirect(route('coordinador-operador.index'))
            ->assertSessionHasErrors('empleado_id');

        $this->assertDatabaseHas('coordinador_operador', [
            'id' => $unassignedPoolId,
            'empleado_id' => null,
            'nombre' => 'Pool Norte',
            'cedula' => null,
        ]);
        $this->assertDatabaseCount('coordinador_operador', 2);
    }

    public function test_unassigned_pool_shows_the_assign_employee_action(): void
    {
        DB::table('coordinador_operador')->insert([
            'empleado_id' => null,
            'nombre' => 'Pool Este',
            'apellido' => 'Sin coordinador',
            'cedula' => null,
            'puesto' => 'coordinador',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('coordinador-operador.index'))
            ->assertOk()
            ->assertSee('ID empleado')
            ->assertSee('Sin asignar')
            ->assertSee('Asignar empleado');
    }

    public function test_list_starts_with_company_column_and_hides_email_column(): void
    {
        $employeeId = $this->insertEmployee();

        DB::table('coordinador_operador')->insert([
            'empleado_id' => $employeeId,
            'nombre' => 'Ana',
            'apellido' => 'Pérez',
            'correo' => 'ana@example.com',
            'cedula' => '00111111111',
            'puesto' => 'coordinador',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('coordinador-operador.index'));

        $response
            ->assertOk()
            ->assertSeeInOrder(['<th>Empresa</th>', '<th class="text-center" style="width:80px;">ID</th>'], false)
            ->assertSee('<td>Grupo Joselito</td>', false)
            ->assertDontSee('<th>Correo</th>', false)
            ->assertDontSee('<td>ana@example.com</td>', false);
    }

    public function test_employee_picker_uses_resilient_selection_and_visible_confirmation(): void
    {
        $response = $this->get(route('coordinador-operador.index'));

        $response
            ->assertOk()
            ->assertSee('id="empleado_selected_feedback"', false)
            ->assertSee("employeeResults.addEventListener('pointerdown'", false)
            ->assertSee('employeeSelectionHandledByPointer', false)
            ->assertSee('employeeRequestController.abort()', false)
            ->assertSee('Ya asignado a otro pool');
    }

    public function test_coordinator_row_is_highlighted_only_when_its_cedula_is_not_in_employee_master(): void
    {
        $employeeId = $this->insertEmployee();

        DB::table('coordinador_operador')->insert([
            [
                'empleado_id' => null,
                'nombre' => 'Cedula',
                'apellido' => 'Coincidente',
                'cedula' => '001-1111111-1',
                'puesto' => 'coordinador',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'empleado_id' => $employeeId,
                'nombre' => 'Cedula',
                'apellido' => 'Sin Coincidencia',
                'cedula' => '00222222222',
                'puesto' => 'coordinador',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->get(route('coordinador-operador.index'));

        $response
            ->assertOk()
            ->assertSee('data-cedula-en-maestra="1"', false)
            ->assertSee('data-cedula-en-maestra="0"', false)
            ->assertSee('No está en maestra');

        $this->assertSame(1, substr_count($response->getContent(), 'class="coordinador-sin-maestra"'));
    }

    public function test_coordinator_row_is_highlighted_in_light_blue_when_cedula_has_an_exit_date(): void
    {
        $exitedEmployeeId = $this->insertEmployee([
            'fechasalida' => '2026-07-01',
        ]);
        $activeEmployeeId = $this->insertEmployee([
            'empleadoid' => 1002,
            'cedula' => '00111111112',
        ]);

        DB::table('coordinador_operador')->insert([
            [
                'empleado_id' => $exitedEmployeeId,
                'nombre' => 'Empleado',
                'apellido' => 'Con salida',
                'cedula' => '00111111111',
                'puesto' => 'coordinador',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'empleado_id' => $activeEmployeeId,
                'nombre' => 'Empleado',
                'apellido' => 'Activo',
                'cedula' => '00111111112',
                'puesto' => 'coordinador',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->get(route('coordinador-operador.index'));

        $response
            ->assertOk()
            ->assertSee('data-cedula-con-salida="1"', false)
            ->assertSee('data-cedula-con-salida="0"', false)
            ->assertSee('Salida en maestra · actualizar');

        $this->assertSame(1, substr_count($response->getContent(), 'class="coordinador-con-salida"'));
    }

    public function test_pending_coordinator_is_linked_by_cedula_and_displays_employee_code(): void
    {
        $employeeId = $this->insertEmployee([
            'empleadoid' => 8450,
            'cedula' => '001-1111111-1',
        ]);

        $coordinadorId = DB::table('coordinador_operador')->insertGetId([
            'empleado_id' => null,
            'nombre' => 'Ana',
            'apellido' => 'Pérez',
            'cedula' => '00111111111',
            'puesto' => 'coordinador',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $vinculados = app(CoordinadorEmpleadoMatcher::class)->vincularPendientesPorCedula();

        $this->assertSame(1, $vinculados);
        $this->assertDatabaseHas('coordinador_operador', [
            'id' => $coordinadorId,
            'empleado_id' => $employeeId,
        ]);

        $this->get(route('coordinador-operador.index'))
            ->assertOk()
            ->assertSee('8450')
            ->assertDontSee('Sin asignar')
            ->assertSee('Actualizar desde maestra');
    }

    public function test_pending_coordinator_is_not_linked_when_cedula_is_duplicated_in_employee_master(): void
    {
        $this->insertEmployee();
        $this->insertEmployee([
            'empleadoid' => 1002,
        ]);

        $coordinadorId = DB::table('coordinador_operador')->insertGetId([
            'empleado_id' => null,
            'nombre' => 'Cedula',
            'apellido' => 'Duplicada',
            'cedula' => '00111111111',
            'puesto' => 'coordinador',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $vinculados = app(CoordinadorEmpleadoMatcher::class)->vincularPendientesPorCedula();

        $this->assertSame(0, $vinculados);
        $this->assertDatabaseHas('coordinador_operador', [
            'id' => $coordinadorId,
            'empleado_id' => null,
        ]);
    }

    public function test_excel_export_repeats_coordinator_data_for_each_terminal(): void
    {
        $employeeId = $this->insertEmployee();
        $coordinadorId = DB::table('coordinador_operador')->insertGetId([
            'empleado_id' => $employeeId,
            'nombre' => 'Ana',
            'apellido' => 'Pérez',
            'correo' => 'ana@example.com',
            'cedula' => '00111111111',
            'telefono' => '8095551212',
            'puesto' => 'coordinador',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $agenciaIds = collect([
            ['agencia' => 'A-1', 'nombre_agencia' => 'Agencia Uno', 'terminal' => 'T-001'],
            ['agencia' => 'A-2', 'nombre_agencia' => 'Agencia Dos', 'terminal' => 'T-002'],
        ])->map(fn (array $agencia): int => DB::table('agencias')->insertGetId($agencia));

        foreach ($agenciaIds as $agenciaId) {
            DB::table('coordinador_operador_agencia')->insert([
                'coordinador_operador_id' => $coordinadorId,
                'agencia_id' => $agenciaId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $coordinadores = CoordinadorOperador::with(['agencias', 'empleado'])->get();
        $rows = (new CoordinadorOperadorExport($coordinadores))->collection();

        $this->assertCount(2, $rows);
        $this->assertSame('Ana', $rows[0][2]);
        $this->assertSame('Ana', $rows[1][2]);
        $this->assertSame(
            ['T-001', 'T-002'],
            $rows->map(fn (array $row): string => $row[8])->sort()->values()->all()
        );
        $this->assertSame('@', (new CoordinadorOperadorExport($coordinadores))->columnFormats()['I']);

        Excel::fake();
        Carbon::setTestNow('2026-07-30 12:34:56');

        $this->get(route('coordinador-operador.export'))
            ->assertOk();

        Excel::assertDownloaded(
            'coordinadores_terminales_2026-07-30_123456.xlsx',
            fn (CoordinadorOperadorExport $export): bool => $export->collection()->count() === 2
        );
        Carbon::setTestNow();

        $this->get(route('coordinador-operador.index'))
            ->assertOk()
            ->assertSee('Descargar Excel');
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
