<?php

namespace Tests\Feature;

use App\Models\NominaDomingoAuditoria;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
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

    public function test_required_hours_are_displayed_with_two_decimal_places(): void
    {
        $auditoria = new NominaDomingoAuditoria([
            'accion' => 'modificado',
            'categoria' => 'configuracion',
            'usuario_nombre' => 'Auditora',
            'usuario_email' => 'auditora@example.com',
            'antes' => ['horas_requeridas' => 8.9833333333333, 'minutos_requeridos_doble_turno' => 350],
            'despues' => ['horas_requeridas' => 8.9833333333333, 'minutos_requeridos_doble_turno' => 351],
        ]);
        $auditoria->created_at = now();

        $template = file_get_contents(resource_path('views/recursos_humanos/nomina-domingo-auditoria.blade.php'));
        $template = str_replace(["@extends('app')", "@section('content')", '@endsection'], '', $template);
        $html = Blade::render($template, [
            'auditorias' => new LengthAwarePaginator([$auditoria], 1, 50),
            'buscar' => '',
        ]);

        $this->assertStringContainsString('Horas requeridas: 8.98', $html);
        $this->assertStringNotContainsString('8.9833333333333', $html);
        $this->assertStringContainsString('Tiempo doble turno: 5 h 50 min → 5 h 51 min', $html);
    }
}
