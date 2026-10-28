<?php

namespace Tests\Feature;

use App\Http\Controllers\Gerencia\RentabilidadAgenciaV3Controller;
use App\Http\Requests\ConsultarRentabilidadAgenciaV3Request;
use App\Models\ClasificacionCuenta;
use App\Models\CuentaContable;
use App\Models\User;
use App\ViewPermissionCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RentabilidadAgenciaV3Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal');
            $table->string('nombre_agencia')->nullable();
            $table->string('agencia')->nullable();
            $table->string('empresa')->nullable();
            $table->integer('estatus')->default(1);
        });
        Schema::create('entradas_diario', function (Blueprint $table): void {
            $table->id();
            $table->string('id_viejo');
            $table->string('cuenta');
            $table->date('fecha');
            $table->decimal('debito', 15, 2)->default(0);
            $table->decimal('credito', 15, 2)->default(0);
        });
        Schema::create('cuentas_contables', function (Blueprint $table): void {
            $table->id();
            $table->string('cuenta');
            $table->string('tipo');
        });
        Schema::create('clasificacion_cuentas', function (Blueprint $table): void {
            $table->id();
            $table->string('cuenta');
            $table->string('clasificacion')->nullable();
            $table->boolean('activo');
            $table->timestamps();
        });
        Schema::create('rentabilidad_agencia_r20', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal');
            $table->string('mes');
            $table->decimal('monto', 15, 2);
            $table->timestamps();
        });
        config(['clasificacion_cuentas.cuentas' => []]);
        $this->actingAs(User::factory()->make());
    }

    public function test_v3_compares_year_to_date_and_last_three_closed_months(): void
    {
        DB::table('agencias')->insert([
            ['terminal' => '1001', 'nombre_agencia' => 'Agencia rentable', 'empresa' => 'Grupo A'],
            ['terminal' => '2002', 'nombre_agencia' => 'Agencia en pérdida', 'empresa' => 'Grupo B'],
            ['terminal' => '3003', 'nombre_agencia' => 'Agencia sin movimientos contables', 'empresa' => 'Grupo A'],
        ]);
        DB::table('cuentas_contables')->insert([
            ['cuenta' => '6001', 'tipo' => 'Gasto'],
            ['cuenta' => '7001', 'tipo' => 'Costo'],
            ['cuenta' => '8001', 'tipo' => 'Gasto'],
        ]);
        DB::table('clasificacion_cuentas')->insert([
            ['cuenta' => '6001', 'clasificacion' => 'FIJO', 'activo' => 1],
            ['cuenta' => '7001', 'clasificacion' => 'VARIABLE', 'activo' => 1],
            ['cuenta' => '8001', 'clasificacion' => 'FIJO', 'activo' => 0],
        ]);
        DB::table('rentabilidad_agencia_r20')->insert(['terminal' => '*', 'mes' => 'GLOBAL', 'monto' => 10]);

        foreach (['2026-01', '2026-07', '2026-08', '2026-09'] as $mes) {
            DB::table('entradas_diario')->insert([
                ['id_viejo' => '1001', 'cuenta' => '400110001', 'fecha' => $mes.'-15', 'debito' => 0, 'credito' => 100],
                ['id_viejo' => '1001', 'cuenta' => '6001', 'fecha' => $mes.'-15', 'debito' => 40, 'credito' => 0],
                ['id_viejo' => '2002', 'cuenta' => '400110001', 'fecha' => $mes.'-15', 'debito' => 0, 'credito' => 20],
                ['id_viejo' => '2002', 'cuenta' => '6001', 'fecha' => $mes.'-15', 'debito' => 40, 'credito' => 0],
            ]);
        }
        DB::table('entradas_diario')->insert([
            ['id_viejo' => '1001', 'cuenta' => '7001', 'fecha' => '2026-09-15', 'debito' => 500, 'credito' => 0],
            ['id_viejo' => '1001', 'cuenta' => '8001', 'fecha' => '2026-09-15', 'debito' => 500, 'credito' => 0],
            ['id_viejo' => '1001', 'cuenta' => '400999999', 'fecha' => '2026-09-15', 'debito' => 0, 'credito' => 500],
            ['id_viejo' => '1001', 'cuenta' => '400110001', 'fecha' => '2026-10-01', 'debito' => 0, 'credito' => 500],
            ['id_viejo' => '9999', 'cuenta' => '400110001', 'fecha' => '2026-09-15', 'debito' => 0, 'credito' => 500],
        ]);

        $this->assertSame(['6001', '7001'], ClasificacionCuenta::cuentasActivas()->all());
        $this->assertSame(['6001'], CuentaContable::query()->whereIn('cuenta', ClasificacionCuenta::cuentasActivas()->all())->whereRaw('LOWER(TRIM(tipo)) = ?', ['gasto'])->pluck('cuenta')->all());

        $request = ConsultarRentabilidadAgenciaV3Request::create('/gerencia/rentabilidad-agencia-v3', 'GET', ['mes_cierre' => '2026-09']);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();
        $data = app(RentabilidadAgenciaV3Controller::class)->index($request)->getData();
        $rentable = $data['filas']->firstWhere('terminal', '1001');
        $perdida = $data['filas']->firstWhere('terminal', '2002');

        $this->assertSame(['2026-07', '2026-08', '2026-09'], $data['mesesTrimestre']->all());
        $this->assertCount(9, $data['mesesAnio']);
        $this->assertSame(400.0, $rentable['anio']['ingresos']);
        $this->assertSame(200.0, $rentable['anio']['gastos']);
        $this->assertSame(200.0, $rentable['anio']['utilidad']);
        $this->assertSame(4, $rentable['anio']['meses_con_movimientos']);
        $this->assertFalse($rentable['anio']['completo']);
        $this->assertSame(150.0, $rentable['trimestre']['utilidad']);
        $this->assertSame(3, $rentable['trimestre']['meses_rentables']);
        $this->assertSame('Datos incompletos', $rentable['clasificacion']);
        $this->assertSame(-120.0, $perdida['anio']['utilidad']);
        $this->assertSame('Datos incompletos', $perdida['clasificacion']);
        $this->assertSame('Sin movimientos', $data['filas']->firstWhere('terminal', '3003')['clasificacion']);
        $this->assertSame(0.0, $data['filas']->firstWhere('terminal', '3003')['anio']['utilidad']);
        $this->assertSame(0, $data['resumen']['rentables_anio']);
        $this->assertSame(1, $data['resumen']['rentables_trimestre']);
        $this->assertSame(2, $data['resumen']['incompletas']);
        $this->assertSame(['ingresos' => 120.0, 'gastos' => 100.0, 'terminales_con_movimientos' => 2], $data['graficoMensual']['2026-07']);
        $this->assertSame(['ingresos' => 0.0, 'gastos' => 0.0, 'terminales_con_movimientos' => 0], $data['graficoMensual']['2026-02']);
        $this->assertSame('/gerencia/rentabilidad-agencia-v3', route('gerencia.rentabilidad-agencia-v3', [], false));
        $this->assertSame('gerencia.rentabilidad_de_agencia.view', app(ViewPermissionCatalog::class)->items()->firstWhere('path', '/gerencia/rentabilidad-agencia-v3')['permission']);
        $viewSource = file_get_contents(resource_path('views/gerencia/rentabilidad-agencia-v3.blade.php'));
        $this->assertStringContainsString('Rentable sostenida', $viewSource);
        $this->assertStringContainsString('id="modalRentabilidadAgenciaV3"', $viewSource);
        $this->assertStringContainsString('id="periodoGraficoRentabilidadV3"', $viewSource);
        $this->assertLessThan(strpos($viewSource, '@if ($consultar && $resumen !== null)'), strpos($viewSource, 'id="periodoGraficoRentabilidadV3"'));
        $this->assertStringContainsString('id="graficoRentabilidadAgenciaV3"', $viewSource);
        $this->assertStringContainsString('id="totalIngresosGraficoV3"', $viewSource);
        $this->assertStringContainsString('id="filtrosRentabilidadAgenciaV3"', $viewSource);
        $this->assertStringContainsString("title: 'Generando reporte'", $viewSource);
        $this->assertStringContainsString('didOpen: () => Swal.showLoading()', $viewSource);
    }

    public function test_v3_quarter_can_cross_calendar_year_and_company_filter_applies(): void
    {
        DB::table('agencias')->insert([
            ['terminal' => '1001', 'empresa' => 'Grupo A'],
            ['terminal' => '2002', 'empresa' => 'Grupo B'],
        ]);
        DB::table('cuentas_contables')->insert(['cuenta' => '6001', 'tipo' => 'Gasto']);
        DB::table('clasificacion_cuentas')->insert(['cuenta' => '6001', 'clasificacion' => 'FIJO', 'activo' => 1]);
        DB::table('entradas_diario')->insert([
            ['id_viejo' => '1001', 'cuenta' => '400110001', 'fecha' => '2025-11-15', 'debito' => 0, 'credito' => 50],
            ['id_viejo' => '1001', 'cuenta' => '6001', 'fecha' => '2025-12-15', 'debito' => 20, 'credito' => 0],
            ['id_viejo' => '1001', 'cuenta' => '400110001', 'fecha' => '2026-01-15', 'debito' => 0, 'credito' => 100],
        ]);

        $request = ConsultarRentabilidadAgenciaV3Request::create('/gerencia/rentabilidad-agencia-v3', 'GET', ['mes_cierre' => '2026-01', 'empresa' => 'Grupo A']);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();
        $data = app(RentabilidadAgenciaV3Controller::class)->index($request)->getData();

        $this->assertSame(['2025-11', '2025-12', '2026-01'], $data['mesesTrimestre']->all());
        $this->assertSame(['1001'], $data['filas']->pluck('terminal')->all());
        $this->assertSame(100.0, $data['filas'][0]['anio']['utilidad']);
        $this->assertSame(130.0, $data['filas'][0]['trimestre']['utilidad']);
        $this->assertSame('Rentable irregular', $data['filas'][0]['clasificacion']);
        $this->assertSame(50.0, $data['graficoMensual']['2025-11']['ingresos']);
        $this->assertSame(20.0, $data['graficoMensual']['2025-12']['gastos']);
    }

    public function test_v3_classifies_sustained_profit_only_with_complete_months(): void
    {
        DB::table('agencias')->insert(['terminal' => '1001', 'empresa' => 'Grupo A']);
        foreach (['2026-01', '2026-02', '2026-03'] as $mes) {
            DB::table('entradas_diario')->insert([
                'id_viejo' => '1001', 'cuenta' => '400110001', 'fecha' => $mes.'-15', 'debito' => 0, 'credito' => 100,
            ]);
        }

        $request = ConsultarRentabilidadAgenciaV3Request::create('/gerencia/rentabilidad-agencia-v3', 'GET', ['mes_cierre' => '2026-03']);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();
        $data = app(RentabilidadAgenciaV3Controller::class)->index($request)->getData();

        $this->assertTrue($data['filas'][0]['anio']['completo']);
        $this->assertTrue($data['filas'][0]['trimestre']['completo']);
        $this->assertSame('Rentable sostenida', $data['filas'][0]['clasificacion']);
        $this->assertSame(1, $data['resumen']['rentables_anio']);
    }

    public function test_v3_rejects_open_month(): void
    {
        $request = new ConsultarRentabilidadAgenciaV3Request;

        $this->assertTrue(Validator::make(['mes_cierre' => now()->format('Y-m')], $request->rules())->fails());
        $this->assertTrue(Validator::make(['mes_cierre' => 'septiembre'], $request->rules())->fails());
        $this->assertFalse(Validator::make(['mes_cierre' => now()->subMonth()->format('Y-m')], $request->rules())->fails());
    }
}
