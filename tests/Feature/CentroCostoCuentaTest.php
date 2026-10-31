<?php

namespace Tests\Feature;

use App\Models\CentroCostoCuentaRuta;
use App\Models\CentroDeCosto;
use App\Models\CuentaContable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CentroCostoCuentaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \Illuminate\Auth\Middleware\Authenticate::class,
            \App\Http\Middleware\ForcePasswordChange::class,
            \App\Http\Middleware\EnsureViewPermission::class,
        ]);
        Http::preventStrayRequests();
        (require database_path('migrations/2026_02_23_120000_create_cuentas_contables_table.php'))->up();
        Schema::table('cuentas_contables', function (Blueprint $table): void {
            $table->string('company_id', 3)->default('168');
            $table->dropUnique(['cuenta']);
            $table->unique(['company_id', 'cuenta']);
        });
        Schema::create('centros_de_costo', function (Blueprint $table): void {
            $table->id();
            $table->integer('id_centro_costo');
            $table->string('company_id');
            $table->string('id_grupo')->nullable();
            $table->string('descripcion')->nullable();
            $table->boolean('ocultar')->default(false);
            $table->timestamps();
        });
        $migration = glob(database_path('migrations/*_add_cuenta_ruta_id_to_centros_de_costo_table.php'))[0];
        (require $migration)->up();
        $rutasMigration = glob(database_path('migrations/*_create_centro_costo_cuenta_rutas_table.php'))[0];
        (require $rutasMigration)->up();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('centro_costo_cuenta_rutas');
        Schema::dropIfExists('centros_de_costo');
        Schema::dropIfExists('cuentas_contables');
        parent::tearDown();
    }

    public function test_searches_code_and_description_only_within_center_company(): void
    {
        $centro = $this->centro('168-Grupo Joselito');
        $cuenta = $this->cuenta('168', '10013001', 'Ruta San Cristobal 1 Rafaela');
        $this->cuenta('169', '10013001', 'Ruta San Cristobal 1 Otra Empresa');
        $this->cuenta('168', '10013002', 'Ruta Santiago');
        foreach (['Rafaela', '10013001'] as $busqueda) {
            $this->getJson($this->url($centro).'/cuentas?buscar='.$busqueda)
                ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $cuenta->id);
        }
        $this->getJson($this->url($centro).'/cuentas?buscar=Inexistente')->assertOk()->assertExactJson([]);
        Http::assertNothingSent();
    }

    public function test_association_applies_to_entire_route_and_future_terminals_without_crossing_companies(): void
    {
        $centro = $this->centro();
        $otroCentro = $this->centro();
        $otraRuta = $this->centro();
        $otraRuta->update(['id_grupo' => 'San Cristobal 2']);
        $otraEmpresa = $this->centro('169');
        $cuenta = $this->cuenta('168', '10013001', 'Ruta San Cristobal 1 Rafaela');
        $this->putJson($this->url($centro).'/cuenta', ['cuenta_ruta_id' => $cuenta->id])
            ->assertOk()->assertJsonPath('CuentaRuta', '10013001')->assertJsonPath('CuentaRutaDescripcion', 'Ruta San Cristobal 1 Rafaela');
        $this->assertSame($cuenta->id, CentroCostoCuentaRuta::query()->first()->cuenta_ruta_id);
        $nuevoCentro = $this->centro();
        $cuenta->update(['descripcion' => 'Nueva descripcion']);
        $registros = collect($this->getJson('/api-centros-costo?empresa=todas')->assertOk()->json());
        $this->assertSame('Nueva descripcion', $registros->firstWhere('id', $centro->id)['CuentaRutaDescripcion']);
        $this->assertSame('10013001', $registros->firstWhere('id', $otroCentro->id)['CuentaRuta']);
        $this->assertSame('10013001', $registros->firstWhere('id', $nuevoCentro->id)['CuentaRuta']);
        $this->assertSame('', $registros->firstWhere('id', $otraRuta->id)['CuentaRuta']);
        $this->assertSame('', $registros->firstWhere('id', $otraEmpresa->id)['CuentaRuta']);
        Http::assertNothingSent();
    }

    public function test_can_change_and_remove_association_without_changing_center_visibility(): void
    {
        $centro = $this->centro();
        $centro->update(['ocultar' => true]);
        $primera = $this->cuenta('168', '10013001', 'Rafaela');
        $segunda = $this->cuenta('168', '10013002', 'Otra cuenta');
        foreach ([$primera->id, $segunda->id, null] as $id) {
            $this->putJson($this->url($centro).'/cuenta', ['cuenta_ruta_id' => $id])
                ->assertOk()->assertJsonPath('CuentaRutaId', $id);
            $this->assertSame($id, CentroCostoCuentaRuta::query()->first()->cuenta_ruta_id);
            $registros = collect($this->getJson('/api-centros-costo?empresa=todas')->assertOk()->json());
            $this->assertSame($id, $registros->firstWhere('id', $centro->id)['CuentaRutaId']);
            $this->assertTrue($centro->fresh()->ocultar);
        }
    }

    public function test_rejects_other_company_unknown_accounts_and_malformed_requests(): void
    {
        $centro = $this->centro();
        $cuenta = $this->cuenta('169', '10013001', 'Rafaela');
        foreach ([['cuenta_ruta_id' => $cuenta->id], ['cuenta_ruta_id' => 99999], ['cuenta_ruta_id' => 'abc'], []] as $payload) {
            $this->putJson($this->url($centro).'/cuenta', $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('cuenta_ruta_id');
            $this->assertNull($centro->fresh()->cuenta_ruta_id);
        }
        $this->getJson($this->url($centro).'/cuentas?buscar='.str_repeat('a', 151))
            ->assertUnprocessable()->assertJsonValidationErrors('buscar');
        $this->putJson('/api-centros-costo/99999/cuenta', ['cuenta_ruta_id' => null])->assertNotFound();
    }

    public function test_supports_negosur_and_historical_company_labels(): void
    {
        $centro = $this->centro('169-Negosur');
        $cuenta = $this->cuenta('169', '10013001', 'Rafaela');
        $this->putJson($this->url($centro).'/cuenta', ['cuenta_ruta_id' => $cuenta->id])->assertOk();
        $this->getJson($this->url($centro).'/cuentas?buscar=Rafaela')->assertOk()->assertJsonPath('0.id', $cuenta->id);
    }

    public function test_search_finds_accounts_beyond_first_page_of_results(): void
    {
        $centro = $this->centro();
        for ($numero = 0; $numero < 55; $numero++) {
            $this->cuenta('168', (string) (10013000 + $numero), 'Cuenta '.$numero);
        }
        $this->getJson($this->url($centro).'/cuentas')->assertOk()->assertJsonCount(50);
        $this->getJson($this->url($centro).'/cuentas?buscar=10013054')->assertOk()->assertJsonCount(1)->assertJsonPath('0.cuenta', '10013054');
    }

    public function test_deleting_an_account_clears_association_and_migration_can_roll_back(): void
    {
        $centro = $this->centro();
        $cuenta = $this->cuenta('168', '10013001', 'Rafaela');
        CentroCostoCuentaRuta::factory()->create(['company_id' => '168', 'ruta_key' => CentroCostoCuentaRuta::claveRuta($centro->id_grupo), 'cuenta_ruta_id' => $cuenta->id]);
        $cuenta->delete();
        $this->assertNull(CentroCostoCuentaRuta::query()->first()->cuenta_ruta_id);
        $this->assertNull($centro->fresh()->cuenta_ruta_id);
        $migration = glob(database_path('migrations/*_add_cuenta_ruta_id_to_centros_de_costo_table.php'))[0];
        (require $migration)->down();
        $this->assertFalse(Schema::hasColumn('centros_de_costo', 'cuenta_ruta_id'));
        $this->assertSame(1, CentroDeCosto::query()->count());
    }

    public function test_route_identifier_is_preserved_when_route_description_changes(): void
    {
        $primero = $this->centro();
        $primero->update(['id_grupo' => '113-05 - Ng Ruta San Cristobal 1']);
        $cuenta = $this->cuenta('168', '10013001', 'Rafaela');
        $this->putJson($this->url($primero).'/cuenta', ['cuenta_ruta_id' => $cuenta->id])->assertOk();
        $primero->update(['id_grupo' => '113-Ruta renombrada']);
        $nuevo = $this->centro();
        $nuevo->update(['id_grupo' => '113-Ruta renombrada']);
        $this->getJson('/api-centros-costo?empresa=todas')->assertOk()->assertJsonPath('0.CuentaRuta', '10013001')->assertJsonPath('1.CuentaRuta', '10013001');
    }

    public function test_rejects_saving_a_center_without_a_route(): void
    {
        $centro = $this->centro();
        $centro->update(['id_grupo' => null]);
        $cuenta = $this->cuenta('168', '10013001', 'Rafaela');
        $this->putJson($this->url($centro).'/cuenta', ['cuenta_ruta_id' => $cuenta->id])->assertUnprocessable();
        $this->assertSame(0, CentroCostoCuentaRuta::query()->count());
    }

    public function test_migration_promotes_explicit_terminal_association_to_route(): void
    {
        $centro = $this->centro();
        $cuenta = $this->cuenta('168', '10013001', 'Rafaela');
        $centro->update(['cuenta_ruta_id' => $cuenta->id]);
        $migration = glob(database_path('migrations/*_create_centro_costo_cuenta_rutas_table.php'))[0];
        (require $migration)->down();
        (require $migration)->up();
        $this->assertSame($cuenta->id, CentroCostoCuentaRuta::query()->first()->cuenta_ruta_id);
        $nuevo = $this->centro();
        $registros = collect($this->getJson('/api-centros-costo?empresa=todas')->assertOk()->json());
        $this->assertSame('10013001', $registros->firstWhere('id', $nuevo->id)['CuentaRuta']);
    }

    private function centro(string $empresa = '168'): CentroDeCosto
    {
        return CentroDeCosto::query()->create([
            'id_centro_costo' => CentroDeCosto::query()->count() + 1,
            'company_id' => $empresa, 'id_grupo' => 'San Cristobal 1', 'descripcion' => 'Agencia',
        ]);
    }

    private function cuenta(string $empresa, string $codigo, string $descripcion): CuentaContable
    {
        return CuentaContable::query()->create(['company_id' => $empresa, 'cuenta' => $codigo, 'descripcion' => $descripcion]);
    }

    private function url(CentroDeCosto $centro): string
    {
        return '/api-centros-costo/'.$centro->id;
    }
}
