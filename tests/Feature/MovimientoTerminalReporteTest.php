<?php

namespace Tests\Feature;

use App\Models\Agencia;
use App\Models\User;
use App\ViewPermissionCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MovimientoTerminalReporteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();

        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('agencia');
            $table->string('nombre_agencia');
            $table->string('terminal');
            $table->string('empresa');
            $table->timestamps();
        });
        Schema::create('empleados', function (Blueprint $table): void {
            $table->id();
            $table->string('empleadoid');
            $table->string('cedula');
            $table->string('nombres');
            $table->string('apellidos');
            $table->date('fechasalida')->nullable();
        });
        foreach (['nomina_domingo_ventas', 'ponche_por_cedula_ventas'] as $tabla) {
            Schema::create($tabla, function (Blueprint $table): void {
                $table->id();
                $table->string('tipo');
                $table->string('terminal')->nullable();
                $table->string('terminal_clave')->nullable();
                $table->string('usuario_venta')->nullable();
                $table->dateTime('fecha_transaccion');
                $table->string('estatus')->nullable();
            });
        }
        Agencia::query()->create(['agencia' => 'A12', 'nombre_agencia' => 'Agencia Centro', 'terminal' => '0012', 'empresa' => 'GRUPO JOSELITO']);
        Agencia::query()->create(['agencia' => 'A13', 'nombre_agencia' => 'Agencia Sur', 'terminal' => '13', 'empresa' => 'NEGOSUR']);
        DB::table('empleados')->insert([
            'empleadoid' => 'E1', 'cedula' => '001-1234567-8', 'nombres' => 'Ana', 'apellidos' => 'Pérez',
        ]);
    }

    public function test_daily_report_returns_all_movements_with_identity_and_no_status(): void
    {
        $this->venta('2026-11-01 00:00:00');
        $this->venta('2026-11-01 23:59:59', ['tipo' => 'Recargas']);
        $this->venta('2026-11-02 12:00:00', ['tipo' => 'No Tradicional']);
        $this->venta('2026-10-31 23:59:59');
        $this->venta('2026-11-03 00:00:00');
        $this->venta('2026-11-02 23:59:59', ['terminal' => '013', 'terminal_clave' => '13']);

        $response = $this->getJson($this->url(['fecha_fin' => '2026-11-02']))
            ->assertOk()->assertJsonCount(3)
            ->assertJsonPath('0.tipo', 'No Tradicional')
            ->assertJsonPath('0.fecha', '2026-11-02 12:00:00')
            ->assertJsonPath('1', [
                'tipo' => 'Recargas', 'terminal' => '012', 'cedula' => '00112345678',
                'empleado' => 'Ana Pérez', 'fecha' => '2026-11-01 23:59:59',
            ]);

        $this->assertArrayNotHasKey('estatus', $response->json('0'));
        $this->assertArrayNotHasKey('status', $response->json('0'));
    }

    public function test_monthly_report_preserves_all_movements_inside_the_selected_range(): void
    {
        $this->venta('2026-10-01 00:00:00');
        $this->venta('2026-10-31 23:59:59');
        $this->venta('2026-11-01 12:00:00');
        $this->venta('2026-11-02 15:00:00', ['tipo' => 'No Tradicional']);
        $this->venta('2026-11-03 23:59:59');

        $this->getJson($this->url(['fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-11-02', 'periodo' => 'mes']))
            ->assertOk()->assertJsonCount(4)
            ->assertJsonPath('0.fecha', '2026-11-02 15:00:00')
            ->assertJsonPath('1.fecha', '2026-11-01 12:00:00')
            ->assertJsonPath('2.fecha', '2026-10-31 23:59:59')
            ->assertJsonPath('3.fecha', '2026-10-01 00:00:00');
    }

    public function test_source_priority_matches_payroll_for_each_date_including_other_terminals(): void
    {
        $this->venta('2026-11-01 23:00:00');
        $this->venta('2026-11-01 10:00:00', ['tipo' => 'No Tradicional'], 'ponche_por_cedula_ventas');
        $this->venta('2026-11-02 12:00:00');
        $this->venta('2026-11-03 12:00:00');
        $this->venta('2026-11-03 08:00:00', ['terminal' => '013', 'terminal_clave' => '13'], 'ponche_por_cedula_ventas');

        $this->getJson($this->url(['fecha_fin' => '2026-11-03']))->assertOk()->assertJsonCount(2)
            ->assertJsonPath('0.fecha', '2026-11-02 12:00:00')
            ->assertJsonPath('1.tipo', 'No Tradicional');
    }

    public function test_pending_recargas_and_rows_without_seller_are_excluded(): void
    {
        $this->venta('2026-11-01 12:00:00');
        $this->venta('2026-11-01 13:00:00', ['tipo' => 'Recargas', 'estatus' => 'Pendiente']);
        $this->venta('2026-11-01 14:00:00', ['usuario_venta' => '']);
        $this->venta('2026-11-01 15:00:00', ['usuario_venta' => null]);

        $this->getJson($this->url())->assertOk()->assertJsonCount(1)->assertJsonPath('0.fecha', '2026-11-01 12:00:00');
    }

    public function test_mismatched_company_unknown_terminal_and_empty_dates_return_no_data(): void
    {
        $this->venta('2026-11-01 12:00:00');
        $this->getJson($this->url(['empresa' => 'NEGOSUR']))->assertOk()->assertExactJson([]);
        $this->getJson($this->url(['terminal' => '999']))->assertOk()->assertExactJson([]);
        $this->getJson($this->url(['fecha_inicio' => '2026-11-02', 'fecha_fin' => '2026-11-02']))->assertOk()->assertExactJson([]);
    }

    public function test_same_time_preserves_both_movements_and_unknown_employee_is_explicit(): void
    {
        $this->venta('2026-11-01 12:00:00');
        $this->venta('2026-11-01 12:00:00', ['tipo' => 'No Tradicional', 'usuario_venta' => '99999999999']);

        $this->getJson($this->url())->assertOk()->assertJsonCount(2)
            ->assertJsonPath('0.tipo', 'No Tradicional')
            ->assertJsonPath('0.empleado', 'Actualizar en la maestra de empleado')
            ->assertJsonPath('1.empleado', 'Ana Pérez');
    }

    public function test_terminal_day_shows_eight_movements_from_both_employees(): void
    {
        $filas = [
            ['08:33:40', 'Recargas', '00112345678'],
            ['08:35:30', 'Recargas', '00112345678'],
            ['08:43:50', 'No Tradicional', '00112345678'],
            ['09:09:10', 'Tradicional', '00112345678'],
            ['17:46:35', 'Tradicional', '00212345678'],
            ['17:46:36', 'Tradicional', '00212345678'],
            ['17:48:18', 'Recargas', '00212345678'],
            ['17:52:28', 'Tradicional', '00212345678'],
        ];
        foreach ($filas as [$hora, $tipo, $cedula]) {
            $this->venta('2026-11-09 '.$hora, ['tipo' => $tipo, 'usuario_venta' => $cedula], 'ponche_por_cedula_ventas');
        }

        $response = $this->getJson($this->url(['fecha_inicio' => '2026-11-09', 'fecha_fin' => '2026-11-09']))
            ->assertOk()->assertJsonCount(8)
            ->assertJsonPath('0.fecha', '2026-11-09 17:52:28')
            ->assertJsonPath('7.fecha', '2026-11-09 08:33:40');

        $this->assertCount(4, collect($response->json())->where('cedula', '00112345678'));
        $this->assertCount(4, collect($response->json())->where('cedula', '00212345678'));
    }

    public function test_lookup_uses_company_and_normalizes_terminal_leading_zeroes(): void
    {
        $this->getJson(route('reportes.movimiento-terminal.agencia', ['empresa' => 'GRUPO JOSELITO', 'terminal' => '00012']))
            ->assertOk()->assertJsonPath('agencia', 'A12')->assertJsonPath('nombre_agencia', 'Agencia Centro');
        $this->getJson(route('reportes.movimiento-terminal.agencia', ['empresa' => 'NEGOSUR', 'terminal' => '12']))
            ->assertOk()->assertJsonMissingPath('agencia');
    }

    public function test_filters_are_validated_for_data_and_lookup(): void
    {
        $this->getJson(route('reportes.movimiento-terminal.list'))->assertUnprocessable()
            ->assertJsonValidationErrors(['empresa', 'terminal', 'fecha_inicio', 'fecha_fin', 'periodo']);
        $this->getJson($this->url(['fecha_inicio' => '2026-11-02', 'fecha_fin' => '2026-11-01', 'terminal' => '<script>', 'periodo' => 'otro']))
            ->assertUnprocessable()->assertJsonValidationErrors(['fecha_fin', 'terminal', 'periodo']);
        $this->getJson($this->url(['fecha_inicio' => '2026-02-30']))->assertUnprocessable()->assertJsonValidationErrors('fecha_inicio');
        $this->getJson(route('reportes.movimiento-terminal.agencia'))->assertUnprocessable()->assertJsonValidationErrors(['empresa', 'terminal']);
    }

    public function test_report_handles_missing_sales_tables(): void
    {
        Schema::drop('ponche_por_cedula_ventas');
        $this->venta('2026-11-01 12:00:00');
        $this->getJson($this->url())->assertOk()->assertJsonCount(1);
        Schema::drop('nomina_domingo_ventas');
        $this->getJson($this->url())->assertOk()->assertExactJson([]);
    }

    public function test_card_view_and_permission_protect_the_new_report(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        $usuario = Mockery::mock(User::class)->makePartial();
        $usuario->shouldReceive('can')->andReturnFalse();
        $usuario->shouldReceive('hasRole')->andReturnFalse();
        $usuario->shouldReceive('hasAnyRole')->andReturnFalse();
        $usuario->setRelation('viewPermissions', collect());
        $this->actingAs($usuario);
        Gate::before(fn (): bool => false);

        $card = collect(config('reportes'))->firstWhere('url', '/reportes-movimiento-terminal');
        $this->assertSame('Movimiento de terminal', $card['nombre']);
        $this->assertSame('reportes.movimiento_de_terminal.view', app(ViewPermissionCatalog::class)->permission('reportes', $card));
        $this->assertContains('role_or_permission:superadmin|admin|rh|reportes.view', Route::getRoutes()->getByName('reportes.movimiento-terminal.list')->gatherMiddleware());
        $this->get(route('reportes.movimiento-terminal'))->assertOk()->assertViewIs('reportes.movimiento-terminal')
            ->assertSee('Empresa')->assertSee('Datos limpios')->assertSee('Última transacción')
            ->assertDontSee('<th>Estatus</th>', false)->assertDontSee('<th>Status</th>', false);

        $this->withMiddleware();
        $this->getJson($this->url())->assertForbidden();
    }

    public function test_permission_migration_grants_report_roles_and_can_be_reversed(): void
    {
        $tables = require database_path('migrations/2026_02_23_000000_create_permission_tables.php');
        $tables->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $reportes = Role::findOrCreate('Conta_Rep', 'web');
        $rh = Role::findOrCreate('rh', 'web');
        $otro = Role::findOrCreate('comercial', 'web');
        $migration = require database_path('migrations/2026_11_16_105527_create_movimiento_terminal_report_permission.php');

        $migration->up();
        $migration->up();
        $this->assertSame(1, Permission::query()->where('name', 'reportes.movimiento_de_terminal.view')->count());
        $this->assertTrue($reportes->fresh()->hasPermissionTo('reportes.movimiento_de_terminal.view'));
        $this->assertTrue($rh->fresh()->hasPermissionTo('reportes.movimiento_de_terminal.view'));
        $this->assertFalse($otro->fresh()->hasPermissionTo('reportes.movimiento_de_terminal.view'));
        $migration->down();
        $this->assertFalse(Permission::query()->where('name', 'reportes.movimiento_de_terminal.view')->exists());
    }

    public function test_javascript_queries_filters_populates_agency_and_handles_errors(): void
    {
        $codigo = <<<'JS'
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const elements = {};
let boot;
global.document = {
    addEventListener(name, handler) { boot = handler; },
    getElementById(id) { return elements[id] ||= { value: '', listeners: {}, addEventListener(name, handler) { this.listeners[name] = handler; } }; }
};
const form = document.getElementById('formMovimientoTerminal');
global.FormData = class {
    *[Symbol.iterator]() {
        for (const [name, id] of [['empresa', 'empresa'], ['terminal', 'terminal_codigo'], ['fecha_inicio', 'fecha_inicio'], ['fecha_fin', 'fecha_fin'], ['periodo', 'periodo']]) {
            yield [name, elements[id].value];
        }
    }
};
let config;
let rows = [];
const table = {
    clear() { rows = []; return this; },
    draw() { return this; },
    settings() { return [{oLanguage: {}}]; },
    rows: { add(data) { rows.push(...data); return table; } }
};
global.$ = () => ({DataTable(options) {config = options; return table;}});
$.fn = {dataTable: {render: {text: () => ({display: text => String(text).replaceAll('<', '&lt;').replaceAll('>', '&gt;')})}}};
let alert;
global.Swal = {fire(options) {alert = options;}};
let response = {ok: true, json: async () => ({agencia: 'A12', nombre_agencia: 'Agencia Centro'})};
const urls = [];
global.fetch = async url => { urls.push(url); return response; };
let script = fs.readFileSync(process.argv[2], 'utf8').split('<script>')[1].split('</script>')[0];
script = script.replace(/@json\(route\('([^']+)'\)\)/g, (_, name) => JSON.stringify('/' + name));
vm.runInThisContext(script);
boot();
assert.deepEqual(config.columns.map(c => c.data), ['tipo', 'terminal', 'cedula', 'empleado', 'fecha']);
assert.match(config.columns[0].render('<script>', 'display'), /&lt;script&gt;/);
elements.empresa.value = 'GRUPO JOSELITO';
elements.terminal_codigo.value = '00012';
document.getElementById('fecha_inicio').value = '2026-11-01';
document.getElementById('fecha_fin').value = '2026-11-02';
document.getElementById('periodo').value = 'dia';
(async () => {
    await elements.empresa.listeners.change();
    assert.equal(elements.agencia_codigo.value, 'A12');
    assert.equal(elements.agencia_nombre.value, 'Agencia Centro');
    response = {ok: true, json: async () => Array.from({length: 8}, () => ({tipo: 'Tradicional', fecha: '2026-11-01 12:00:00'}))};
    await form.listeners.submit({preventDefault(){}});
    assert.equal(rows.length, 8);
    const query = new URL(urls.at(-1), 'https://test.invalid').searchParams;
    assert.equal(query.get('empresa'), 'GRUPO JOSELITO');
    assert.equal(query.get('terminal'), '00012');
    assert.equal(query.get('periodo'), 'dia');
    assert.equal(query.get('fecha_fin'), '2026-11-02');
    assert.equal(elements.btnBuscar.disabled, false);
    response = {ok: false, json: async () => ({errors: {terminal: ['Terminal inválida']}})};
    await form.listeners.submit({preventDefault(){}});
    assert.equal(alert.text, 'Terminal inválida');
    assert.equal(rows.length, 0);
    assert.equal(elements.btnBuscar.disabled, false);
    const requests = urls.length;
    elements.fecha_inicio.value = '2026-12-01';
    await form.listeners.submit({preventDefault(){}});
    assert.equal(urls.length, requests);
    assert.equal(alert.icon, 'warning');
})().catch(error => {console.error(error); process.exitCode = 1;});
JS;
        $process = new Process(['node', '-', resource_path('views/reportes/movimiento-terminal.blade.php')]);
        $process->setInput($codigo)->setTimeout(15)->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    /** @param array<string, string> $filtros */
    private function url(array $filtros = []): string
    {
        return route('reportes.movimiento-terminal.list', array_replace([
            'empresa' => 'GRUPO JOSELITO', 'terminal' => '00012',
            'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-01', 'periodo' => 'dia',
        ], $filtros));
    }

    /** @param array<string, mixed> $valores */
    private function venta(string $fecha, array $valores = [], string $tabla = 'nomina_domingo_ventas'): void
    {
        DB::table($tabla)->insert(array_replace([
            'tipo' => 'Tradicional', 'terminal' => '012', 'terminal_clave' => '12',
            'usuario_venta' => '00112345678', 'fecha_transaccion' => $fecha, 'estatus' => 'Validos',
        ], $valores));
    }
}
