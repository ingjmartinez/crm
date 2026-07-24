<?php

namespace Tests\Feature;

use App\Http\Controllers\Gerencia\RentabilidadAgenciaController;
use App\Http\Controllers\ModuleHubController;
use App\Models\RentabilidadAgencia;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class GerenciaModuleHubTest extends TestCase
{
    public function test_agency_profitability_card_is_published_in_management_hub(): void
    {
        $item = collect(config('module_hubs.gerencia.items'))
            ->firstWhere('nombre', 'Rentabilidad de Agencia');

        $this->assertNotNull($item);
        $this->assertSame('/gerencia/rentabilidad-agencia', $item['url']);
        $this->assertSame('Rentabilidad', $item['categoria']);
        $this->assertTrue($item['activo']);
    }

    public function test_management_hub_exposes_agency_profitability_card(): void
    {
        $view = app(ModuleHubController::class)->gerencia();
        $item = $view->getData()['items']->firstWhere('nombre', 'Rentabilidad de Agencia');

        $this->assertSame('module-hub.index', $view->name());
        $this->assertNotNull($item);
        $this->assertSame(url('/gerencia/rentabilidad-agencia'), $item['url']);
    }

    public function test_agency_profitability_has_independent_model_controller_route_and_view(): void
    {
        $this->assertTrue(class_exists(RentabilidadAgencia::class));
        $this->assertTrue(class_exists(RentabilidadAgenciaController::class));
        $this->assertTrue(Route::has('gerencia.rentabilidad-agencia'));

        $route = Route::getRoutes()->getByName('gerencia.rentabilidad-agencia');

        $this->assertSame('gerencia/rentabilidad-agencia', $route->uri());
        $this->assertStringContainsString(RentabilidadAgenciaController::class, $route->getActionName());
        $this->assertTrue(view()->exists('gerencia.rentabilidad-agencia'));
    }
}
