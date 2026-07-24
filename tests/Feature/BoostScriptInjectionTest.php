<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Laravel\Boost\Middleware\InjectBoost;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class BoostScriptInjectionTest extends TestCase
{
    public function test_browser_logger_is_only_injected_once_in_incentive_v4_view(): void
    {
        $this->assertBrowserLoggerIsOnlyInjectedOnce(
            view('incentivos.reporte-nuevo-incentivo-v4', [
                'coordinadores' => collect(),
                'administrativosConfig' => [],
            ])->render()
        );
    }

    public function test_browser_logger_is_only_injected_once_in_incentive_v5_view(): void
    {
        $this->assertBrowserLoggerIsOnlyInjectedOnce(
            view('incentivos.reporte-nuevo-incentivo-v5', [
                'coordinadores' => collect(),
                'administrativosConfig' => [],
                'terminalesExcluidasIncentivo' => [],
            ])->render()
        );
    }

    public function test_incentive_v5_uses_its_production_name(): void
    {
        $html = view('incentivos.reporte-nuevo-incentivo-v5', [
            'coordinadores' => collect(),
            'administrativosConfig' => [],
            'terminalesExcluidasIncentivo' => [],
        ])->render();
        $module = collect(config('module_hubs.incentivos.items'))
            ->firstWhere('url', '/incentivos/reporte-nuevo-incentivo-v5-view');

        $this->assertSame('Calculo de Incentivos', $module['nombre']);
        $this->assertStringContainsString('Calculo de Incentivos', $html);
        $this->assertStringNotContainsString('V5 - Pruebas', $html);
    }

    public function test_browser_logger_is_only_injected_once_in_incentive_v6_view(): void
    {
        $this->assertBrowserLoggerIsOnlyInjectedOnce(
            view('incentivos.reporte-nuevo-incentivo-v6', [
                'coordinadores' => collect(),
                'administrativosConfig' => [],
                'terminalesExcluidasIncentivo' => [],
            ])->render()
        );
    }

    public function test_browser_logger_is_only_injected_once_in_commercial_sales_kpi_view(): void
    {
        $this->assertBrowserLoggerIsOnlyInjectedOnce(
            view('comercial.kpi-ventas', [
                'kpis' => [],
                'metasDiarias' => [],
                'cumplimiento' => [],
                'resumenAgencias' => [],
                'rentabilidadCargada' => false,
                'agenciasPorTipo' => [],
                'mesSeleccionado' => '2026-07',
            ])->render()
        );
    }

    private function assertBrowserLoggerIsOnlyInjectedOnce(string $html): void
    {
        $response = new Response($html, headers: ['Content-Type' => 'text/html']);
        $middleware = app(InjectBoost::class);
        $injectedResponse = $middleware->handle(
            Request::create('/'),
            fn (): Response => $response
        );

        $this->assertSame(1, substr_count($injectedResponse->getContent(), 'id="browser-logger-active"'));
    }
}
