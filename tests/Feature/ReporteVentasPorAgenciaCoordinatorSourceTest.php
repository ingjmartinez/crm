<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReporteVentasPorAgenciaCoordinatorSourceTest extends TestCase
{
    public function test_report_uses_current_coordinator_assignments_instead_of_legacy_agency_text(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ReporteController.php'));
        $methodStart = strpos($controller, 'public function listVentasPorAgencia');
        $methodEnd = strpos($controller, 'public function ventasPorCedula', $methodStart);
        $method = substr($controller, $methodStart, $methodEnd - $methodStart);

        $this->assertStringContainsString('coordinador_operador_agencia', $method);
        $this->assertStringContainsString('JOIN coordinador_operador co', $method);
        $this->assertStringContainsString("co.puesto = 'coordinador'", $method);
        $this->assertStringContainsString("COALESCE(NULLIF(ca.coordinador, ''), 'Sin coordinador') AS coordinador", $method);
        $this->assertStringNotContainsString('a.coordinador AS coordinador', $method);
    }
}
