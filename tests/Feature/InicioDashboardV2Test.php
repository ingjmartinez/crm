<?php

namespace Tests\Feature;

use App\Http\Controllers\InicioController;
use App\Http\Controllers\InicioV2Controller;
use App\Services\Gerencia\VentasEnVivoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InicioDashboardV2Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-11 14:30:00');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_v2_is_published_as_a_separate_dashboard_card(): void
    {
        $item = collect(config('module_hubs.dashboard.items'))->firstWhere('nombre', 'Tablero Principal V2');

        $this->assertNotNull($item);
        $this->assertSame('/inicio-v2', $item['url']);
        $this->assertTrue(Route::has('inicio-v2.index'));
        $this->assertTrue(Route::has('inicio-v2.ventas-en-vivo'));
    }

    public function test_live_endpoint_returns_today_traditional_and_non_traditional_sales(): void
    {
        $this->withoutDefer();

        $this->mock(VentasEnVivoService::class, function ($mock): void {
            $mock->shouldReceive('generarTotales')
                ->once()
                ->withArgs(fn (Carbon $fecha, array $filtros): bool => $fecha->toDateString() === '2026-09-11'
                    && $filtros['empresa'] === 'Empresa Uno')
                ->andReturn([
                    'resumen' => [
                        'total_tradicional' => 1250.55,
                        'total_no_tradicional' => 875.25,
                    ],
                ]);
        });

        $primeraRespuesta = $this->withoutMiddleware()
            ->getJson(route('inicio-v2.ventas-en-vivo', ['empresa' => 'Empresa Uno']));

        $primeraRespuesta
            ->assertOk()
            ->assertJsonPath('pendiente', true)
            ->assertJsonPath('tradicional', 1250.55);

        $this->getJson(route('inicio-v2.ventas-en-vivo', ['empresa' => 'Empresa Uno']))
            ->assertOk()
            ->assertJsonPath('fecha', '2026-09-11')
            ->assertJsonPath('tradicional', 1250.55)
            ->assertJsonPath('no_tradicional', 875.25)
            ->assertJsonPath('desactualizado', false);
    }

    public function test_v2_defaults_the_dashboard_query_to_today(): void
    {
        $request = Request::create('/inicio-v2', 'GET');
        $inicioController = $this->mock(InicioController::class);
        $inicioController->shouldReceive('index')
            ->once()
            ->withArgs(fn (Request $receivedRequest): bool => $receivedRequest->query('fecha') === '2026-09-11'
                && $receivedRequest->query('cargar') === '1')
            ->andReturn(view('inicio'));

        $controller = new InicioV2Controller($inicioController, $this->mock(VentasEnVivoService::class));
        $view = $controller->index($request);

        $this->assertTrue($view->getData()['esTableroV2']);
    }
}
