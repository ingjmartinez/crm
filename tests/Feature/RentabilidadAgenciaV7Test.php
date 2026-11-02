<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureViewPermission;
use App\Http\Middleware\ForcePasswordChange;
use App\Models\User;
use App\Services\RentabilidadAgenciaV7Service;
use App\ViewPermissionCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RentabilidadAgenciaV7Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([EnsureViewPermission::class, ForcePasswordChange::class]);
        Http::preventStrayRequests();
        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal');
            $table->string('sistema')->nullable();
            $table->string('nombre_agencia')->nullable();
            $table->string('agencia')->nullable();
            $table->string('empresa')->nullable();
            $table->integer('estatus')->default(1);
        });
        foreach (['vt_usuarios_bet', 'vt_usuarios_net', 'premios_bet', 'premios_net', 'pagos_misma_empresa_bet', 'pagos_porotra_empresa_bet', 'pagos_misma_empresa_net', 'pagos_porotra_empresa_net', 'pagos_aotra_empresa_bet', 'pagos_aotra_empresa_net'] as $tabla) {
            Schema::create($tabla, function (Blueprint $table): void {
                $table->id();
                $table->string('agencia_id');
                $table->string('pagado_agencia_id')->nullable();
                $table->date('fecha');
                $table->decimal('monto', 15, 2);
                $table->string('tipo')->nullable();
                $table->string('producto_id')->nullable();
            });
        }
        Schema::create('catalogo_juegos', function (Blueprint $table): void {
            $table->id();
            $table->string('producto_id');
            $table->string('tipo');
        });
        Schema::create('cuentas_contables', function (Blueprint $table): void {
            $table->id();
            $table->string('cuenta');
            $table->string('descripcion')->nullable();
            $table->string('tipo');
        });
        Schema::create('entradas_diario', function (Blueprint $table): void {
            $table->id();
            $table->string('id_viejo');
            $table->date('fecha');
            $table->string('cuenta');
            $table->decimal('debito', 15, 2);
            $table->decimal('credito', 15, 2);
        });
        Schema::create('rentabilidad_agencia_r20', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal');
            $table->string('mes');
            $table->decimal('monto', 15, 2);
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();
        });
        Schema::create('clasificacion_cuentas', function (Blueprint $table): void {
            $table->id();
            $table->string('cuenta');
            $table->string('clasificacion')->nullable();
            $table->boolean('activo');
            $table->timestamps();
        });
        config(['clasificacion_cuentas.cuentas' => ['5001' => 'FIJO', '6001' => 'VARIABLE']]);

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
        });
        Gate::before(fn (): bool => false);
        $this->usuario(true);
    }

    private function usuario(bool $permitido): void
    {
        $usuario = \Mockery::mock(User::class)->makePartial();
        $usuario->id = 1;
        $usuario->name = 'Prueba';
        $usuario->email = 'prueba@example.test';
        $usuario->shouldReceive('can')->andReturn($permitido);
        $usuario->shouldReceive('hasRole')->andReturnFalse();
        $usuario->setRelation('viewPermissions', collect());
        $this->actingAs($usuario);
    }

    private function datos(): void
    {
        DB::table('agencias')->insert([
            ['terminal' => '1001', 'empresa' => 'Grupo Joselito', 'sistema' => 'LOTOBET', 'nombre_agencia' => 'Agencia A'],
            ['terminal' => '1002', 'empresa' => 'Negosur', 'sistema' => 'LOTONET', 'nombre_agencia' => 'Agencia B'],
            ['terminal' => '1003', 'empresa' => 'Grupo Joselito', 'sistema' => 'MIXTO', 'nombre_agencia' => 'Agencia C'],
        ]);
        DB::table('catalogo_juegos')->insert([['producto_id' => '1', 'tipo' => 'Tradicional'], ['producto_id' => '2', 'tipo' => 'No Tradicional']]);
        foreach ([['1001', '2026-01-01', 100], ['1001', '2026-02-01', 100], ['1001', '2026-04-01', 1000], ['1002', '2026-01-01', 999], ['1003', '2026-01-01', 50]] as [$terminal, $fecha, $monto]) {
            DB::table('vt_usuarios_bet')->insert(['agencia_id' => $terminal, 'fecha' => $fecha, 'monto' => $monto, 'tipo' => 'Tradicional']);
        }
        DB::table('vt_usuarios_net')->insert([['agencia_id' => '1002', 'fecha' => '2026-01-01', 'monto' => 200, 'tipo' => 'Tradicional'], ['agencia_id' => '1003', 'fecha' => '2026-01-01', 'monto' => 70, 'tipo' => 'Tradicional']]);
        DB::table('premios_bet')->insert(['agencia_id' => '1001', 'fecha' => '2026-01-01', 'monto' => 90, 'producto_id' => '1']);
        foreach ([['1001', '1001', '2026-01-01', 20], ['1001', '999', '2026-01-01', 30], ['999', '1001', '2026-01-01', 60], ['1001', null, '2026-01-01', 900], ['1001', '1001', '2026-02-01', 100]] as [$pagadora, $origen, $fecha, $monto]) {
            DB::table('pagos_misma_empresa_bet')->insert(['agencia_id' => $pagadora, 'pagado_agencia_id' => $origen, 'fecha' => $fecha, 'monto' => $monto, 'producto_id' => '1']);
        }
        DB::table('pagos_aotra_empresa_bet')->insert(['agencia_id' => '1001', 'fecha' => '2026-01-01', 'monto' => 5, 'producto_id' => '1']);
        DB::table('pagos_porotra_empresa_bet')->insert(['agencia_id' => '1001', 'fecha' => '2026-01-01', 'monto' => 80, 'producto_id' => '1']);
        DB::table('cuentas_contables')->insert([['cuenta' => '5001', 'tipo' => 'Costo'], ['cuenta' => '6001', 'tipo' => 'Gasto'], ['cuenta' => '9999', 'tipo' => 'Gasto']]);
        foreach ([['2026-01-01', '5001', 10, 0], ['2026-01-01', '6001', 40, 5], ['2026-02-01', '6001', 5, 0], ['2026-03-01', '6001', 7, 0], ['2026-01-01', '9999', 999, 0]] as [$fecha, $cuenta, $debito, $credito]) {
            DB::table('entradas_diario')->insert(['id_viejo' => '1001', 'fecha' => $fecha, 'cuenta' => $cuenta, 'debito' => $debito, 'credito' => $credito]);
        }
        DB::table('rentabilidad_agencia_r20')->insert(['terminal' => '*', 'mes' => 'GLOBAL', 'monto' => 10]);
    }

    private function filtros(): array
    {
        return ['anio' => 2026, 'mes_inicio' => 1, 'mes_fin' => 3, 'terminal' => '1001', 'empresa' => 'Grupo Joselito', 'alcance' => 'terminal'];
    }

    public function test_calcula_con_premios_generados_y_conserva_los_gastos_de_v6(): void
    {
        $this->datos();
        $response = $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', $this->filtros()))->assertOk();
        $response->assertJsonPath('ventas', 200)->assertJsonPath('premios_sacados', 90)
            ->assertJsonPath('utilidad', 110)->assertJsonPath('gastos', 87)->assertJsonPath('balance_general', 23)
            ->assertJsonPath('meses.0.utilidad', 10)->assertJsonPath('meses.0.balance_general', -45)
            ->assertJsonPath('meses.1.balance_general', 85)->assertJsonPath('meses.2.balance_general', -17)
            ->assertJsonPath('clasificacion.nombre', 'Para rescatar')
            ->assertJsonPath('clasificacion.meses_positivos', 1)->assertJsonPath('clasificacion.meses_con_ventas', 2)
            ->assertJsonCount(3, 'meses')->assertJsonMissingPath('premios_propios')->assertJsonMissingPath('clasificacion_neta');
        $original = app(\App\Services\RentabilidadAgenciaV6Service::class)->reporte($this->filtros())['filas']->first();
        $this->assertSame($original['gastos'], (float) $response->json('gastos'));
        Http::assertNothingSent();
    }

    public function test_agrega_una_consulta_por_terminal_y_mes_sin_leer_pagos(): void
    {
        $this->datos();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $resultado = app(RentabilidadAgenciaV7Service::class)->reporte(['anio' => 2026, 'mes_inicio' => 1, 'mes_fin' => 3]);
        $consultas = collect(DB::getQueryLog())->filter(fn (array $consulta): bool => str_contains(strtolower($consulta['query']), 'union all'));
        DB::disableQueryLog();
        $this->assertCount(1, $consultas);
        $this->assertStringNotContainsString('pagos_', $consultas->first()['query']);
        $this->assertStringContainsString('group by "terminal", "periodo"', $consultas->first()['query']);
        $this->assertSame(['ventas' => 520.0, 'premios_sacados' => 90.0, 'utilidad' => 430.0, 'gastos' => 147.0, 'balance_general' => 283.0], $resultado['totales']);
        $this->assertSame(200.0, $resultado['filas']->firstWhere('terminal', '1002')['ventas']);
        $this->assertSame(120.0, $resultado['filas']->firstWhere('terminal', '1003')['ventas']);
        $this->assertSame(3, $resultado['resumen_clasificaciones']['total']);
        $categorias = collect($resultado['resumen_clasificaciones']['categorias'])->keyBy('nombre');
        $this->assertSame(2, $categorias['Rentable']['cantidad']);
        $this->assertSame(1, $categorias['Para rescatar']['cantidad']);
    }

    public function test_filtros_anuales_de_productos_empresa_y_terminal(): void
    {
        $this->datos();
        $filtros = $this->filtros();
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', [...$filtros, 'anio_completo' => 1]))->assertOk()->assertJsonCount(12, 'meses')->assertJsonPath('ventas', 1200)->assertJsonPath('gastos', 177)->assertJsonPath('balance_general', 933);
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', [...$filtros, 'productos_filtrados' => 1, 'productos' => ['Recarga']]))->assertOk()->assertJsonPath('ventas', 0)->assertJsonPath('premios_sacados', 0)->assertJsonPath('balance_general', -87)->assertJsonPath('clasificacion.nombre', 'Sin ventas');
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', [...$filtros, 'productos' => ['Tradicional']]))->assertOk()->assertJsonPath('premios_sacados', 90);
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', [...$filtros, 'empresa' => 'Negosur']))->assertNotFound();
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', [...$filtros, 'terminal' => '99999']))->assertNotFound();
    }

    public static function categoriasPorMesesPositivos(): array
    {
        return [[9, 'Rentable'], [7, 'Buena'], [6, 'Para rescatar'], [5, 'Para rescatar'], [4, 'No rentable'], [2, 'No rentable'], [0, 'No rentable']];
    }

    #[DataProvider('categoriasPorMesesPositivos')]
    public function test_clasifica_el_balance_general_ignorando_meses_sin_ventas(int $positivos, string $categoria): void
    {
        $this->datos();
        DB::table('agencias')->insert(['terminal' => '9001', 'empresa' => 'Grupo Joselito', 'sistema' => 'LOTOBET', 'nombre_agencia' => 'Clasificacion']);
        foreach (range(1, 9) as $mes) {
            $fecha = sprintf('2026-%02d-01', $mes);
            DB::table('vt_usuarios_bet')->insert(['agencia_id' => '9001', 'fecha' => $fecha, 'monto' => 100]);
            DB::table('premios_bet')->insert(['agencia_id' => '9001', 'fecha' => $fecha, 'monto' => $mes <= $positivos ? 20 : 90]);
        }
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', [...$this->filtros(), 'terminal' => '9001', 'mes_fin' => 12]))->assertOk()
            ->assertJsonPath('clasificacion.nombre', $categoria)->assertJsonPath('clasificacion.meses_positivos', $positivos)
            ->assertJsonPath('clasificacion.meses_con_ventas', 9)->assertJsonPath('balance_general', $positivos * 70 - 30);
    }

    public function test_cache_ejecutiva_aisla_usuario_filtros_y_expira(): void
    {
        $this->datos();
        $servicio = app(RentabilidadAgenciaV7Service::class);
        $filtros = $this->filtros();
        $this->assertSame(23.0, $servicio->resumenEjecutivo($filtros, '1')['totales']['balance_general']);
        DB::table('premios_bet')->where('agencia_id', '1001')->update(['monto' => 900]);
        $this->assertSame(23.0, $servicio->resumenEjecutivo($filtros, '1')['totales']['balance_general']);
        $this->assertSame(-787.0, $servicio->resumenEjecutivo($filtros, '2')['totales']['balance_general']);
        $this->assertSame(0.0, $servicio->resumenEjecutivo([...$filtros, 'empresa' => 'Inexistente'], '1')['totales']['ventas']);
        $this->assertSame(0.0, $servicio->resumenEjecutivo([...$filtros, 'productos_filtrados' => 1, 'productos' => []], '1')['totales']['ventas']);
        $this->travel(11)->minutes();
        $this->assertSame(-787.0, $servicio->resumenEjecutivo($filtros, '1')['totales']['balance_general']);
        $this->travelBack();
    }

    public function test_cero_con_centavos_no_es_positivo_y_el_diario_concilia(): void
    {
        $this->datos();
        DB::table('rentabilidad_agencia_r20')->update(['monto' => 0.03]);
        DB::table('vt_usuarios_net')->where('agencia_id', '1002')->update(['monto' => 0.33]);
        DB::table('premios_net')->insert(['agencia_id' => '1002', 'fecha' => '2026-01-01', 'monto' => 0.3]);
        $filtros = [...$this->filtros(), 'terminal' => '1002', 'empresa' => 'Negosur', 'mes_fin' => 1];
        $servicio = app(RentabilidadAgenciaV7Service::class);
        $mensual = $servicio->reporte($filtros)['filas']->first();
        $diario = $servicio->reporte($filtros, true)['filas']->first();
        $this->assertSame(0.0, $mensual['balance_general']);
        $this->assertSame(0, $mensual['clasificacion']['meses_positivos']);
        $this->assertSame($mensual['balance_general'], $diario['balance_general']);
        $this->get(route('gerencia.rentabilidad-agencia-v7.detalle', $filtros))->assertOk()->assertSee('detalleTerminalRentabilidadV7')->assertSee('Balance general')->assertDontSee('Balance neto');
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.cuentas', $this->filtros()))->assertOk()->assertJsonPath('total', 57.09);
    }

    public function test_vista_columnas_y_permisos_iguales_a_v6(): void
    {
        $this->datos();
        $this->get(route('gerencia.rentabilidad-agencia-v7'))->assertOk()->assertSee('Año completo');
        $this->get(route('gerencia.rentabilidad-agencia-v7', $this->filtros()))->assertOk()
            ->assertSeeInOrder(['Terminal</th>', 'Agencia (nombre)</th>', 'Ventas</th>', 'Premios sacados</th>', 'Utilidad</th>', 'Gastos</th>', 'Balance general</th>', 'Clasificación</th>', 'Acción</th>'], false)
            ->assertSee('categoriaTablaV7')->assertDontSee('clasificacionTablaV7')->assertDontSee('Premios pagados propios');
        foreach (['', '.resumen', '.detalle', '.cuentas', '.informe-ejecutivo'] as $sufijo) {
            $this->assertSame(Route::getRoutes()->getByName('gerencia.rentabilidad-agencia-v6'.$sufijo)->gatherMiddleware(), Route::getRoutes()->getByName('gerencia.rentabilidad-agencia-v7'.$sufijo)->gatherMiddleware());
        }
        $this->assertSame('gerencia.rentabilidad_de_agencia.view', app(ViewPermissionCatalog::class)->items()->firstWhere('path', '/gerencia/rentabilidad-agencia-v7')['permission']);
        $this->withMiddleware(EnsureViewPermission::class);
        $this->usuario(false);
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', $this->filtros()))->assertForbidden();
    }

    public function test_valida_rangos_y_parametros_de_los_modales(): void
    {
        foreach ([['mes_inicio' => 4], ['anio' => 10000], ['mes_fin' => 0], ['terminal' => null], ['productos' => ['Invalido']]] as $cambio) {
            $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', [...$this->filtros(), ...$cambio]))->assertUnprocessable();
        }
        $this->getJson(route('gerencia.rentabilidad-agencia-v7.resumen', ['terminal' => '1001']))->assertUnprocessable();
    }

    public function test_pdf_reutiliza_consulta_y_su_cache_no_se_mezcla_con_v6(): void
    {
        $this->datos();
        $this->get(route('gerencia.rentabilidad-agencia-v7', $this->filtros()))->assertOk();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->get(route('gerencia.rentabilidad-agencia-v7.informe-ejecutivo', $this->filtros()))->assertOk();
        $consultas = collect(DB::getQueryLog())->filter(fn (array $consulta): bool => str_contains(strtolower($consulta['query']), 'union all'));
        DB::disableQueryLog();
        $this->assertCount(0, $consultas);
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('rentabilidad_agencia_v7.pdf', $response->headers->get('Content-Disposition'));
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()));
        $resultado = app(RentabilidadAgenciaV7Service::class)->resumenEjecutivo($this->filtros(), '1');
        $this->assertArrayNotHasKey('filas', $resultado);
        $this->assertSame(23.0, $resultado['totales']['balance_general']);
        $v6 = app(\App\Services\RentabilidadAgenciaV6Service::class)->resumenEjecutivo($this->filtros(), '1');
        $this->assertSame(-42.0, $v6['totales']['balance_neto']);
        $this->assertArrayNotHasKey('balance_general', $v6['totales']);
        $filtros = $this->filtros();
        $productos = RentabilidadAgenciaV7Service::PRODUCTOS;
        $html = view('gerencia.rentabilidad-agencia-v7-pdf', compact('resultado', 'filtros', 'productos'))->render();
        $this->assertStringContainsString('Balance general', $html);
        $this->assertStringNotContainsString('Pagados propios', $html);
        $this->assertStringNotContainsString('Clasificación neta', $html);
    }

    public function test_filtro_local_totales_y_resumen_ajax_en_javascript(): void
    {
        $codigo = <<<'JS'
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const elementos = new Map();
function elemento(id) {
    if (!elementos.has(id)) elementos.set(id, {value: '', checked: false, listeners: {}, classList: {add(){}, remove(){}, contains(){return true;}}, addEventListener(tipo, fn){this.listeners[tipo] = fn;}, textContent: '', innerHTML: ''});
    return elementos.get(id);
}
let inicio, opciones, alerta, urlConsultada, cargando = false, envios = 0, prevenidos = 0, filtro = '';
const eventosVentana = {};
const clasificacion = {nombre: 'Rentable', color: 'success', meses_positivos: 1, meses_con_ventas: 1, porcentaje: 100};
const fila = {terminal: '001', nombre: '<Agencia>', empresa: 'Empresa', ventas: 100, premios_sacados: 50, utilidad: 50, gastos: 10, balance_general: 40, clasificacion};
const otraFila = {...fila, terminal: '002', ventas: 200, premios_sacados: 300, utilidad: -100, gastos: 20, balance_general: -120, clasificacion: {...clasificacion, nombre: 'No rentable', color: 'danger'}};
const sinVentas = {...fila, terminal: '003', ventas: 0, premios_sacados: 0, utilidad: 0, gastos: 10, balance_general: -10, clasificacion: {...clasificacion, nombre: 'Sin ventas', color: 'secondary'}};
const datos = {...fila, meses: [{...fila, mes: '2026-01'}, {...sinVentas, mes: '2026-02'}]};
global.document = {getElementById: elemento, addEventListener(tipo, fn){inicio = fn;}, body: {appendChild(){}}, createElement(){return {set textContent(v){this.innerHTML = v.replaceAll('<', '&lt;').replaceAll('>', '&gt;');}};}};
global.window = {rentabilidadV7: {datos: [fila, otraFila, sinVentas], filtros: {anio: 2026, mes_inicio: 1, mes_fin: 3, productos: ['Tradicional']}, urls: {resumen: 'http://localhost/v7/resumen'}}, addEventListener(tipo, fn){eventosVentana[tipo] = fn;}};
window.Swal = {fire(configuracion){alerta = configuracion; configuracion.didOpen();}, showLoading(){cargando = true;}, close(){alerta = undefined;}};
global.HTMLFormElement = class {submit(){assert.equal(this, elemento('filtrosRentabilidadV7')); envios++;}};
const filasAplicadas = () => window.rentabilidadV7.datos.filter(fila => !filtro || new RegExp(filtro).test(fila.clasificacion.nombre));
const apiTabla = {
    row(){return {data(){return fila;}};},
    rows(opciones){assert.equal(opciones.search, 'applied'); return {data(){return {toArray: filasAplicadas};}};},
    column(indice){return {search(valor, regex, smart){assert.equal(indice, 7); assert.equal(regex, true); assert.equal(smart, false); filtro = valor; return apiTabla;}, footer(){return elemento('pie' + indice);}};},
    draw(){opciones.footerCallback.call({api(){return apiTabla;}}); return apiTabla;},
};
global.$ = () => ({DataTable(o){opciones = o; return apiTabla;}});
$.fn = {dataTable: {render: {text(){return x => x;}}, util: {escapeRegex(valor){return valor.replace(/[.*+?^$\{\}()|[\]\\]/g, '\\$&');}}}};
global.bootstrap = {Modal: class {show(){}}};
global.fetch = async url => {urlConsultada = url; return {ok: true, redirected: false, async json(){return datos;}};};
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
inicio();
assert.equal(opciones.columns.length, 9);
assert.deepEqual(opciones.columns.slice(2, 8).map(c => c.data), ['ventas', 'premios_sacados', 'utilidad', 'gastos', 'balance_general', 'clasificacion']);
assert.equal(opciones.deferRender, true);
assert.equal(opciones.pageLength, 25);
apiTabla.draw();
assert.equal(elemento('pie2').textContent, 'RD$ 300.00');
assert.equal(elemento('pie6').textContent, 'RD$ -90.00');
elemento('categoriaTablaV7').value = 'Rentable';
elemento('categoriaTablaV7').listeners.change();
assert.deepEqual(filasAplicadas().map(fila => fila.terminal), ['001']);
assert.equal(elemento('pie6').textContent, 'RD$ 40.00');
elemento('categoriaTablaV7').value = 'Buena';
elemento('categoriaTablaV7').listeners.change();
assert.equal(elemento('pie1').textContent, '0 terminales');
assert.equal(elemento('pie2').textContent, 'RD$ 0.00');
elemento('categoriaTablaV7').value = 'Sin ventas';
elemento('categoriaTablaV7').listeners.change();
assert.deepEqual(filasAplicadas().map(fila => fila.terminal), ['003']);
elemento('categoriaTablaV7').value = '';
elemento('categoriaTablaV7').listeners.change();
assert.equal(filasAplicadas().length, 3);
assert.equal(urlConsultada, undefined);
elemento('anioCompletoV7').checked = true;
elemento('anioCompletoV7').listeners.change();
assert.equal(elemento('mes_finV7').value, '12');
elemento('mes_finV7').listeners.change();
assert.equal(elemento('anioCompletoV7').checked, false);
elemento('alcanceV7').value = 'terminal';
elemento('alcanceV7').listeners.change();
assert.equal(elemento('terminalV7').required, true);
const eventoSubmit = () => ({preventDefault(){prevenidos++;}});
elemento('filtrosRentabilidadV7').listeners.submit(eventoSubmit());
elemento('filtrosRentabilidadV7').listeners.submit(eventoSubmit());
assert.equal(alerta.title, 'Consultando datos...');
assert.equal(cargando, true);
assert.equal(envios, 0);
assert.equal(prevenidos, 2);
(async () => {
    await new Promise(resolve => setTimeout(resolve, 80));
    assert.equal(envios, 1);
    eventosVentana.pageshow({persisted: true});
    assert.equal(alerta, undefined);
    const boton = {dataset: {accion: 'resumen'}, textContent: 'Resumen', closest(){return {};}};
    await elemento('tablaRentabilidadV7').listeners.click({target: {closest(){return boton;}}});
    assert.equal(urlConsultada.pathname, '/v7/resumen');
    assert.equal(urlConsultada.searchParams.get('terminal'), '001');
    assert.equal(urlConsultada.searchParams.get('mes_fin'), '3');
    assert.equal(urlConsultada.searchParams.get('productos[]'), 'Tradicional');
    const html = elemento('contenidoModalV7').innerHTML;
    assert.ok(html.includes('<th>Premios sacados</th>'));
    assert.ok(html.includes('<th>Utilidad</th>'));
    assert.ok(html.includes('<th>Balance general</th>'));
    assert.ok(html.includes('text-success'));
    assert.ok(html.includes('text-danger'));
    assert.ok(!html.includes('Pagados propios'));
    assert.ok(!html.includes('Balance neto'));
    window.rentabilidadV7.datos = [];
    opciones = undefined;
    inicio();
    assert.equal(opciones, undefined);
    delete window.Swal;
    const previos = prevenidos;
    elemento('filtrosRentabilidadV7').listeners.submit(eventoSubmit());
    assert.equal(prevenidos, previos);
})().catch(error => {console.error(error); process.exitCode = 1;});
JS;
        $proceso = new Process(['node', '-e', $codigo, public_path('js/pages/rentabilidad-agencia-v7.js')]);
        $proceso->setTimeout(15)->run();
        $this->assertTrue($proceso->isSuccessful(), $proceso->getErrorOutput());
    }
}
