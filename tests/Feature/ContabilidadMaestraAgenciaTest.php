<?php

namespace Tests\Feature;

use App\Http\Controllers\ContabilidadMaestraAgenciaController;
use App\Models\CentroDeCosto;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContabilidadMaestraAgenciaTest extends TestCase
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
            $table->string('id_grupo')->nullable();
            $table->string('id_sub_grupo')->nullable();
            $table->string('id_division')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('centros_de_costo');

        parent::tearDown();
    }

    public function test_hub_exposes_maestra_de_agencia(): void
    {
        $item = collect(config('module_hubs.contabilidad.items'))->firstWhere('nombre', 'Maestra de agencia');

        $this->assertNotNull($item);
        $this->assertSame('/contabilidad/maestra-agencia', $item['url']);
        $this->assertTrue(Route::has('contabilidad.maestra-agencia'));
        $this->assertTrue(view()->exists('contabilidad.maestra-agencia'));
    }

    public function test_maestra_lists_source_fields_and_filters_by_old_id(): void
    {
        CentroDeCosto::query()->create([
            'company_id' => '169',
            'id_centro_costo' => 101,
            'id_viejo' => '074',
            'descripcion' => 'Barahona-62',
            'id_grupo' => '2-Barahona',
            'id_sub_grupo' => '8-Adelis Feliz',
            'id_division' => 'Sur',
        ]);
        CentroDeCosto::query()->create(['company_id' => '168', 'id_centro_costo' => 102, 'descripcion' => 'Otra terminal']);

        $view = app(ContabilidadMaestraAgenciaController::class)->index(Request::create('/contabilidad/maestra-agencia', 'GET', ['buscar' => '074']));
        $centrosDeCosto = $view->getData()['centrosDeCosto'];

        $this->assertSame('contabilidad.maestra-agencia', $view->name());
        $this->assertSame(1, $centrosDeCosto->total());
        $this->assertSame('169', $centrosDeCosto->first()->company_id);
        $this->assertSame('Barahona-62', $centrosDeCosto->first()->descripcion);
        $this->assertSame('2-Barahona', $centrosDeCosto->first()->id_grupo);
        $this->assertSame('8-Adelis Feliz', $centrosDeCosto->first()->id_sub_grupo);
        $this->assertSame('Sur', $centrosDeCosto->first()->id_division);
    }

    public function test_maestra_paginates_centros_de_costo(): void
    {
        for ($numero = 1; $numero <= 51; $numero++) {
            CentroDeCosto::query()->create(['company_id' => '169', 'id_centro_costo' => $numero]);
        }

        $view = app(ContabilidadMaestraAgenciaController::class)->index(Request::create('/contabilidad/maestra-agencia'));

        $this->assertSame(51, $view->getData()['centrosDeCosto']->total());
        $this->assertCount(50, $view->getData()['centrosDeCosto']);
    }
}
