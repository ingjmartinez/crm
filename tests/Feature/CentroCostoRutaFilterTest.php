<?php

namespace Tests\Feature;

use App\Models\CentroDeCosto;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CentroCostoRutaFilterTest extends TestCase
{
    public function test_route_filter_is_displayed_next_to_status_and_filters_route_column(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->andReturnFalse();
        $user->shouldReceive('hasRole')->andReturnFalse();
        $user->setRelation('viewPermissions', collect());
        $this->actingAs($user);
        Gate::before(fn (): bool => false);

        $response = $this->withoutMiddleware()->get(route('contabilidad.centro-costo'));
        Schema::dropIfExists('permissions');

        $response->assertOk()
            ->assertSee('id="filtroRutaCentroCosto"', false)
            ->assertSee('centrosCostoTable.column(6).search(busquedaRuta, true, false);', false)
            ->assertSee('centrosCostoTable.column(5).search(busquedaEstado, true, false);', false)
            ->assertSee('<td>${celdaCuentaCentro(item)}</td>', false)
            ->assertSee('agruparCentrosPorRuta()', false)
            ->assertSee('Ocultar todos', false)
            ->assertSee('La cuenta se aplicara a todos sus centros y terminales.', false)
            ->assertSee('id="buscarCuentaCentro"', false)
            ->assertSee('id="guardarCuentaCentro"', false)
            ->assertSee('id="quitarCuentaCentro"', false)
            ->assertSee('cargarOpcionesRutaCentroCosto(dataFiltrada);', false)
            ->assertSee("String(item.IdGrupo ?? '').trim()", false)
            ->assertSee('Buscar ruta por nombre...')
            ->assertSee('new Choices(filtroRutaCentroCostoEl', false)
            ->assertSee('padding: 9px 16px 9px 24px !important;', false)
            ->assertSee('overflow-x: hidden;', false)
            ->assertSee("filtroRutaCentroCostoChoices.setChoices(opciones, 'value', 'label', true);", false);

        $documento = new \DOMDocument;
        @$documento->loadHTML($response->getContent());
        $xpath = new \DOMXPath($documento);

        $this->assertSame(1, $xpath->query("//*[@id='filtroEstadoCentroCosto']/parent::div/following-sibling::div[1]//*[@id='filtroRutaCentroCosto']")->length);
        $this->assertSame('Cuenta contable de la ruta', $xpath->query("//*[@id='tableCentrosCosto']/thead/tr/th[3]")->item(0)->textContent);
        $this->assertSame('Cuenta Ruta', $xpath->query("//*[@id='tableCentrosCosto']/thead/tr/th[normalize-space()='Ruta Empresa']/following-sibling::th[1]")->item(0)->textContent);
        $this->assertSame('Centro de costo ruta', $xpath->query("//*[@id='tableCentrosCosto']/thead/tr/th[normalize-space()='Cuenta Ruta']/following-sibling::th[1]")->item(0)->textContent);
        $response->assertSee('<td>${escaparHtmlCentro(item.Atrib8 ?? \'\')}</td>', false);
    }

    public function test_route_account_attribute_is_returned_with_empty_fallback(): void
    {
        $this->crearTablaCentros();

        try {
            foreach ([['Atr8' => '00100130011'], ['atr8' => '100130032'], ['Atr8' => null], []] as $index => $atributos) {
                CentroDeCosto::query()->create([
                    'company_id' => '168',
                    'id_centro_costo' => $index + 1,
                    'atributos' => $atributos,
                ]);
            }

            $this->withoutMiddleware()->getJson('/api-centros-costo?empresa=todas')
                ->assertOk()
                ->assertJsonCount(4)
                ->assertJsonPath('0.Atrib8', '00100130011')
                ->assertJsonPath('1.Atrib8', '100130032')
                ->assertJsonPath('2.Atrib8', '')
                ->assertJsonPath('3.Atrib8', '')
                ->assertJsonPath('0.CuentaRuta', '');
        } finally {
            Schema::dropIfExists('centro_costo_cuenta_rutas');
            Schema::dropIfExists('centros_de_costo');
        }
    }

    public function test_route_cost_center_is_reported_separately_from_terminal_and_company(): void
    {
        $this->crearTablaCentros();
        try {
            foreach ([
                ['168', 1101, '68-05 - Gj Ruta 02 - Distrito', 'Grupo: Ruta Distrito 2 Euris', null],
                ['168', 1474, '68-05 - Gj Ruta 02 - Distrito', 'Agencia Distrito', '05367'],
                ['169', 2694, '68-Otra ruta', 'Grupo: Otra empresa', null],
                ['168', 2000, '171-05 - Gj Ruta Bavaro', 'Agencia Bavaro', '05368'],
            ] as [$empresa, $id, $ruta, $descripcion, $terminal]) {
                CentroDeCosto::query()->create([
                    'company_id' => $empresa, 'id_centro_costo' => $id, 'id_grupo' => $ruta,
                    'descripcion' => $descripcion, 'id_viejo' => $terminal,
                ]);
            }
            $registros = collect($this->withoutMiddleware()->getJson('/api-centros-costo?empresa=todas')->assertOk()->json());
            $this->assertSame([1101], $registros->firstWhere('IdCentroCosto', 1474)['CentrosCostoRuta']);
            $this->assertSame('05367', $registros->firstWhere('IdCentroCosto', 1474)['IdViejo']);
            $this->assertSame([2694], $registros->firstWhere('IdCentroCosto', 2694)['CentrosCostoRuta']);
            $this->assertSame([], $registros->firstWhere('IdCentroCosto', 2000)['CentrosCostoRuta']);
        } finally {
            Schema::dropIfExists('centro_costo_cuenta_rutas');
            Schema::dropIfExists('centros_de_costo');
        }
    }

    public function test_centers_without_explicit_associations_do_not_guess_accounts(): void
    {
        $this->crearTablaCentros();
        Http::preventStrayRequests();
        try {
            foreach (['168-Grupo Joselito', '169-Negosur'] as $empresa) {
                CentroDeCosto::query()->create([
                    'company_id' => $empresa, 'id_centro_costo' => 1,
                    'id_grupo' => '05 - GJ Ruta Villa Mella', 'cuenta' => 'OTRA',
                ]);
            }
            CentroDeCosto::query()->create([
                'company_id' => '168', 'id_centro_costo' => 2, 'id_grupo' => 'Ruta sin cuenta',
            ]);

            $response = $this->withoutMiddleware()->getJson('/api-centros-costo?empresa=todas')->assertOk();
            $centros = collect($response->json());

            $this->assertSame('', $centros->firstWhere('CompanyID', '168-Grupo Joselito')['CuentaRuta']);
            $this->assertSame('', $centros->firstWhere('CompanyID', '169-Negosur')['CuentaRuta']);
            $this->assertSame('', $centros->firstWhere('IdCentroCosto', 2)['CuentaRuta']);
        } finally {
            Schema::dropIfExists('centro_costo_cuenta_rutas');
            Schema::dropIfExists('centros_de_costo');
        }
    }

    public function test_centers_load_without_contacting_external_catalog(): void
    {
        $this->crearTablaCentros();
        Http::fake(['*' => Http::response([], 500)]);

        try {
            foreach (['Ruta Villa Mella', 'Ruta Santiago'] as $indice => $ruta) {
                CentroDeCosto::query()->create([
                    'company_id' => '168', 'id_centro_costo' => $indice + 1, 'id_grupo' => $ruta,
                ]);
            }

            $this->withoutMiddleware()->getJson('/api-centros-costo?empresa=todas')
                ->assertOk()->assertJsonCount(2)
                ->assertJsonPath('0.CuentaRuta', '')
                ->assertJsonPath('1.CuentaRuta', '');

            Http::assertNothingSent();
        } finally {
            Schema::dropIfExists('centros_de_costo');
        }
    }

    private function crearTablaCentros(): void
    {
        Schema::create('centro_costo_cuenta_rutas', function (Blueprint $table): void {
            $table->id();
            $table->string('company_id');
            $table->string('ruta_key');
            $table->unsignedBigInteger('cuenta_ruta_id')->nullable();
        });
        Schema::create('centros_de_costo', function (Blueprint $table): void {
            $table->id();
            $table->string('company_id');
            $table->integer('id_centro_costo');
            $table->string('id_grupo')->nullable();
            $table->string('cuenta')->nullable();
            $table->string('descripcion')->nullable();
            $table->string('id_viejo')->nullable();
            $table->boolean('inactivo')->default(false);
            $table->boolean('ocultar')->default(false);
            $table->unsignedBigInteger('cuenta_ruta_id')->nullable();
            $table->timestamps();
            $table->json('atributos')->nullable();
        });
    }
}
