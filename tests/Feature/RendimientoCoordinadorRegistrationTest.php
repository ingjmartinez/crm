<?php

namespace Tests\Feature;

use App\Http\Controllers\RendimientoCoordinadorController;
use App\Models\RendimientoCoordinador;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RendimientoCoordinadorRegistrationTest extends TestCase
{
    public function test_reporte_tiene_componentes_y_ruta_independientes(): void
    {
        $this->assertTrue(class_exists(RendimientoCoordinadorController::class));
        $this->assertTrue(class_exists(RendimientoCoordinador::class));
        $this->assertTrue(Route::has('incentivos.rendimiento-coordinador.index'));

        $route = Route::getRoutes()->getByName('incentivos.rendimiento-coordinador.index');

        $this->assertSame('incentivos/rendimiento-coordinador', $route->uri());
        $this->assertStringContainsString(RendimientoCoordinadorController::class, $route->getActionName());
    }

    public function test_reporte_esta_publicado_en_el_hub_de_incentivos(): void
    {
        $item = collect(config('module_hubs.incentivos.items'))
            ->firstWhere('url', '/incentivos/rendimiento-coordinador');

        $this->assertNotNull($item);
        $this->assertSame('Rendimiento de Coordinador', $item['nombre']);
        $this->assertTrue($item['activo']);
    }

    public function test_vista_renderiza_resultados_con_el_nombre_de_variable_correcto(): void
    {
        $html = view('incentivos.rendimiento-coordinador', [
            'coordinadores' => collect([
                [
                    'coordinador_id' => 1,
                    'coordinador' => 'Coordinador de prueba',
                    'agencias_asignadas' => 1,
                    'agencias_cumplieron' => 1,
                    'agencias_no_cumplieron' => 0,
                    'usuarios_cumplieron' => 1,
                    'usuarios_no_cumplieron' => 0,
                    'detalle_agencias_cumplieron' => [],
                    'detalle_agencias_no_cumplieron' => [],
                    'detalle_usuarios_cumplieron' => [],
                    'detalle_usuarios_no_cumplieron' => [],
                ],
            ]),
            'agenciasSinCoordinador' => collect(),
            'resumen' => [],
            'filtros' => [
                'fecha_inicio' => '2026-06-01',
                'fecha_fin' => '2026-06-30',
                'sistema' => 'Todos',
            ],
            'filtrosAplicados' => true,
        ])->render();

        $this->assertStringContainsString('Agencias sin coordinador asignado', $html);
        $this->assertStringContainsString('0 detectadas', $html);
        $this->assertStringContainsString('rc-detail-trigger', $html);
        $this->assertStringContainsString('modalDetalleRendimientoCoordinador', $html);
        $this->assertStringContainsString('Cumple regla', $html);
        $this->assertStringContainsString('Avance a la meta', $html);
        $this->assertStringNotContainsString('Venta mínima usuario', $html);
        $this->assertStringNotContainsString('Días mínimos', $html);
    }
}
