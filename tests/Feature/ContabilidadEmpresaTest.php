<?php

namespace Tests\Feature;

use App\Models\CuentaContable;
use App\Models\DetalleCuenta;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContabilidadEmpresaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        Http::preventStrayRequests();
        (require database_path('migrations/2026_02_23_120000_create_cuentas_contables_table.php'))->up();
        (require database_path('migrations/2026_02_24_090000_create_detalle_cuentas_table.php'))->up();
        CuentaContable::query()->create(['cuenta' => '10013', 'descripcion' => 'Cuenta anterior']);
        DetalleCuenta::query()->create(['external_key' => 'anterior', 'cuenta' => '10013', 'fecha' => '2026-10-01']);
        $migration = glob(database_path('migrations/*_add_company_id_to_accounting_account_tables.php'))[0];
        (require $migration)->up();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('detalle_cuentas');
        Schema::dropIfExists('cuentas_contables');
        parent::tearDown();
    }

    public function test_preserves_existing_data_and_filters_catalog_by_company(): void
    {
        $this->assertSame('168', DetalleCuenta::query()->first()->company_id);
        CuentaContable::query()->create(['company_id' => '169', 'cuenta' => '10013', 'descripcion' => 'Negosur']);
        $this->getJson('/api-cuentas')->assertOk()->assertJsonCount(1)->assertJsonPath('0.DESCRIPCION', 'Cuenta anterior');
        $this->getJson('/api-cuentas?empresa=169')->assertOk()->assertJsonCount(1)->assertJsonPath('0.DESCRIPCION', 'Negosur');
        Http::assertNothingSent();
    }

    public function test_syncs_catalog_for_selected_company_without_overwriting_other_company(): void
    {
        Http::fake(['*' => Http::response(['Result' => ['Det' => [['CUENTA' => '10013', 'DESCRIPCION' => 'Negosur']]]])]);
        $this->getJson('/api-cuentas?empresa=169')->assertOk()->assertJsonPath('0.DESCRIPCION', 'Negosur');
        $this->postJson('/api-cuentas/sync?empresa=169')->assertOk();
        $this->assertSame('Cuenta anterior', CuentaContable::query()->where('company_id', '168')->first()->descripcion);
        $this->assertSame(2, CuentaContable::query()->count());
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'intIdEmpresa=169'));
    }

    public function test_same_account_and_movement_remain_separate_and_sync_is_idempotent(): void
    {
        Http::fake(['*' => Http::response(['Result' => ['Det' => [[
            'NOASIENTO' => '1', 'FECHA' => '2026-10-30', 'DEBITO' => '25', 'CREDITO' => '0',
        ]]]])]);
        foreach (['168', '169', '169'] as $empresa) {
            $this->getJson('/api-entradas?empresa='.$empresa.'&cuenta=10013&fecha_inicio=2026-10-30&fecha_fin=2026-10-30')
                ->assertOk()->assertJsonCount(1, 'result.Det');
        }
        $this->assertSame(2, DetalleCuenta::query()->whereDate('fecha', '2026-10-30')->count());
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'intIdEmpresa=169') && str_contains($request->url(), 'strCuenta=10013'));
    }

    public function test_sync_sends_requested_limit_and_saves_two_hundred_accounts(): void
    {
        $cuentas = collect(range(1, 200))->map(fn (int $numero): array => ['CUENTA' => '200'.$numero, 'DESCRIPCION' => 'Cuenta '.$numero])->all();
        Http::fake(['*' => Http::response($cuentas)]);
        $this->postJson('/api-cuentas/sync?empresa=169', ['limite' => 200])
            ->assertOk()->assertJsonPath('total_recibidas', 200)->assertJsonPath('creadas', 200);
        $this->assertSame(200, CuentaContable::query()->where('company_id', '169')->count());
        Http::assertSent(function ($request): bool {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $parametros);

            return $parametros['intLimite'] === '200' && $parametros['intIdEmpresa'] === '169';
        });
    }

    public function test_sync_accepts_custom_limit_and_defaults_to_two_hundred(): void
    {
        Http::fake(['*' => Http::response([])]);
        $this->postJson('/api-cuentas/sync?empresa=168', ['limite' => 350])->assertOk();
        $this->postJson('/api-cuentas/sync?empresa=168')->assertOk();
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'intLimite=350'));
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'intLimite=200'));
    }

    public function test_sync_rejects_invalid_limits_without_contacting_api(): void
    {
        foreach ([0, -1, 1.5, 'abc', null, 10001] as $limite) {
            $this->postJson('/api-cuentas/sync?empresa=168', ['limite' => $limite])
                ->assertUnprocessable()->assertJsonValidationErrors('limite');
        }
        Http::assertNothingSent();
    }

    public function test_validates_company_and_scopes_account_edits_and_deletes(): void
    {
        $payload = ['empresa' => '169', 'cuenta' => '10013', 'descripcion' => 'Negosur'];
        $id = $this->postJson('/api-cuentas', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api-cuentas', $payload)->assertUnprocessable()->assertJsonValidationErrors('cuenta');
        $this->putJson('/api-cuentas/'.$id, [...$payload, 'descripcion' => 'Nueva'])->assertOk();
        $this->putJson('/api-cuentas/'.$id, ['empresa' => '168', 'cuenta' => '999', 'descripcion' => 'Incorrecta'])->assertNotFound();
        $this->deleteJson('/api-cuentas/'.$id.'?empresa=168')->assertNotFound();
        $this->deleteJson('/api-cuentas/'.$id.'?empresa=169')->assertOk();
        foreach (['999', 'todas', ''] as $empresa) {
            $this->getJson('/api-cuentas?empresa='.$empresa)->assertUnprocessable()->assertJsonValidationErrors('empresa');
            $this->getJson('/api-entradas?empresa='.$empresa)->assertUnprocessable()->assertJsonValidationErrors('empresa');
        }
    }

    public function test_reports_external_errors_without_showing_other_company_data(): void
    {
        Http::fake(['*' => Http::response([], 500)]);
        $this->getJson('/api-cuentas?empresa=169')->assertStatus(502);
        $this->getJson('/api-entradas?empresa=169&cuenta=10013&fecha=2026-10-30')->assertStatus(502);
        $this->assertSame(1, CuentaContable::query()->count());
    }

    public function test_view_exposes_company_filter_and_passes_company_to_requests(): void
    {
        $view = file_get_contents(resource_path('views/contabilidad/index.blade.php'));
        $this->assertStringContainsString('id="filtroEmpresaContabilidad"', $view);
        $this->assertStringContainsString('169 - Negosur', $view);
        $this->assertStringContainsString('id="limiteSincronizarCuentas"', $view);
        $this->assertStringContainsString('body: JSON.stringify({ limite })', $view);
        $this->assertStringContainsString("fetch('/api-cuentas?empresa=' + empresaSeleccionada())", $view);
        $this->assertStringContainsString('consultarEntradasDia(cuenta, fecha, empresa)', $view);
        $this->assertStringContainsString('version !== versionConsultaEmpresa', $view);
    }
}
