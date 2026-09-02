<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgenciasCerradasDomingosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('agencia')->nullable();
            $table->string('nombre_agencia')->nullable();
            $table->string('terminal')->nullable();
            $table->string('sistema')->nullable();
            $table->string('empresa')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('ruta')->nullable();
            $table->string('coordinador')->nullable();
            $table->unsignedTinyInteger('estatus')->default(1);
            $table->timestamps();
        });

        Schema::create('vt_usuarios_bet', function (Blueprint $table): void {
            $table->id();
            $table->string('agencia_id');
            $table->decimal('monto', 12, 2)->default(0);
            $table->date('fecha');
        });

        Schema::create('asistencias_bet', function (Blueprint $table): void {
            $table->id();
            $table->string('agencia_id');
            $table->date('fecha');
            $table->dateTime('primer_login')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('asistencias_bet');
        Schema::dropIfExists('vt_usuarios_bet');
        Schema::dropIfExists('agencias');

        parent::tearDown();
    }

    public function test_user_can_open_report_screen_without_running_a_query(): void
    {
        $this->get(route('recursos-humanos.agencias-cerradas-domingos.index'))
            ->assertOk()
            ->assertSee('Agencias Cerradas Domingos')
            ->assertSee('Domingo a consultar')
            ->assertDontSee('Agencias que no abrieron');
    }

    public function test_report_only_lists_active_lotobet_agencies_without_sales_and_punches(): void
    {
        $this->seedReportScenario();

        $this->get(route('recursos-humanos.agencias-cerradas-domingos.index', [
            'consultar' => 1,
            'fecha' => '2026-08-02',
        ]))
            ->assertOk()
            ->assertSee('Agencia cerrada')
            ->assertDontSee('Agencia con venta')
            ->assertDontSee('Agencia con ponche')
            ->assertDontSee('Agencia inactiva')
            ->assertDontSee('Agencia Lotonet')
            ->assertSee('Grupo Joselito: 1 cerradas de 3 activas')
            ->assertSee('Negosur: 0 cerradas de 1 activas');
    }

    public function test_report_does_not_create_false_closures_when_a_source_is_missing(): void
    {
        $this->insertAgency('001', 'Agencia sin validar', 'Grupo Joselito');
        DB::table('vt_usuarios_bet')->insert([
            'agencia_id' => '001',
            'monto' => 100,
            'fecha' => '2026-08-02',
        ]);

        $this->get(route('recursos-humanos.agencias-cerradas-domingos.index', [
            'consultar' => 1,
            'fecha' => '2026-08-02',
        ]))
            ->assertOk()
            ->assertSee('Datos incompletos')
            ->assertSee('faltan datos de ponches')
            ->assertDontSee('Agencias que no abrieron');
    }

    public function test_report_rejects_a_date_that_is_not_sunday(): void
    {
        $this->from(route('recursos-humanos.agencias-cerradas-domingos.index'))
            ->get(route('recursos-humanos.agencias-cerradas-domingos.index', [
                'consultar' => 1,
                'fecha' => '2026-08-03',
            ]))
            ->assertRedirect(route('recursos-humanos.agencias-cerradas-domingos.index'))
            ->assertSessionHasErrors('fecha');
    }

    public function test_user_can_download_the_excel_for_a_valid_sunday(): void
    {
        $this->seedReportScenario();

        $this->get(route('recursos-humanos.agencias-cerradas-domingos.exportar', [
            'fecha' => '2026-08-02',
        ]))
            ->assertOk()
            ->assertDownload('agencias-cerradas-domingo-20260802.xlsx');
    }

    private function seedReportScenario(): void
    {
        $this->insertAgency('001', 'Agencia cerrada', 'Grupo Joselito');
        $this->insertAgency('002', 'Agencia con venta', 'Grupo Joselito');
        $this->insertAgency('003', 'Agencia con ponche', 'Grupo Joselito');
        $this->insertAgency('004', 'Agencia inactiva', 'Grupo Joselito', estatus: 0);
        $this->insertAgency('005', 'Agencia Lotonet', 'Negosur', sistema: 'Lotonet');
        $this->insertAgency('006', 'Agencia Negosur abierta', 'Negosur');

        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '0002', 'monto' => 500, 'fecha' => '2026-08-02'],
            ['agencia_id' => '006', 'monto' => 250, 'fecha' => '2026-08-02'],
        ]);

        DB::table('asistencias_bet')->insert([
            ['agencia_id' => '03', 'fecha' => '2026-08-02', 'primer_login' => '2026-08-02 08:00:00'],
            ['agencia_id' => '0006', 'fecha' => '2026-08-02', 'primer_login' => '2026-08-02 08:15:00'],
        ]);
    }

    private function insertAgency(
        string $terminal,
        string $nombre,
        string $empresa,
        int $estatus = 1,
        string $sistema = 'Lotobet'
    ): void {
        DB::table('agencias')->insert([
            'agencia' => $nombre,
            'nombre_agencia' => $nombre,
            'terminal' => $terminal,
            'sistema' => $sistema,
            'empresa' => $empresa,
            'ciudad' => 'Santo Domingo',
            'ruta' => 'Ruta 1',
            'coordinador' => 'Coordinador prueba',
            'estatus' => $estatus,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
