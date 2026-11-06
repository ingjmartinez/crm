<?php

namespace Tests\Feature;

use App\Http\Controllers\Api;
use App\Models\EntradaDiario;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MovimientoMayorCsvExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        Schema::create('entradas_diario', function (Blueprint $table): void {
            $table->id();
            $table->string('external_key')->unique();
            $table->string('payload_hash')->nullable();
            $table->string('no_asiento')->nullable();
            $table->string('company_id')->nullable();
            $table->date('fecha')->nullable();
            $table->string('fecha_raw')->nullable();
            $table->string('ref')->nullable();
            $table->string('no_ref')->nullable();
            $table->string('cuenta')->nullable();
            $table->decimal('debito', 18, 2)->default(0);
            $table->decimal('credito', 18, 2)->default(0);
            $table->text('descripcion')->nullable();
            $table->string('id_centro_costo')->nullable();
            $table->string('id_grupo')->nullable();
            $table->string('id_sub_grupo')->nullable();
            $table->string('id_division')->nullable();
            $table->string('id_sociedad')->nullable();
            $table->boolean('conciliado')->default(false);
            $table->string('modulo')->nullable();
            $table->dateTime('fecha_grabado')->nullable();
            $table->dateTime('fecha_modificado')->nullable();
            $table->string('id_viejo')->nullable();
            $table->string('centro_costo')->nullable();
            $table->string('grupo')->nullable();
            $table->string('sub_grupo')->nullable();
            $table->string('division')->nullable();
            $table->string('creado_por')->nullable();
            $table->string('modificado_por')->nullable();
            $table->string('ref_desc')->nullable();
            $table->string('sociedad')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('entradas_diario');

        parent::tearDown();
    }

    public function test_screen_contains_complete_csv_button(): void
    {
        $viewSource = file_get_contents(resource_path('views/contabilidad/movimiento-mayor.blade.php'));

        $this->assertTrue(Route::has('contabilidad.movimiento-mayor.exportar-csv'));
        $this->assertIsString($viewSource);
        $this->assertStringContainsString('id="btnExportarEntradasDiarioCsv"', $viewSource);
        $this->assertStringContainsString("route('contabilidad.movimiento-mayor.exportar-csv')", $viewSource);
        $this->assertStringContainsString("title: 'Generando CSV completo'", $viewSource);
        $this->assertStringContainsString("title: 'Descarga iniciada'", $viewSource);
        $this->assertStringContainsString('Swal.showLoading()', $viewSource);
    }

    public function test_csv_export_downloads_every_row_matching_selected_filters(): void
    {
        $matchingFirst = $this->createEntradaDiario([
            'external_key' => 'matching-first',
            'fecha' => '2026-08-01',
            'cuenta' => '5101',
            'descripcion' => 'Primer movimiento',
        ]);
        $matchingSecond = $this->createEntradaDiario([
            'external_key' => 'matching-second',
            'fecha' => '2026-08-02',
            'cuenta' => '5101',
            'descripcion' => '=FORMULA',
        ]);
        $this->createEntradaDiario([
            'external_key' => 'outside-range',
            'fecha' => '2026-08-03',
            'cuenta' => '5101',
            'descripcion' => 'No debe exportarse',
        ]);
        $this->createEntradaDiario([
            'external_key' => 'other-company',
            'company_id' => '169',
            'fecha' => '2026-08-01',
            'cuenta' => '5101',
            'descripcion' => 'Otra empresa',
        ]);

        $this->assertSame(2, EntradaDiario::query()
            ->where('company_id', '168')
            ->whereBetween('fecha', ['2026-08-01', '2026-08-02'])
            ->where('cuenta', '5101')
            ->count());

        $response = $this->actingAs($this->user())->withoutMiddleware()
            ->get(route('contabilidad.movimiento-mayor.exportar-csv', [
                'empresa' => '168',
                'fecha_inicio' => '2026-08-01',
                'fecha_fin' => '2026-08-02',
                'cuenta' => '5101',
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertDownload('movimiento-mayor-168-20260801-a-20260802.csv');

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBFNoAsiento,Fecha", $content);
        $this->assertStringContainsString((string) $matchingFirst->no_asiento, $content);
        $this->assertStringContainsString((string) $matchingSecond->no_asiento, $content);
        $this->assertStringContainsString("'=FORMULA", $content);
        $this->assertStringNotContainsString('No debe exportarse', $content);
        $this->assertStringNotContainsString('Otra empresa', $content);
        $this->assertCount(3, preg_split('/\r\n|\r|\n/', trim($content)));
    }

    public function test_csv_export_rejects_an_invalid_date_range(): void
    {
        $response = $this->actingAs($this->user())->withoutMiddleware()
            ->getJson(route('contabilidad.movimiento-mayor.exportar-csv', [
                'empresa' => '168',
                'fecha_inicio' => '2026-08-02',
                'fecha_fin' => '2026-08-01',
            ]));

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'La fecha inicio no puede ser mayor que la fecha fin.');
    }

    public function test_sync_processes_large_daily_response_in_batches_without_losing_later_duplicates(): void
    {
        $items = [];
        for ($numero = 1; $numero <= 600; $numero++) {
            $items[] = [
                'NOASIENTO' => 'ASIENTO-'.$numero,
                'CUENTA' => '5101',
                'FECHA' => '2026-07-14',
                'DEBITO' => '25.00',
                'CREDITO' => '0',
                'DESCRIPCION' => 'Original',
            ];
        }
        $items[] = ['NOASIENTO' => 'ASIENTO-1', 'CUENTA' => '5101', 'FECHA' => '2026-07-14', 'DEBITO' => '30.00', 'CREDITO' => '0', 'DESCRIPCION' => 'Última versión'];
        $items[] = ['NOASIENTO' => 'EXCLUIDO', 'CUENTA' => '9999', 'FECHA' => '2026-07-14'];
        $items[] = ['CUENTA' => '5101'];

        $metodo = new \ReflectionMethod(Api::class, 'procesarEntradasDiarioItems');
        $resultado = $metodo->invokeArgs(app(Api::class), [&$items, '168', '2026-07-14', ['5101' => true]]);

        $this->assertSame([], $items);
        $this->assertSame(['total_recibidos' => 603, 'creados' => 600, 'actualizados' => 0, 'sin_cambios' => 0, 'omitidos' => 2], $resultado);
        $this->assertSame(600, EntradaDiario::query()->count());
        $this->assertSame('Última versión', EntradaDiario::query()->where('no_asiento', 'ASIENTO-1')->value('descripcion'));

        $repetidos = [['NOASIENTO' => 'ASIENTO-1', 'CUENTA' => '5101', 'FECHA' => '2026-07-14', 'DEBITO' => '30.00', 'CREDITO' => '0', 'DESCRIPCION' => 'Última versión']];
        $sinCambios = $metodo->invokeArgs(app(Api::class), [&$repetidos, '168', '2026-07-14', null]);
        $this->assertSame(1, $sinCambios['sin_cambios']);
    }

    public function test_multiple_accounts_filter_local_queries_and_complete_csv(): void
    {
        foreach (['200111001', '400120001', '999999'] as $cuenta) {
            $this->createEntradaDiario(['cuenta' => $cuenta, 'descripcion' => 'Cuenta '.$cuenta]);
        }
        $this->createEntradaDiario(['cuenta' => '400120001', 'company_id' => '169', 'descripcion' => 'Otra empresa']);
        $filtros = ['empresa' => '168', 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-08-01', 'cuentas' => ['200111001', '400120001']];
        $this->actingAs($this->user())->withoutMiddleware()->getJson('/api-entradas-diario?'.http_build_query($filtros))
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('summary.registros', 2);
        $csv = $this->get(route('contabilidad.movimiento-mayor.exportar-csv', $filtros))->assertOk()->streamedContent();
        $this->assertStringContainsString('Cuenta 200111001', $csv);
        $this->assertStringContainsString('Cuenta 400120001', $csv);
        $this->assertStringNotContainsString('Cuenta 999999', $csv);
        $this->assertStringNotContainsString('Otra empresa', $csv);
        unset($filtros['cuentas']);
        $this->getJson('/api-entradas-diario?'.http_build_query($filtros))->assertOk()->assertJsonCount(3, 'data');
    }

    #[DataProvider('accountSelections')]
    public function test_sync_requests_only_the_selected_accounts_from_the_api(array $seleccion, int $peticiones, int $registros): void
    {
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $params);
            $cuentas = isset($params['strCuenta']) ? [$params['strCuenta']] : ['200111001', '400120001', '999999'];
            $items = array_map(fn (string $cuenta): array => ['NOASIENTO' => 'ASIENTO-'.$cuenta, 'CUENTA' => $cuenta, 'COMPANYID' => '168', 'FECHA' => $params['dtFecha'], 'CREDITO' => 100, 'DEBITO' => 0], $cuentas);

            return Http::response(['Result' => ['Det' => $items]]);
        });
        $filtros = ['empresa' => '168', 'fecha_inicio' => '2026-05-31', 'fecha_fin' => '2026-05-31', ...$seleccion];
        $this->actingAs($this->user())->withoutMiddleware()->postJson('/api-entradas-diario/sync', $filtros)
            ->assertOk()->assertJsonPath('creados', $registros);
        Http::assertSentCount($peticiones);
        $this->assertSame($registros, EntradaDiario::query()->count());
        if ($seleccion !== []) {
            $this->assertFalse(EntradaDiario::query()->where('cuenta', '999999')->exists());
            Http::assertSent(function (Request $request) use ($seleccion): bool {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $params);

                return in_array($params['strCuenta'] ?? null, $seleccion['cuentas'] ?? [$seleccion['cuenta']], true);
            });
        }
        $this->postJson('/api-entradas-diario/sync', $filtros)->assertOk()->assertJsonPath('creados', 0)->assertJsonPath('sin_cambios', $registros);
        $this->assertSame($registros, EntradaDiario::query()->count());
    }

    public static function accountSelections(): array
    {
        return [
            'dos cuentas' => [['cuentas' => ['200111001', '400120001']], 2, 2],
            'una cuenta' => [['cuentas' => ['400120001']], 1, 1],
            'filtro anterior de una cuenta' => [['cuenta' => '400120001'], 1, 1],
            'todas' => [[], 1, 3],
        ];
    }

    public function test_sync_continues_through_days_without_movements_for_an_account(): void
    {
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $params);
            if ($params['strCuenta'] === '400120001' && $params['dtFecha'] === '2026-05-30') {
                return Http::response('No existen datos para la empresa Id "168" en fecha "2026-05-30" y para la cuenta "400120001".', 200);
            }

            return Http::response(['Result' => ['Det' => [['NOASIENTO' => $params['strCuenta'].'-'.$params['dtFecha'], 'CUENTA' => $params['strCuenta'], 'FECHA' => $params['dtFecha'], 'CREDITO' => 100]]]]);
        });
        $this->actingAs($this->user())->withoutMiddleware()->postJson('/api-entradas-diario/sync', ['empresa' => '168', 'fecha_inicio' => '2026-05-30', 'fecha_fin' => '2026-05-31', 'cuentas' => ['200111001', '400120001']])
            ->assertOk()->assertJsonPath('creados', 3);
        Http::assertSentCount(4);
        $this->assertTrue(EntradaDiario::query()->where('cuenta', '400120001')->whereDate('fecha', '2026-05-31')->exists());
    }

    public function test_existing_account_type_filter_keeps_one_general_api_request(): void
    {
        Schema::create('cuentas_contables', function (Blueprint $table): void {
            $table->string('cuenta');
            $table->string('tipo');
        });
        DB::table('cuentas_contables')->insert([
            ['cuenta' => '200111001', 'tipo' => 'Pasivo'],
            ['cuenta' => '400120001', 'tipo' => 'Ingreso'],
            ['cuenta' => '400120002', 'tipo' => 'Ingreso'],
        ]);
        Http::fake(fn () => Http::response(['Result' => ['Det' => [
            ['NOASIENTO' => 'PASIVO', 'CUENTA' => '200111001', 'FECHA' => '2026-05-31', 'CREDITO' => 100],
            ['NOASIENTO' => 'INGRESO', 'CUENTA' => '400120001', 'FECHA' => '2026-05-31', 'CREDITO' => 10],
        ]]]));
        $this->actingAs($this->user())->withoutMiddleware()->postJson('/api-entradas-diario/sync', ['empresa' => '168', 'fecha_inicio' => '2026-05-31', 'fecha_fin' => '2026-05-31', 'tipos' => ['Ingreso']])
            ->assertOk()->assertJsonPath('creados', 1);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $params);

            return ! isset($params['strCuenta']);
        });
        $this->assertFalse(EntradaDiario::query()->where('cuenta', '200111001')->exists());
        Schema::drop('cuentas_contables');
    }

    public function test_sync_reports_external_errors_instead_of_treating_them_as_empty_accounts(): void
    {
        Http::fake(fn () => Http::response('Servicio no disponible', 503));
        $this->actingAs($this->user())->withoutMiddleware()->postJson('/api-entradas-diario/sync', ['empresa' => '168', 'fecha_inicio' => '2026-05-31', 'fecha_fin' => '2026-05-31', 'cuentas' => ['400120001']])
            ->assertStatus(502);
        $this->assertSame(0, EntradaDiario::query()->count());
    }

    #[DataProvider('invalidAccountSelections')]
    public function test_rejects_invalid_or_ambiguous_account_selections(array $seleccion): void
    {
        $this->actingAs($this->user())->withoutMiddleware()->postJson('/api-entradas-diario/sync', ['empresa' => '168', 'fecha_inicio' => '2026-05-31', 'fecha_fin' => '2026-05-31', ...$seleccion])->assertUnprocessable();
        Http::assertNothingSent();
        $this->assertSame(0, EntradaDiario::query()->count());
    }

    public static function invalidAccountSelections(): array
    {
        return [
            'lista vacia' => [['cuentas' => []]],
            'texto en lugar de lista' => [['cuentas' => '200111001,400120001']],
            'cuenta vacia' => [['cuentas' => ['']]],
            'cuentas repetidas' => [['cuentas' => ['200111001', '200111001']]],
            'cuenta y lista' => [['cuenta' => '200111001', 'cuentas' => ['400120001']]],
        ];
    }

    public function test_delete_respects_multiple_accounts_and_other_companies(): void
    {
        foreach (['200111001', '400120001', '999999'] as $cuenta) {
            $this->createEntradaDiario(['cuenta' => $cuenta]);
        }
        $this->createEntradaDiario(['cuenta' => '400120001', 'company_id' => '169']);
        $this->actingAs($this->user())->withoutMiddleware()->deleteJson('/api-entradas-diario', ['empresa' => '168', 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-08-01', 'cuentas' => ['200111001', '400120001']])
            ->assertOk()->assertJsonPath('eliminados', 2);
        $this->assertSame(2, EntradaDiario::query()->count());
        $this->assertTrue(EntradaDiario::query()->where('cuenta', '999999')->exists());
        $this->assertTrue(EntradaDiario::query()->where('company_id', '169')->exists());
    }

    public function test_account_picker_selects_the_two_report_accounts_and_can_return_to_all_accounts(): void
    {
        $codigo = <<<'JS'
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(process.argv[2],'utf8');
const script = source.match(/@section\('script'\)[\s\S]*?<script>([\s\S]*?)<\/script>/)[1].replace(/\{\{[\s\S]*?\}\}/g,'');
const elementos = new Map();
function elemento(id) {
    if (!elementos.has(id)) elementos.set(id,{value:'',checked:false,disabled:false,eventos:{},addEventListener(event,fn){this.eventos[event]=fn;}});
    return elementos.get(id);
}
const checks = ['entrada-tipo-ingreso','entrada-tipo-costo','entrada-tipo-gasto'].map(elemento);
global.document = {getElementById:elemento,querySelectorAll(selector){return selector.includes(':not') ? checks : [elemento('entrada-tipo-todos'),...checks];}};
vm.runInThisContext(script);
elemento('entradaDiarioEmpresa').value='168';
elemento('entradaDiarioFechaInicio').value='2026-05-01';
elemento('entradaDiarioFechaFin').value='2026-05-31';
elemento('entradaDiarioAlcanceCuentas').value='seleccionadas';
assert.throws(getEntradasDiarioFiltros,/Indica al menos una cuenta/);
elemento('entradaDiarioCentroCosto').value='CENTRO';
checks[0].checked=true;
elemento('btnCuentasNoTradicional').eventos.click();
assert.equal(elemento('entradaDiarioCentroCosto').value,'');
assert.equal(elemento('entradaDiarioCuenta').disabled,false);
let filtros=getEntradasDiarioFiltros();
assert.deepEqual(filtros.cuentas,['200111001','400120001']);
assert.deepEqual(filtros.params.getAll('cuentas[]'),['200111001','400120001']);
assert.deepEqual(filtros.tipos,[]);
assert.equal(filtros.params.has('cuenta'),false);
elemento('entradaDiarioCuenta').value='200111001; 400120001 200111001';
assert.deepEqual(getEntradasDiarioFiltros().cuentas,['200111001','400120001']);
let body;
global.fetch=async (url,options)=>{body=JSON.parse(options.body);return {ok:true,json:async()=>({creados:2})};};
(async()=>{
    await sincronizarBloqueEntradasDiario(filtros,{inicio:'2026-05-31',fin:'2026-05-31'});
    assert.deepEqual(body.cuentas,['200111001','400120001']);
    elemento('entradaDiarioAlcanceCuentas').value='todas';
    actualizarAlcanceCuentas();
    assert.equal(elemento('entradaDiarioCuenta').disabled,true);
    filtros=getEntradasDiarioFiltros();
    assert.deepEqual(filtros.cuentas,[]);
    assert.equal(filtros.params.has('cuentas[]'),false);
    await sincronizarBloqueEntradasDiario(filtros,{inicio:'2026-05-31',fin:'2026-05-31'});
    assert.equal(Object.hasOwn(body,'cuentas'),false);
})().catch(error=>{console.error(error);process.exitCode=1;});
JS;
        $proceso = new Process(['node', '-', resource_path('views/contabilidad/movimiento-mayor.blade.php')]);
        $proceso->setInput($codigo)->setTimeout(15)->run();
        $this->assertTrue($proceso->isSuccessful(), $proceso->getErrorOutput());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createEntradaDiario(array $attributes): EntradaDiario
    {
        $entradaDiario = EntradaDiario::query()->create(array_merge([
            'external_key' => 'entry-'.fake()->unique()->uuid(),
            'payload_hash' => hash('sha256', fake()->uuid()),
            'no_asiento' => fake()->unique()->numerify('ASIENTO-#####'),
            'company_id' => '168',
            'fecha' => '2026-08-01',
            'fecha_raw' => '2026-08-01',
            'ref' => 'DIARIO',
            'no_ref' => fake()->numerify('REF-#####'),
            'cuenta' => '5101',
            'debito' => 125.50,
            'credito' => 0,
            'descripcion' => 'Movimiento de prueba',
            'id_centro_costo' => 'CC-01',
        ], $attributes));

        DB::table('entradas_diario')
            ->where('id', $entradaDiario->id)
            ->update(['fecha' => $attributes['fecha'] ?? '2026-08-01']);

        return $entradaDiario->refresh();
    }

    private function user(): User
    {
        $user = new User([
            'name' => 'Usuario de prueba',
            'email' => 'movimiento@example.com',
            'must_change_password' => false,
        ]);
        $user->id = 1;
        $user->exists = true;

        return $user;
    }
}
