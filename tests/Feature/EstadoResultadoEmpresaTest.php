<?php

namespace Tests\Feature;

use App\Http\Controllers\ContabilidadEstadoResultadoController;
use App\Http\Requests\ContabilidadEmpresaRequest;
use App\Models\CuentaContable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EstadoResultadoEmpresaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        Http::preventStrayRequests();
        Schema::create('cuentas_contables', function (Blueprint $table): void {
            $table->id();
            $table->string('company_id');
            $table->string('cuenta');
            $table->string('descripcion');
            $table->string('tipo');
            $table->timestamps();
        });
        foreach (['168', '169'] as $empresa) {
            CuentaContable::query()->create(['company_id' => $empresa, 'cuenta' => '4001', 'descripcion' => 'Ingreso '.$empresa, 'tipo' => 'Ingreso']);
        }
        CuentaContable::query()->create(['company_id' => '168', 'cuenta' => '6001', 'descripcion' => 'Gasto 168', 'tipo' => 'Gasto']);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('cuentas_contables');
        parent::tearDown();
    }

    public function test_metadata_uses_local_catalog_for_selected_company(): void
    {
        $this->getJson(route('contabilidad.reportes.estado-resultado.meta'))->assertOk()->assertJsonPath('total_cuentas', 2);
        $this->getJson(route('contabilidad.reportes.estado-resultado.meta', ['empresa' => '169']))->assertOk()->assertJsonPath('total_cuentas', 1)->assertJsonPath('conteo_por_tipo.Ingreso', 1);
        $this->getJson(route('contabilidad.reportes.estado-resultado.meta', ['empresa' => '169', 'tipos' => ['Gasto']]))->assertOk()->assertJsonPath('total_cuentas', 0);
        Http::assertNothingSent();
    }

    public function test_movements_come_from_api_and_cache_is_isolated_by_company(): void
    {
        Cache::put('estado_resultado_api:'.sha1('4001|2026-10-01'), [['CREDITO' => 99999]], now()->addHour());
        Http::fake(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $parametros);

            return Http::response(['Result' => ['Det' => [['DEBITO' => 0, 'CREDITO' => $parametros['intIdEmpresa'] === '168' ? 100 : 250]]]]);
        });
        foreach (['168' => 100, '169' => 250] as $empresa => $monto) {
            $this->consultar((string) $empresa)->assertOk()->assertJsonPath('resumen.ingresos_periodo', $monto)->assertJsonPath('grupos.0.cuentas.0.descripcion', 'Ingreso '.$empresa);
        }
        $this->consultar('168')->assertOk()->assertJsonPath('resumen.ingresos_periodo', 100);
        $this->consultar('169')->assertOk()->assertJsonPath('resumen.ingresos_periodo', 250);
        Http::assertSentCount(2);
        $this->assertFalse(Schema::hasTable('entradas_diario'));
    }

    public function test_rejects_invalid_company_and_date_ranges_without_calling_api(): void
    {
        foreach (['999', 'todas', ''] as $empresa) {
            $this->getJson(route('contabilidad.reportes.estado-resultado.meta', ['empresa' => $empresa]))->assertUnprocessable()->assertJsonValidationErrors('empresa');
            $this->consultar($empresa)->assertUnprocessable()->assertJsonValidationErrors('empresa');
        }
        $this->getJson(route('contabilidad.reportes.estado-resultado.data', ['empresa' => '168', 'fecha_desde' => '2026-10-02', 'fecha_hasta' => '2026-10-01']))->assertUnprocessable()->assertJsonValidationErrors('fecha_hasta');
        Http::assertNothingSent();
    }

    public function test_external_failures_are_reported_and_not_cached_as_zero(): void
    {
        Http::fake(['*' => Http::response([], 500)]);
        $this->consultar('169')->assertStatus(502);
        $this->consultar('169')->assertStatus(502);
        Http::assertSentCount(2);
    }

    public function test_view_has_company_filter_and_company_is_sent_in_all_requests_and_pdf(): void
    {
        $request = ContabilidadEmpresaRequest::create('/', 'GET', ['empresa' => '169']);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();
        $datos = app(ContabilidadEstadoResultadoController::class)->index($request)->getData();
        $fuente = file_get_contents(resource_path('views/contabilidad/reportes/estado_resultado.blade.php'));
        $contenido = Blade::render(str_replace(["@extends('app')", "@section('content')", "@section('script')", '@endsection'], '', $fuente), $datos);
        $this->assertStringContainsString('id="empresaEstadoResultado"', $contenido);
        $this->assertStringContainsString('value="169" selected', $contenido);
        $this->assertSame(2, substr_count($contenido, "params.set('empresa', empresaConsulta)"));
        $this->assertStringContainsString("doc.text('Estado de Resultado - ' + nombreEmpresa()", $contenido);
    }

    private function consultar(string $empresa): \Illuminate\Testing\TestResponse
    {
        return $this->getJson(route('contabilidad.reportes.estado-resultado.data', [
            'empresa' => $empresa, 'fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-01', 'tipos' => ['Ingreso'],
        ]));
    }
}
