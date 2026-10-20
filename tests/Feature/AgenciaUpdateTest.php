<?php

namespace Tests\Feature;

use App\Exports\AgenciasExport;
use App\Http\Controllers\AgenciaController;
use App\Models\Agencia;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
            $table->string('zona')->nullable();
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

        $response = app(AgenciaController::class)->update(
            Request::create(route('agencias.update', $agenciaId), 'PUT', [
                'agencia' => '55903',
                'nombre_agencia' => 'Agencia actualizada',
                'zona' => 'Zona Norte',
                'estatus' => 1,
                'aplica_incentivo' => 1,
            ]),
            Agencia::findOrFail($agenciaId),
        );
        $this->assertSame(route('agencias.index'), $response->getTargetUrl());

        $this->assertDatabaseHas('agencias', [
            'id' => $agenciaId,
            'nombre_agencia' => 'Agencia actualizada',
            'zona' => 'Zona Norte',
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
        $this->assertStringContainsString('name="zona"', $vista);

        $listado = file_get_contents(resource_path('views/agencias/index.blade.php'));
        $this->assertStringContainsString('>Zona</th>', $listado);
        $this->assertStringContainsString("{ data: 'zona', name: 'zona'", $listado);
        $this->assertStringContainsString('{ targets: 6, visible: false }', $listado);
    }

    public function test_txt_import_and_mass_update_preserve_zona(): void
    {
        $this->withoutMiddleware();

        $this->post(route('agencias.import'), [
            'file' => UploadedFile::fake()->createWithContent('agencias.txt', "Agencia\tTerminal\tZona\nAG-10\t55010\tNorte\n"),
        ])->assertRedirect(route('agencias.index'))->assertSessionHas('import_result');

        $this->assertDatabaseHas('agencias', ['agencia' => 'AG-10', 'terminal' => '55010', 'zona' => 'Norte']);

        $this->post(route('agencias.mass-update'), [
            'file' => UploadedFile::fake()->createWithContent('actualizar.txt', "Terminal\tZona\n55010\tSur\n"),
        ])->assertRedirect(route('agencias.index'));
        $this->assertNull(session('error'), (string) session('error'));

        $this->assertDatabaseHas('agencias', ['terminal' => '55010', 'zona' => 'Sur']);

        $this->postJson(route('agencias.mass-update-preview'), [
            'file' => UploadedFile::fake()->createWithContent('preview.txt', "Terminal;Zona\n55010;Sur\n"),
        ])->assertOk()->assertJsonPath('encontradas', 1);

        $exportacion = new AgenciasExport;
        $this->assertContains('Zona', $exportacion->headings());
        $this->assertContains('Sur', $exportacion->map(Agencia::firstOrFail()));
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
