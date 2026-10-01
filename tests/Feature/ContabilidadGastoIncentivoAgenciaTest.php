<?php

namespace Tests\Feature;

use App\Http\Controllers\IncentivosController;
use App\Http\Controllers\IncentivoV6Controller;
use App\Services\Contabilidad\DistribuidorIncentivoAgencia;
use App\Services\IncentivoV6Calculator;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class ContabilidadGastoIncentivoAgenciaTest extends TestCase
{
    public function test_report_routes_are_registered_in_accounting(): void
    {
        $this->assertTrue(Route::has('contabilidad.reportes.gastos-incentivo-agencia'));
        $this->assertTrue(Route::has('contabilidad.reportes.gastos-incentivo-agencia.data'));
    }

    public function test_report_view_contains_excel_and_generate_feedback_controls(): void
    {
        $view = $this->view('contabilidad.reportes.gastos_incentivo_agencia');

        $view->assertSee('id="btnDescargarExcel"', false);
        $view->assertSee('Generando reporte...');
        $view->assertSee('Reporte generado');
        $view->assertSee("extend: 'excelHtml5'", false);
        $view->assertDontSee('id="tipoPago"', false);
        $view->assertSee('desvinculadosConsultados', false);
        $view->assertSee('Calendario aplicado:', false);
    }

    public function test_shared_v6_calendar_calculation_receives_all_payment_ranges_and_excluded_terminals(): void
    {
        $calculator = Mockery::mock(IncentivoV6Calculator::class);
        $calculator->shouldReceive('applyDailyPaymentTypes')
            ->once()
            ->withArgs(function (array $payload, array $filters, array $ranges): bool {
                return $payload === ['data' => []]
                    && $filters['terminales_excluidas'] === ['1001']
                    && isset($ranges['tramos_60'], $ranges['tramos_70'], $ranges['tramos_80']);
            })
            ->andReturn(['data' => [], 'meta' => ['modo_tipos_pago' => 'calendario_diario_terminal']]);

        $controller = new IncentivoV6Controller(Mockery::mock(IncentivosController::class), $calculator);
        $result = $controller->applyPaymentCalendar(['data' => []], [
            'fecha_ini' => '2026-09-01',
            'fecha_fin' => '2026-09-30',
            'terminales_excluidas' => '["1001"]',
        ]);

        $this->assertSame('calendario_diario_terminal', $result['meta']['modo_tipos_pago']);
    }

    public function test_it_distributes_incentive_proportionally_to_agency_sales(): void
    {
        $result = app(DistribuidorIncentivoAgencia::class)->distribuir(
            500000,
            [400000, 100000]
        );

        $this->assertSame([400000, 100000], $result);
        $this->assertSame(500000, array_sum($result));
    }

    public function test_it_only_accepts_rows_that_qualified_and_won_an_incentive(): void
    {
        $distribuidor = app(DistribuidorIncentivoAgencia::class);

        $this->assertSame(500000, $distribuidor->incentivoGanadoCentavos([
            'cumple_minimo' => 'SI',
            'nuevo_incentivo' => '5,000',
        ]));
        $this->assertSame(0, $distribuidor->incentivoGanadoCentavos([
            'cumple_minimo' => 'NO',
            'nuevo_incentivo' => '5,000',
        ]));
        $this->assertSame(0, $distribuidor->incentivoGanadoCentavos([
            'cumple_minimo' => 'SI',
            'nuevo_incentivo' => '0',
        ]));
    }

    public function test_it_assigns_rounding_difference_without_changing_total(): void
    {
        $result = app(DistribuidorIncentivoAgencia::class)->distribuir(
            100,
            [1, 1, 1]
        );

        $this->assertSame([34, 33, 33], $result);
        $this->assertSame(100, array_sum($result));
    }

    public function test_it_distributes_whole_pesos_and_preserves_the_won_total(): void
    {
        $result = app(DistribuidorIncentivoAgencia::class)->distribuir(
            5001,
            [400000, 100000]
        );

        $this->assertSame([4001, 1000], $result);
        $this->assertSame(5001, array_sum($result));
    }

    public function test_it_returns_zero_when_there_are_no_sales(): void
    {
        $result = app(DistribuidorIncentivoAgencia::class)->distribuir(
            500000,
            [0, 0]
        );

        $this->assertSame([0, 0], $result);
    }
}
