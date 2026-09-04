<?php

namespace Tests\Feature;

use App\Exports\VentasUsuarioExport;
use Tests\TestCase;

class ReporteVentasUsuarioBetCoordinatorFilterTest extends TestCase
{
    public function test_view_contains_coordinator_filter_and_sends_it_to_requests(): void
    {
        $html = view('reportes.ventas-usuario-bet', [
            'coordinadores' => collect([
                (object) ['id' => 7, 'nombre' => 'Ana', 'apellido' => 'Pérez'],
            ]),
        ])->render();

        $this->assertStringContainsString('id="coordinador"', $html);
        $this->assertStringContainsString('value="7"', $html);
        $this->assertStringContainsString('Ana Pérez', $html);
        $this->assertStringContainsString("coordinador: document.getElementById('coordinador').value", $html);
    }

    public function test_excel_query_filters_sales_by_current_coordinator_assignments(): void
    {
        $query = (new VentasUsuarioExport(
            empresa: 'todos',
            fechaInicio: '2026-09-01',
            fechaFin: '2026-09-30',
            coordinadorId: 7,
        ))->query();

        $sql = $query->toSql();

        $this->assertStringContainsString('coordinador_operador_agencia', $sql);
        $this->assertStringContainsString('coordinador_operador', $sql);
        $this->assertContains(7, $query->getBindings());
    }

    public function test_list_endpoint_uses_the_same_coordinator_assignment_filter(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ReporteController.php'));
        $methodStart = strpos($controller, 'public function listVentasUsuarioBet');
        $methodEnd = strpos($controller, 'public function excelVentasUsuarioBet', $methodStart);
        $method = substr($controller, $methodStart, $methodEnd - $methodStart);

        $this->assertStringContainsString("\$validated['coordinador']", $method);
        $this->assertStringContainsString('coordinador_operador_agencia as coa_filter', $method);
        $this->assertStringContainsString("where('co_filter.id', \$coordinadorId)", $method);
    }
}
