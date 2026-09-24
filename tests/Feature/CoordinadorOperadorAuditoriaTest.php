<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CoordinadorOperadorAuditoriaTest extends TestCase
{
    public function test_audit_report_route_is_restricted_to_superadmin(): void
    {
        $route = Route::getRoutes()->getByName('coordinador-operador.auditoria');

        $this->assertNotNull($route);
        $this->assertContains('role:superadmin|admin2', $route->gatherMiddleware());
    }

    public function test_coordinator_page_only_shows_history_link_to_superadmin(): void
    {
        $view = file_get_contents(resource_path('views/coordinador_operador/index.blade.php'));

        $this->assertStringContainsString("@hasanyrole('superadmin|admin2')", $view);
        $this->assertStringContainsString("route('coordinador-operador.auditoria')", $view);
    }
}
