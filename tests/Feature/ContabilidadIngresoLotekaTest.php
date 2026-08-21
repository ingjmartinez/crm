<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContabilidadIngresoLotekaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('agencias');
        Schema::dropIfExists('vt_usuarios_bet');
        Schema::dropIfExists('catalogo_juegos');
        Schema::dropIfExists('centros_de_costo');

        parent::tearDown();
    }

    public function test_report_is_registered_in_the_accounting_hub(): void
    {
        $this->assertTrue(Route::has('contabilidad.reportes.ingresos-loteka'));

        $card = collect(config('module_hubs.contabilidad.items'))
            ->firstWhere('url', '/contabilidad/reportes/ingresos-loteka');

        $this->assertSame('Ingresos Loteka', $card['nombre']);
        $this->assertSame('Reportes', $card['categoria']);
    }

    public function test_report_form_shows_loading_feedback_when_submitted(): void
    {
        $this->get(route('contabilidad.reportes.ingresos-loteka'))
            ->assertOk()
            ->assertSee('id="formIngresosLoteka"', false)
            ->assertSee('Generando datos')
            ->assertDontSee('vt_usuarios_bet')
            ->assertSee('Swal.showLoading()', false);
    }

    public function test_report_groups_only_non_traditional_sales_and_resolves_loteka_cost_center(): void
    {
        DB::table('catalogo_juegos')->insert([
            ['producto_id' => 10, 'tipo' => 'No Tradicional', 'descripcion' => 'Quiniela'],
            ['producto_id' => 20, 'tipo' => 'Tradicional', 'descripcion' => 'Loto'],
        ]);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '001', 'producto_id' => 10, 'monto' => 100, 'fecha' => '2026-08-10'],
            ['agencia_id' => '001', 'producto_id' => 10, 'monto' => 50, 'fecha' => '2026-08-11'],
            ['agencia_id' => '001', 'producto_id' => 20, 'monto' => 999, 'fecha' => '2026-08-10'],
            ['agencia_id' => '002', 'producto_id' => 10, 'monto' => 200, 'fecha' => '2026-08-10'],
            ['agencia_id' => '003', 'producto_id' => 10, 'monto' => 500, 'fecha' => '2026-07-31'],
        ]);
        DB::table('agencias')->insert([
            ['terminal' => '0001', 'empresa' => 'Grupo Joselito'],
            ['terminal' => '2', 'empresa' => 'Negosur'],
        ]);
        DB::table('centros_de_costo')->insert([
            [
                'id_centro_costo' => 10,
                'company_id' => '168',
                'descripcion' => 'Centro Loteka Uno',
                'id_viejo' => '0001',
                'inactivo' => false,
            ],
            [
                'id_centro_costo' => 99,
                'company_id' => '169',
                'descripcion' => 'Centro de otra empresa',
                'id_viejo' => '1',
                'inactivo' => false,
            ],
        ]);

        $response = $this->get(route('contabilidad.reportes.ingresos-loteka', [
            'consultar' => 1,
            'fecha_inicio' => '2026-08-01',
            'fecha_fin' => '2026-08-20',
            'monto_loteka' => '10,000,000.00',
        ]));

        $response->assertOk()
            ->assertSee('Terminales')
            ->assertSee('Centro de costo')
            ->assertSee('Monto')
            ->assertSee('Participación')
            ->assertSee('Participación Loteka')
            ->assertSee('Ventas base')
            ->assertSee('value="10,000,000.00"', false)
            ->assertSee('Centro Loteka Uno')
            ->assertSee('Grupo Joselito')
            ->assertSee('Negosur')
            ->assertSee('Subtotal Grupo Joselito')
            ->assertSee('Subtotal Negosur')
            ->assertSee('RD$ 150.00')
            ->assertSee('42.86%')
            ->assertSee('RD$ 4,285,714.29')
            ->assertSee('Sin centro de costo')
            ->assertSee('RD$ 200.00')
            ->assertSee('RD$ 350.00')
            ->assertSee('RD$ 10,000,000.00')
            ->assertDontSee('RD$ 999.00')
            ->assertViewHas('registros', fn ($registros): bool => $registros->count() === 2
                && $registros->firstWhere('terminal', '001')['centro_costo'] === '10'
                && $registros->firstWhere('terminal', '001')['monto'] === 150.0
                && $registros->firstWhere('terminal', '001')['participacion'] === 42.8571
                && $registros->firstWhere('terminal', '001')['monto_distribuido'] === 4285714.29
                && $registros->sum('monto_distribuido') === 10000000.0
                && $registros->firstWhere('terminal', '001')['empresa_id'] === '168'
                && $registros->firstWhere('terminal', '002')['empresa_id'] === '169')
            ->assertViewHas('totalMonto', 350.0)
            ->assertViewHas('montoLoteka', 10000000.0)
            ->assertViewHas('totalDistribuido', 10000000.0)
            ->assertViewHas('resumenEmpresas', fn ($resumenEmpresas): bool => $resumenEmpresas->get('168')['participacion'] === 42.8571
                && $resumenEmpresas->get('168')['monto_loteka'] === 4285714.29
                && $resumenEmpresas->get('169')['participacion'] === 57.1429
                && $resumenEmpresas->get('169')['monto_loteka'] === 5714285.71)
            ->assertViewHas('sinCentroCosto', 1);
    }

    public function test_report_can_be_filtered_by_company(): void
    {
        DB::table('catalogo_juegos')->insert([
            ['producto_id' => 10, 'tipo' => 'No Tradicional', 'descripcion' => 'Quiniela'],
        ]);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '001', 'producto_id' => 10, 'monto' => 175, 'fecha' => '2026-08-10'],
            ['agencia_id' => '002', 'producto_id' => 10, 'monto' => 200, 'fecha' => '2026-08-10'],
        ]);
        DB::table('agencias')->insert([
            ['terminal' => '1', 'empresa' => 'Grupo Joselito'],
            ['terminal' => '2', 'empresa' => 'Negosur'],
        ]);
        DB::table('centros_de_costo')->insert([
            'id_centro_costo' => 20,
            'company_id' => '169',
            'descripcion' => 'Centro Negosur Dos',
            'id_viejo' => '0002',
            'inactivo' => false,
        ]);

        $this->get(route('contabilidad.reportes.ingresos-loteka', [
            'consultar' => 1,
            'fecha_inicio' => '2026-08-01',
            'fecha_fin' => '2026-08-20',
            'empresa' => '169',
        ]))
            ->assertOk()
            ->assertSee('Negosur')
            ->assertSee('Centro Negosur Dos')
            ->assertSee('RD$ 200.00')
            ->assertSee('100.00%')
            ->assertDontSee('RD$ 175.00')
            ->assertViewHas('empresa', '169')
            ->assertViewHas('registros', fn ($registros): bool => $registros->count() === 1
                && $registros->first()['terminal'] === '002');
    }

    public function test_report_validates_the_date_range(): void
    {
        $this->get(route('contabilidad.reportes.ingresos-loteka', [
            'consultar' => 1,
            'fecha_inicio' => '2026-08-20',
            'fecha_fin' => '2026-08-01',
        ]))->assertSessionHasErrors('fecha_fin');
    }

    public function test_report_validates_the_company_filter_and_loteka_amount(): void
    {
        $this->get(route('contabilidad.reportes.ingresos-loteka', [
            'consultar' => 1,
            'fecha_inicio' => '2026-08-01',
            'fecha_fin' => '2026-08-20',
            'empresa' => '999',
            'monto_loteka' => -1,
        ]))->assertSessionHasErrors(['empresa', 'monto_loteka']);
    }

    private function createSchema(): void
    {
        foreach (['agencias', 'vt_usuarios_bet', 'catalogo_juegos', 'centros_de_costo'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal')->nullable();
            $table->string('empresa')->nullable();
            $table->timestamps();
        });

        Schema::create('catalogo_juegos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('producto_id')->unique();
            $table->string('tipo');
            $table->string('descripcion')->nullable();
        });

        Schema::create('vt_usuarios_bet', function (Blueprint $table): void {
            $table->id('vt_usuario_id');
            $table->string('agencia_id');
            $table->unsignedBigInteger('producto_id');
            $table->decimal('monto', 14, 2);
            $table->date('fecha');
        });

        Schema::create('centros_de_costo', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_centro_costo')->unique();
            $table->string('company_id')->nullable();
            $table->string('descripcion')->nullable();
            $table->string('id_viejo')->nullable();
            $table->boolean('inactivo')->default(false);
            $table->timestamps();
        });
    }
}
