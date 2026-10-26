<?php

namespace Tests\Feature;

use App\Http\Controllers\ContabilidadGastoAgenciaController;
use App\Http\Requests\ReporteGastoAgenciaRequest;
use App\Models\CentroDeCosto;
use App\Models\ClasificacionCuenta;
use App\Models\EntradaDiario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ContabilidadGastoAgenciaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('centros_de_costo', function (Blueprint $table): void {
            $table->id();
            $table->string('company_id');
            $table->integer('id_centro_costo');
            $table->string('id_viejo')->nullable();
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });
        Schema::create('entradas_diario', function (Blueprint $table): void {
            $table->id();
            $table->string('company_id');
            $table->string('id_centro_costo');
            $table->string('cuenta');
            $table->date('fecha');
            $table->decimal('debito', 18, 2);
            $table->decimal('credito', 18, 2);
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_23_171204_create_clasificacion_cuentas_table.php'))->up();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('clasificacion_cuentas');
        Schema::dropIfExists('entradas_diario');
        Schema::dropIfExists('centros_de_costo');

        parent::tearDown();
    }

    public function test_hub_exposes_gasto_agencia(): void
    {
        $item = collect(config('module_hubs.contabilidad.items'))->firstWhere('nombre', 'Gasto por agencia');

        $this->assertNotNull($item);
        $this->assertSame('/contabilidad/gasto-agencia', $item['url']);
        $this->assertTrue(Route::has('contabilidad.gasto-agencia'));
        $this->assertTrue(view()->exists('contabilidad.gasto-agencia'));
    }

    public function test_uses_only_active_classified_accounts_and_matches_company_and_cost_center(): void
    {
        CentroDeCosto::query()->create(['company_id' => '168', 'id_centro_costo' => 7, 'id_viejo' => 'A07', 'descripcion' => 'Agencia A']);
        CentroDeCosto::query()->create(['company_id' => '169', 'id_centro_costo' => 7, 'id_viejo' => 'B07', 'descripcion' => 'Agencia B']);
        ClasificacionCuenta::query()->create(['cuenta' => '600110002', 'activo' => false]);
        $this->movimiento('168', '7', '600110001', 100, 20);
        $this->movimiento('169', '7', '600110001', 40, 0);
        $this->movimiento('168', '7', '600110002', 300, 0);
        $this->movimiento('168', '7', '999999', 500, 0);
        $this->movimiento('168', '8', '600110001', 700, 0);

        $request = ReporteGastoAgenciaRequest::create('/contabilidad/gasto-agencia', 'GET', [
            'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-31',
        ]);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->setValidator(Validator::make($request->all(), $request->rules()));
        $view = app(ContabilidadGastoAgenciaController::class)->index($request);
        $montos = $view->getData()['montos'];

        $this->assertSame('contabilidad.gasto-agencia', $view->name());
        $this->assertCount(2, $view->getData()['agencias']);
        $this->assertEquals(80, $montos->get('168|7')->gasto);
        $this->assertEquals(40, $montos->get('169|7')->gasto);
    }

    private function movimiento(string $empresa, string $centroCosto, string $cuenta, int $debito, int $credito): void
    {
        EntradaDiario::query()->create([
            'company_id' => $empresa,
            'id_centro_costo' => $centroCosto,
            'cuenta' => $cuenta,
            'fecha' => '2026-10-23',
            'debito' => $debito,
            'credito' => $credito,
        ]);
    }
}
