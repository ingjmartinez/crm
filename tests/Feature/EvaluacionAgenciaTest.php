<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class EvaluacionAgenciaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-14 12:00:00');
        View::share('errors', new ViewErrorBag);

        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal')->nullable();
            $table->string('nombre_agencia')->nullable();
            $table->string('empresa')->nullable();
        });

        foreach (['vt_usuarios_bet', 'vt_usuarios_net'] as $tabla) {
            Schema::create($tabla, function (Blueprint $table): void {
                $table->id();
                $table->string('agencia_id');
                $table->decimal('monto', 14, 2);
                $table->date('fecha');
                $table->string('tipo')->default('Tradicional');
            });
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        foreach (['vt_usuarios_net', 'vt_usuarios_bet', 'agencias'] as $tabla) {
            Schema::dropIfExists($tabla);
        }

        parent::tearDown();
    }

    public function test_evaluates_combined_monthly_sales_against_the_same_goal(): void
    {
        DB::table('agencias')->insert(['terminal' => '001001', 'nombre_agencia' => 'Agencia Central']);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '1001', 'monto' => 70000, 'fecha' => '2026-07-10'],
            ['agencia_id' => '1001', 'monto' => 60000, 'fecha' => '2026-08-10'],
        ]);
        DB::table('vt_usuarios_net')->insert([
            ['agencia_id' => '001001', 'monto' => 40000, 'fecha' => '2026-07-15'],
            ['agencia_id' => '1001', 'monto' => 30000, 'fecha' => '2026-08-15'],
        ]);

        $response = $this->withoutMiddleware()->get(route('gerencia.evaluacion-agencia', [
            'analizar' => 1,
            'meses' => 3,
            'meta' => 100000,
        ]));

        $response->assertOk()
            ->assertSee('Agencia Central')
            ->assertSee('RD$ 110,000.00')
            ->assertSee('RD$ 90,000.00')
            ->assertSee('table-success text-success', false)
            ->assertSee('table-danger text-danger', false)
            ->assertViewHas('filas', function ($filas): bool {
                $fila = $filas->first();

                return $fila['ventas']['2026-07']['cumple'] === true
                    && $fila['ventas']['2026-08']['cumple'] === false
                    && $fila['ventas']['2026-09']['venta'] === 0.0
                    && $fila['meses_cumple'] === 1
                    && $fila['meses_no_cumple'] === 2
                    && $fila['cumple'] === false;
            });
    }

    public function test_validates_month_count_and_goal(): void
    {
        $this->withoutMiddleware()->get(route('gerencia.evaluacion-agencia', [
            'analizar' => 1,
            'meses' => 25,
            'meta' => -1,
        ]))->assertSessionHasErrors(['meses', 'meta']);
    }

    public function test_filters_the_evaluation_by_company(): void
    {
        DB::table('agencias')->insert([
            ['terminal' => '1001', 'nombre_agencia' => 'Agencia Empresa A', 'empresa' => 'Empresa A'],
            ['terminal' => '2002', 'nombre_agencia' => 'Agencia Empresa B', 'empresa' => 'Empresa B'],
        ]);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '1001', 'monto' => 120000, 'fecha' => '2026-09-10'],
            ['agencia_id' => '2002', 'monto' => 130000, 'fecha' => '2026-09-10'],
        ]);

        $this->withoutMiddleware()->get(route('gerencia.evaluacion-agencia', [
            'analizar' => 1,
            'meses' => 1,
            'meta' => 100000,
            'empresa' => 'Empresa A',
        ]))->assertOk()
            ->assertSee('Agencia Empresa A')
            ->assertDontSee('Agencia Empresa B')
            ->assertViewHas('empresaSeleccionada', 'Empresa A');
    }

    public function test_report_is_available_from_the_management_hub(): void
    {
        $this->withoutMiddleware()->get(route('gerencia.evaluacion-agencia'))
            ->assertOk()
            ->assertSee('Evaluación de agencias')
            ->assertSee('Cantidad de meses a evaluar')
            ->assertSee('Parámetro de venta mensual')
            ->assertSee('Todas las empresas');

        $item = collect(config('module_hubs.gerencia.items'))->firstWhere('url', '/gerencia/evaluacion-agencia');

        $this->assertSame('Evaluación de Agencia', $item['nombre']);
        $this->assertTrue($item['activo']);
    }

    public function test_displays_terminal_totals_and_exports_them_with_the_detail(): void
    {
        DB::table('agencias')->insert([
            ['terminal' => '1001', 'nombre_agencia' => 'Agencia Cumple'],
            ['terminal' => '2002', 'nombre_agencia' => 'Agencia No Cumple'],
        ]);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '1001', 'monto' => 120000, 'fecha' => '2026-09-10'],
            ['agencia_id' => '2002', 'monto' => 90000, 'fecha' => '2026-09-10'],
        ]);

        $query = ['analizar' => 1, 'meses' => 1, 'meta' => 100000];

        $this->withoutMiddleware()->get(route('gerencia.evaluacion-agencia', $query))
            ->assertOk()
            ->assertSee('Terminales que cumplen: 1')
            ->assertSee('Terminales que no cumplen: 1')
            ->assertSee('Meses que no cumple')
            ->assertSee(route('gerencia.evaluacion-agencia.export.excel', $query));

        $response = $this->withoutMiddleware()->get(route('gerencia.evaluacion-agencia.export.excel', $query));
        $response->assertOk()->assertDownload('evaluacion_agencias_20260914_120000.xlsx');

        $workbook = IOFactory::load($response->baseResponse->getFile()->getPathname());
        $resumen = $workbook->getSheetByName('Resumen');
        $detalle = $workbook->getSheetByName('Detalle');

        $this->assertSame('Tradicional, No tradicional, Recargas', $resumen?->getCell('B3')->getValue());
        $this->assertSame('Terminales que cumplen', $resumen?->getCell('A7')->getValue());
        $this->assertSame(1, $resumen?->getCell('B7')->getValue());
        $this->assertSame('Terminales que no cumplen', $resumen?->getCell('A8')->getValue());
        $this->assertSame(1, $resumen?->getCell('B8')->getValue());
        $this->assertSame('Meses que no cumple', $detalle?->getCell('F1')->getValue());
        $this->assertSame('Estado', $detalle?->getCell('G1')->getValue());
        $this->assertSame('Cumple', $detalle?->getCell('G2')->getValue());
        $this->assertSame('No cumple', $detalle?->getCell('G3')->getValue());
    }

    public function test_filters_sales_by_the_selected_product_types(): void
    {
        DB::table('agencias')->insert(['terminal' => '1001', 'nombre_agencia' => 'Agencia Mixta']);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '1001', 'tipo' => 'Tradicional', 'monto' => 100000, 'fecha' => '2026-09-10'],
            ['agencia_id' => '1001', 'tipo' => 'No Tradicional', 'monto' => 20000, 'fecha' => '2026-09-10'],
            ['agencia_id' => '1001', 'tipo' => 'Recargas', 'monto' => 30000, 'fecha' => '2026-09-10'],
            ['agencia_id' => '1001', 'tipo' => 'Paqueticos', 'monto' => 5000, 'fecha' => '2026-09-10'],
        ]);

        $this->withoutMiddleware()->get(route('gerencia.evaluacion-agencia', [
            'analizar' => 1,
            'seleccion_productos' => 1,
            'productos' => ['no_tradicional', 'recargas'],
            'meses' => 1,
            'meta' => 50000,
        ]))->assertOk()
            ->assertSee('RD$ 55,000.00')
            ->assertDontSee('RD$ 155,000.00')
            ->assertViewHas('productosSeleccionados', ['no_tradicional', 'recargas']);
    }

    public function test_requires_at_least_one_product_when_using_the_product_filter(): void
    {
        $this->withoutMiddleware()->get(route('gerencia.evaluacion-agencia', [
            'analizar' => 1,
            'seleccion_productos' => 1,
            'meses' => 1,
            'meta' => 100000,
        ]))->assertSessionHasErrors('productos');
    }
}
