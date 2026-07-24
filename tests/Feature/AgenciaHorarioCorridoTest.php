<?php

namespace Tests\Feature;

use App\Http\Controllers\AgenciaController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgenciaHorarioCorridoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('agencia_horarios');
        Schema::dropIfExists('agencias');

        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('agencia')->nullable();
            $table->string('terminal')->nullable();
            $table->string('empresa')->nullable();
            $table->boolean('estatus')->default(true);
            $table->string('horario_am', 35)->nullable();
            $table->string('horario_pm', 35)->nullable();
            $table->timestamps();
        });

        Schema::create('agencia_horarios', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agencia_id');
            $table->unsignedTinyInteger('dia_semana');
            $table->string('horario_am', 35)->nullable();
            $table->string('horario_pm', 35)->nullable();
            $table->timestamps();
            $table->unique(['agencia_id', 'dia_semana']);
        });
    }

    public function test_sunday_can_be_saved_as_a_continuous_schedule(): void
    {
        $agenciaId = DB::table('agencias')->insertGetId([
            'agencia' => 'AG-1',
            'terminal' => '1001',
            'empresa' => 'Joselito',
            'estatus' => 1,
        ]);

        $request = Request::create('/agencias-actualizar-horario', 'POST', [
            'scope' => 'agencia',
            'agencia_busqueda' => (string) $agenciaId,
            'estatus_filter' => 'todos',
            'empresa_filter' => 'todas',
            'reglas' => [[
                'dia_desde' => 7,
                'dia_hasta' => 7,
                'tipo_horario' => 'corrido',
                'horario_am' => '8:00 AM / 8:00 PM',
                'horario_pm' => '2:00 PM / 9:30 PM',
            ]],
        ]);

        $response = app(AgenciaController::class)->actualizarHorarioMasivo($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('agencia_horarios', [
            'agencia_id' => $agenciaId,
            'dia_semana' => 7,
            'horario_am' => '8:00 AM / 8:00 PM',
            'horario_pm' => null,
        ]);
        $this->assertDatabaseHas('agencias', [
            'id' => $agenciaId,
            'horario_am' => '8:00 AM / 8:00 PM',
            'horario_pm' => null,
        ]);
    }

    public function test_modal_defaults_sunday_to_continuous_eight_to_eight(): void
    {
        $source = file_get_contents(resource_path('views/agencias/index.blade.php'));

        $this->assertStringContainsString('Horario corrido', $source);
        $this->assertStringContainsString("tipo_horario: 'corrido'", $source);
        $this->assertStringContainsString("horario_am: '8:00 AM / 8:00 PM'", $source);
    }
}
