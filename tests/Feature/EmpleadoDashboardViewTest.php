<?php

namespace Tests\Feature;

use Tests\TestCase;

class EmpleadoDashboardViewTest extends TestCase
{
    public function test_employee_dashboard_keeps_filters_actions_and_chart_containers(): void
    {
        $html = view('empleado.index')->render();

        foreach (['empresa', 'cedulaSincronizar', 'btnRefrescarDashboard', 'btnSincronizar', 'tableEmpleados', 'chartEstadoEmpleados', 'chartSalarioCiudad', 'chartEmpleadosCiudad', 'chartSalarioEmpresa'] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html);
        }

        $this->assertStringContainsString('Panorama de empleados', $html);
        $this->assertStringContainsString('dashboardActualizado', $html);
        $this->assertStringContainsString('deferRender', $html);
        $this->assertStringContainsString('empleadosExportUrl', $html);
        $this->assertStringContainsString('empleados.ventas-bet-sin-maestra', file_get_contents(resource_path('views/empleado/index.blade.php')));
    }
}
