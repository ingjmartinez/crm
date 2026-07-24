<?php

namespace Tests\Feature;

use App\Http\Controllers\ModuleHubController;
use App\Http\Controllers\Tecnologia\MonitoreoAgenciaPlazaController;
use App\Http\Controllers\Tecnologia\MonitoreoTerminalController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TecnologiaMonitoringCardsTest extends TestCase
{
    public function test_technology_hub_contains_both_blank_monitoring_cards(): void
    {
        $items = collect(config('module_hubs.tecnologia.items'));
        $terminales = $items->firstWhere('nombre', 'Monitoreo de Terminales');
        $agencias = $items->firstWhere('nombre', 'Monitoreo agencias en plaza');

        $this->assertNotNull($terminales);
        $this->assertSame('', $terminales['descripcion']);
        $this->assertSame('/tecnologia/monitoreo-terminales', $terminales['url']);
        $this->assertTrue($terminales['activo']);

        $this->assertNotNull($agencias);
        $this->assertSame('', $agencias['descripcion']);
        $this->assertSame('/tecnologia/monitoreo-agencias-plaza', $agencias['url']);
        $this->assertTrue($agencias['activo']);
    }

    public function test_technology_hub_exposes_both_monitoring_cards(): void
    {
        $view = app(ModuleHubController::class)->tecnologia();
        $items = $view->getData()['items'];

        $this->assertSame('module-hub.index', $view->name());
        $this->assertNotNull($items->firstWhere('nombre', 'Monitoreo de Terminales'));
        $this->assertNotNull($items->firstWhere('nombre', 'Monitoreo agencias en plaza'));
    }

    public function test_each_monitoring_card_has_an_independent_controller_route_and_view(): void
    {
        $this->assertTrue(Route::has('tecnologia.monitoreo-terminales.index'));
        $this->assertTrue(Route::has('tecnologia.monitoreo-agencias-plaza.index'));

        $terminalesRoute = Route::getRoutes()->getByName('tecnologia.monitoreo-terminales.index');
        $agenciasRoute = Route::getRoutes()->getByName('tecnologia.monitoreo-agencias-plaza.index');

        $this->assertSame('tecnologia/monitoreo-terminales', $terminalesRoute->uri());
        $this->assertSame(MonitoreoTerminalController::class.'@index', $terminalesRoute->getActionName());
        $this->assertSame('tecnologia/monitoreo-agencias-plaza', $agenciasRoute->uri());
        $this->assertSame(MonitoreoAgenciaPlazaController::class, $agenciasRoute->getActionName());
        $this->assertNotSame($terminalesRoute->getActionName(), $agenciasRoute->getActionName());

        $this->assertTrue(view()->exists('tecnologia.monitoreo-terminales'));
        $this->assertTrue(view()->exists('tecnologia.monitoreo-agencias-plaza'));
    }
}
