<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NominaDomingoAuditoriaTest extends TestCase
{
    public function test_audit_report_uses_the_same_admin_role_as_coordinator_audit(): void
    {
        $nomina = Route::getRoutes()->getByName('recursos-humanos.nomina-domingo.auditoria');
        $coordinadores = Route::getRoutes()->getByName('coordinador-operador.auditoria');

        $this->assertNotNull($nomina);
        $this->assertContains('role:superadmin|admin2', $nomina->gatherMiddleware());
        $this->assertContains('role:superadmin|admin2', $coordinadores->gatherMiddleware());
    }
}
