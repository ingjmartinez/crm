<?php

namespace Tests\Feature;

use App\Http\Controllers\AgenciaController;
use App\Http\Middleware\ExpireInactiveSession;
use App\Http\Middleware\ForcePasswordChange;
use App\Models\Agencia;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgenciaUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('agencia');
            $table->string('nombre_agencia')->nullable();
            $table->string('terminal')->nullable();
            $table->string('horario_am')->nullable();
            $table->string('horario_pm')->nullable();
            $table->string('sistema')->nullable();
            $table->string('empresa')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('ruta')->nullable();
            $table->string('operador')->nullable();
            $table->string('coordinador')->nullable();
            $table->unsignedTinyInteger('estatus')->default(1);
            $table->boolean('aplica_incentivo')->default(false);
            $table->timestamps();
        });
        Schema::create('coordinador_operador', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('apellido')->nullable();
            $table->string('puesto');
            $table->timestamps();
        });
        Schema::create('operador_ruta', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('apellido')->nullable();
            $table->string('puesto');
            $table->timestamps();
        });
        Schema::create('coordinador_operador_agencia', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('coordinador_operador_id');
            $table->unsignedBigInteger('agencia_id');
            $table->timestamps();
        });
        Schema::create('operador_ruta_agencia', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('operador_ruta_id');
            $table->unsignedBigInteger('agencia_id');
            $table->timestamps();
        });
    }

    public function test_agency_can_be_updated_without_coordinator_or_operator(): void
    {
        $agenciaId = DB::table('agencias')->insertGetId([
            'agencia' => '55903',
            'nombre_agencia' => 'Agencia de prueba',
            'operador' => null,
            'coordinador' => null,
            'estatus' => 1,
            'aplica_incentivo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withoutMiddleware([
            Authenticate::class,
            ForcePasswordChange::class,
            ExpireInactiveSession::class,
        ])
            ->put(route('agencias.update', $agenciaId), [
                'agencia' => '55903',
                'nombre_agencia' => 'Agencia actualizada',
                'estatus' => 1,
                'aplica_incentivo' => 1,
            ])
            ->assertRedirect(route('agencias.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('agencias', [
            'id' => $agenciaId,
            'nombre_agencia' => 'Agencia actualizada',
            'operador' => null,
            'coordinador' => null,
        ]);
    }

    public function test_edit_form_hides_operator_and_links_coordinator_management(): void
    {
        $vista = file_get_contents(resource_path('views/agencias/edit.blade.php'));

        $this->assertIsString($vista);
        $this->assertStringContainsString('Sin coordinador asignado', $vista);
        $this->assertStringContainsString("route('coordinador-operador.index')", $vista);
        $this->assertStringNotContainsString('name="operador"', $vista);
        $this->assertStringNotContainsString('name="coordinador"', $vista);
    }

    public function test_edit_page_receives_the_assigned_coordinator_name(): void
    {
        $agenciaId = DB::table('agencias')->insertGetId([
            'agencia' => '55903',
            'estatus' => 1,
            'aplica_incentivo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $coordinadorId = DB::table('coordinador_operador')->insertGetId([
            'nombre' => 'Ana',
            'apellido' => 'Pérez',
            'puesto' => 'coordinador',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('coordinador_operador_agencia')->insert([
            'coordinador_operador_id' => $coordinadorId,
            'agencia_id' => $agenciaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $vista = app(AgenciaController::class)->edit(Agencia::query()->findOrFail($agenciaId));

        $this->assertSame(['Ana Pérez'], $vista->getData()['coordinadoresAsignados']->all());
    }
}
