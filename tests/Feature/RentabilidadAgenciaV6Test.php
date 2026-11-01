<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureViewPermission;
use App\Http\Middleware\ForcePasswordChange;
use App\Models\User;
use App\Services\RentabilidadAgenciaV6Service;
use App\ViewPermissionCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RentabilidadAgenciaV6Test extends TestCase
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

    public function test_calculos_por_mes_y_clasificaciones_independientes(): void
    {
        $this->datos();
        $response = $this->getJson(route('gerencia.rentabilidad-agencia-v6.resumen', $this->filtros()))->assertOk();
        $response->assertJsonPath('ventas', 200)->assertJsonPath('premios_sacados', 90)->assertJsonPath('premios_propios', 120)->assertJsonPath('premios_no_propios', 35)
            ->assertJsonPath('gastos', 87)->assertJsonPath('utilidad_propia', 80)->assertJsonPath('utilidad_general', 45)->assertJsonPath('balance_propio', -7)->assertJsonPath('balance_neto', -42)
            ->assertJsonPath('clasificacion_propia.nombre', 'Para rescatar')->assertJsonPath('clasificacion_neta.nombre', 'No rentable')
            ->assertJsonPath('clasificacion_propia.meses_con_ventas', 2)->assertJsonPath('clasificacion_propia.meses_positivos', 1)
            ->assertJsonPath('meses.0.balance_propio', 25)->assertJsonPath('meses.0.balance_neto', -10)->assertJsonPath('meses.2.ventas', 0)->assertJsonCount(3, 'meses');
        Http::assertNothingSent();
    }

    public function test_consulta_unica_agregada_y_sistemas_y_totales_sin_n_mas_uno(): void
    {
        $this->datos();
        DB::enableQueryLog();
        $resultado = app(RentabilidadAgenciaV6Service::class)->reporte(['anio' => 2026, 'mes_inicio' => 1, 'mes_fin' => 3]);
        $consultas = collect(DB::getQueryLog())->filter(fn (array $fila): bool => str_contains(strtolower($fila['query']), 'union all'));
        DB::disableQueryLog();
        $this->assertCount(1, $consultas);
        $this->assertStringContainsString('group by "terminal", "periodo"', $consultas->first()['query']);
        $this->assertSame(200.0, $resultado['filas']->firstWhere('terminal', '1002')['ventas']);
        $this->assertSame(120.0, $resultado['filas']->firstWhere('terminal', '1003')['ventas']);
        $this->assertSame(520.0, $resultado['totales']['ventas']);
        $this->assertSame(3, $resultado['filas']->count());
    }

    public function test_filtros_anio_completo_y_cuentas_y_detalle(): void
    {
        $this->datos();
        $filtros = $this->filtros();
        $this->getJson(route('gerencia.rentabilidad-agencia-v6.resumen', [...$filtros, 'anio_completo' => 1]))->assertOk()->assertJsonCount(12, 'meses')->assertJsonPath('ventas', 1200)->assertJsonPath('hasta', '2026-12');
        $this->getJson(route('gerencia.rentabilidad-agencia-v6.resumen', [...$filtros, 'productos_filtrados' => 1, 'productos' => ['Recarga']]))->assertOk()->assertJsonPath('ventas', 0)->assertJsonPath('premios_propios', 0)->assertJsonPath('clasificacion_propia.nombre', 'Sin ventas');
        $this->getJson(route('gerencia.rentabilidad-agencia-v6.cuentas', $filtros))->assertOk()->assertJsonPath('total', 87)->assertJsonCount(3, 'movimientos');
        $this->get(route('gerencia.rentabilidad-agencia-v6.detalle', $filtros))->assertOk()->assertSee('detalleTerminalRentabilidadV6')->assertSee('2026-03-31');
        $this->getJson(route('gerencia.rentabilidad-agencia-v6.resumen', [...$filtros, 'terminal' => '99999']))->assertNotFound();
    }

    public function test_vista_columnas_ordenadas_y_rutas_con_mismos_permisos(): void
    {
        $this->datos();
        $this->get(route('gerencia.rentabilidad-agencia-v6'))->assertOk()->assertSee('Año completo');
        $this->get(route('gerencia.rentabilidad-agencia-v6', $this->filtros()))->assertOk()->assertSeeInOrder(['Terminal</th>', 'Agencia (nombre)</th>', 'Ventas</th>', 'Premios sacados</th>', 'Premios pagados propios</th>', 'Premios pagados no propios</th>', 'Utilidad propia</th>', 'Utilidad general</th>', 'Gastos</th>', 'Balance propio</th>', 'Balance neto</th>', 'Clasificación propia</th>', 'Clasificación neta</th>', 'Acción</th>'], false);
        $this->assertSame(Route::getRoutes()->getByName('gerencia.rentabilidad-agencia-v5')->gatherMiddleware(), Route::getRoutes()->getByName('gerencia.rentabilidad-agencia-v6')->gatherMiddleware());
        $this->assertSame('gerencia.rentabilidad_de_agencia.view', app(ViewPermissionCatalog::class)->items()->firstWhere('path', '/gerencia/rentabilidad-agencia-v6')['permission']);
        $this->withMiddleware(EnsureViewPermission::class);
        $this->usuario(false);
        $this->getJson(route('gerencia.rentabilidad-agencia-v6.resumen', $this->filtros()))->assertForbidden();
    }

    public function test_valida_rango_y_terminal(): void
    {
        foreach ([['mes_fin' => 0], ['mes_inicio' => 13], ['mes_inicio' => 4, 'mes_fin' => 3], ['anio' => 10000], ['terminal' => null], ['productos' => ['Invalido']]] as $cambio) {
            $this->getJson(route('gerencia.rentabilidad-agencia-v6.resumen', [...$this->filtros(), ...$cambio]))->assertUnprocessable();
        }
        $this->getJson(route('gerencia.rentabilidad-agencia-v6.resumen', ['terminal' => '1001']))->assertUnprocessable();
    }

    public function test_balance_cero_con_centavos_y_r20_diario_concilia_con_mensual(): void
    {
        $this->datos();
        DB::table('rentabilidad_agencia_r20')->update(['monto' => 0.03]);
        DB::table('vt_usuarios_net')->where('agencia_id', '1002')->update(['monto' => 0.33]);
        DB::table('pagos_misma_empresa_net')->insert(['agencia_id' => '1002', 'pagado_agencia_id' => '1002', 'fecha' => '2026-01-01', 'monto' => 0.3]);
        $filtros = [...$this->filtros(), 'terminal' => '1002', 'empresa' => 'Negosur', 'mes_fin' => 1];
        $servicio = app(RentabilidadAgenciaV6Service::class);
        $mensual = $servicio->reporte($filtros)['filas']->first();
        $diario = $servicio->reporte($filtros, true)['filas']->first();
        $this->assertSame(0.0, $mensual['balance_propio']);
        $this->assertSame(0, $mensual['clasificacion_propia']['meses_positivos']);
        $this->assertSame($mensual['gastos'], $diario['gastos']);
        $this->assertSame($mensual['balance_neto'], $diario['balance_neto']);
    }

    public function test_informe_ejecutivo_genera_pdf_del_rango(): void
    {
        $this->datos();
        $response = $this->get(route('gerencia.rentabilidad-agencia-v6.informe-ejecutivo', $this->filtros()))->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('rentabilidad_agencia_v6.pdf', $response->headers->get('Content-Disposition'));
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()));
    }

    public function test_pdf_ejecutivo_permanece_compacto_con_miles_de_terminales(): void
    {
        $this->datos();
        $resultado = app(RentabilidadAgenciaV6Service::class)->reporte($this->filtros());
        $resultado['filas'] = collect(array_fill(0, 3000, $resultado['filas']->first()));
        $resultado['resumen_clasificaciones'] = [
            'total' => 3000, 'con_ventas' => 3000, 'sin_ventas' => 0,
            'categorias' => array_map(fn (array $categoria): array => [...$categoria, 'propia' => $categoria['nombre'] === 'Para rescatar' ? 3000 : 0, 'neta' => $categoria['nombre'] === 'No rentable' ? 3000 : 0], $resultado['resumen_clasificaciones']['categorias']),
        ];
        $resultado['totales'] = array_map(fn (float $importe): float => $importe * 3000, $resultado['totales']);
        $this->mock(RentabilidadAgenciaV6Service::class, function ($mock) use ($resultado): void {
            $mock->shouldReceive('productos')->once()->andReturn(RentabilidadAgenciaV6Service::PRODUCTOS);
            $mock->shouldReceive('reporte')->once()->andReturn($resultado);
        });
        $response = $this->get(route('gerencia.rentabilidad-agencia-v6.informe-ejecutivo', $this->filtros()))->assertOk();
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()));
        unset($resultado['filas']);
        $filtros = $this->filtros();
        $productos = RentabilidadAgenciaV6Service::PRODUCTOS;
        $html = view('gerencia.rentabilidad-agencia-v6-pdf', compact('resultado', 'filtros', 'productos'))->render();
        $this->assertStringContainsString('Terminales evaluadas<strong>3,000', $html);
        $this->assertStringContainsString('RD$ 600,000.00', $html);
        $this->assertLessThan(20000, strlen($html));
    }

    public function test_resumen_cuenta_terminales_y_categorias_de_ambas_clasificaciones(): void
    {
        $this->datos();
        DB::table('agencias')->insert(['terminal' => '1004', 'empresa' => 'Negosur', 'sistema' => 'LOTONET', 'nombre_agencia' => 'Sin ventas']);
        $filtros = ['anio' => 2026, 'mes_inicio' => 1, 'mes_fin' => 3];
        $resultado = app(RentabilidadAgenciaV6Service::class)->reporte($filtros);
        $resumen = $resultado['resumen_clasificaciones'];
        $this->assertSame(4, $resumen['total']);
        $this->assertSame(3, $resumen['con_ventas']);
        $this->assertSame(1, $resumen['sin_ventas']);
        $categorias = collect($resumen['categorias'])->keyBy('nombre');
        foreach (['Rentable' => [2, 2], 'Buena' => [0, 0], 'Para rescatar' => [1, 0], 'No rentable' => [0, 1], 'Sin ventas' => [1, 1]] as $nombre => [$propia, $neta]) {
            $this->assertSame($propia, $categorias[$nombre]['propia']);
            $this->assertSame($neta, $categorias[$nombre]['neta']);
        }
        $this->assertSame(4, $categorias->sum('propia'));
        $this->assertSame(4, $categorias->sum('neta'));
        $this->get(route('gerencia.rentabilidad-agencia-v6', $filtros))->assertOk()
            ->assertSee('Resumen de terminales')->assertSee('Terminales evaluadas')
            ->assertViewHas('resultado', fn (array $datos): bool => $datos['resumen_clasificaciones'] === $resumen);
        $productos = RentabilidadAgenciaV6Service::PRODUCTOS;
        $htmlPdf = view('gerencia.rentabilidad-agencia-v6-pdf', compact('resultado', 'filtros', 'productos'))->render();
        $this->assertStringContainsString('Terminales evaluadas<strong>4', $htmlPdf);
        $this->assertStringContainsString('Clasificación propia', $htmlPdf);
        $this->assertStringContainsString('Clasificación neta', $htmlPdf);
        $this->assertStringContainsString('Sin ventas', $htmlPdf);
        $this->assertStringContainsString('Resultados del período', $htmlPdf);
        $this->assertStringContainsString('Empresa: Todas las empresas', $htmlPdf);
        $this->assertStringContainsString('Productos: Tradicional, No Tradicional, Recarga', $htmlPdf);
        $this->assertStringNotContainsString('class="detail-section"', $htmlPdf);
        $resultado['totales']['balance_propio'] = 0;
        $resultado['totales']['balance_neto'] = 0;
        $htmlCero = view('gerencia.rentabilidad-agencia-v6-pdf', compact('resultado', 'filtros', 'productos'))->render();
        $this->assertStringNotContainsString('class="negativo">RD$ 0.00', $htmlCero);
        $filtrado = app(RentabilidadAgenciaV6Service::class)->reporte([...$filtros, 'empresa' => 'Negosur'])['resumen_clasificaciones'];
        $this->assertSame(2, $filtrado['total']);
        $this->assertSame(1, $filtrado['con_ventas']);
        $vacio = app(RentabilidadAgenciaV6Service::class)->reporte([...$filtros, 'empresa' => 'Inexistente'])['resumen_clasificaciones'];
        $this->assertSame(0, $vacio['total']);
        $this->assertSame(0, array_sum(array_column($vacio['categorias'], 'propia')));
        $this->assertSame(0, array_sum(array_column($vacio['categorias'], 'neta')));
    }

    public function test_todos_los_productos_incluye_ventas_sin_tipo_igual_que_v5(): void
    {
        $this->datos();
        DB::table('vt_usuarios_bet')->insert(['agencia_id' => '1001', 'fecha' => '2026-01-01', 'monto' => 11]);
        $this->getJson(route('gerencia.rentabilidad-agencia-v6.resumen', $this->filtros()))->assertOk()->assertJsonPath('ventas', 211);
        $this->getJson(route('gerencia.rentabilidad-agencia-v6.resumen', [...$this->filtros(), 'productos' => ['Tradicional']]))->assertOk()->assertJsonPath('ventas', 200)->assertJsonPath('premios_propios', 120);
    }

    public function test_resumen_ajax_y_filtros_en_javascript(): void
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
let inicio, opciones, ultimaUrl, alerta, cargando = false;
const clasificacion = {nombre: 'Rentable', color: 'success', meses_positivos: 1, meses_con_ventas: 1, porcentaje: 100};
const fila = {terminal: '001', nombre: '<Agencia>', empresa: 'Empresa', clasificacion_propia: clasificacion, clasificacion_neta: clasificacion};
const datos = {...fila, meses: [{mes: '2026-01', ventas: 100, premios_propios: 1, premios_no_propios: 2, gastos: 10, balance_propio: 89, balance_neto: -2}]};
global.document = {getElementById: elemento, addEventListener(tipo, fn){inicio = fn;}, body: {appendChild(){}}, createElement(){return {set textContent(v){this.innerHTML = v.replaceAll('<', '&lt;').replaceAll('>', '&gt;');}};}};
global.window = {rentabilidadV6: {datos: [fila], filtros: {anio: 2026, mes_inicio: 1, mes_fin: 3, productos: ['Tradicional']}, urls: {resumen: 'http://localhost/resumen'}}};
window.Swal = {fire(configuracion){alerta = configuracion; configuracion.didOpen();}, showLoading(){cargando = true;}};
global.$ = () => ({DataTable(o){opciones = o; return {row(){return {data(){return fila;}}}};}});
$.fn = {dataTable: {render: {text(){return x => x;}}}};
global.bootstrap = {Modal: class {show(){}}};
global.fetch = async url => {ultimaUrl = url; return {ok: true, redirected: false, async json(){return datos;}};};
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
inicio();
elemento('filtrosRentabilidadV6').listeners.submit();
assert.equal(alerta.title, 'Consultando datos...');
assert.equal(alerta.allowOutsideClick, false);
assert.equal(alerta.allowEscapeKey, false);
assert.equal(alerta.showConfirmButton, false);
assert.equal(cargando, true);
assert.equal(opciones.columns.length, 14);
assert.equal(opciones.deferRender, true);
assert.equal(opciones.pageLength, 25);
elemento('anioCompletoV6').checked = true;
elemento('anioCompletoV6').listeners.change();
assert.equal(elemento('mes_finV6').value, '12');
elemento('mes_finV6').listeners.change();
assert.equal(elemento('anioCompletoV6').checked, false);
elemento('alcanceV6').value = 'terminal';
elemento('alcanceV6').listeners.change();
assert.equal(elemento('terminalV6').required, true);
const boton = {dataset: {accion: 'resumen'}, textContent: 'Resumen', closest(){return {};}};
(async () => {
    await elemento('tablaRentabilidadV6').listeners.click({target: {closest(){return boton;}}});
    assert.equal(ultimaUrl.searchParams.get('terminal'), '001');
    assert.equal(ultimaUrl.searchParams.get('mes_fin'), '3');
    assert.equal(ultimaUrl.searchParams.get('productos[]'), 'Tradicional');
    const html = elemento('contenidoModalV6').innerHTML;
    assert.ok(html.includes('text-success'));
    assert.ok(html.includes('text-danger'));
    assert.ok(html.includes('1 / 1 meses positivos'));
    assert.ok(html.includes('2026-01'));
    assert.ok(html.includes('<th>Pagados propios</th>'));
    assert.ok(html.includes('<th>Pagados no propios</th>'));
    assert.ok(!html.includes('<th>Premios propios</th>'));
    assert.ok(!html.includes('<th>Premios no propios</th>'));
    window.rentabilidadV6.datos = [];
    opciones = undefined;
    alerta = undefined;
    inicio();
    assert.equal(opciones, undefined);
    elemento('filtrosRentabilidadV6').listeners.submit();
    assert.equal(alerta.title, 'Consultando datos...');
    delete window.Swal;
    assert.doesNotThrow(() => elemento('filtrosRentabilidadV6').listeners.submit());
})().catch(error => {console.error(error); process.exitCode = 1;});
JS;
        $proceso = new Process(['node', '-e', $codigo, public_path('js/pages/rentabilidad-agencia-v6.js')]);
        $proceso->run();
        $this->assertTrue($proceso->isSuccessful(), $proceso->getErrorOutput());
    }
}
