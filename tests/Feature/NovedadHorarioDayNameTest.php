<?php

namespace Tests\Feature;

use App\Http\Controllers\NovedadHorarioController;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

class NovedadHorarioDayNameTest extends TestCase
{
    public function test_detail_modal_formats_the_date_with_its_spanish_day_name(): void
    {
        $this->view('recursos_humanos.novedades_de_horario.index', [
            'ciudades' => collect(),
            'rutas' => collect(),
        ])
            ->assertSee("new Intl.DateTimeFormat('es-DO'", false)
            ->assertSee("weekday: 'long'", false)
            ->assertSee('formatearFechaConDia(item.fecha)', false);
    }

    public function test_table_search_targets_employee_identity_fields_and_normalizes_cedula(): void
    {
        $method = new ReflectionMethod(NovedadHorarioController::class, 'buildNovedadesHorarioQuery');
        $queryData = $method->invoke(
            new NovedadHorarioController,
            [
                'empresa' => 'todos',
                'fecha_inicio' => '2026-09-01',
                'fecha_fin' => '2026-09-30',
                'horas_requeridas' => 8,
            ],
            Request::create('/', 'GET', ['search' => ['value' => '001-123 4567']])
        );

        $this->assertStringContainsString('empleado_id LIKE ?', $queryData['whereSql']);
        $this->assertStringContainsString('nombre_empleado LIKE ?', $queryData['whereSql']);
        $this->assertStringContainsString('cedula LIKE ?', $queryData['whereSql']);
        $this->assertStringContainsString("REPLACE(REPLACE(cedula, '-', ''), ' ', '') LIKE ?", $queryData['whereSql']);
        $this->assertStringNotContainsString('terminal LIKE ?', $queryData['whereSql']);
        $this->assertSame(
            ['%001-123 4567%', '%001-123 4567%', '%001-123 4567%', '%0011234567%'],
            $queryData['whereBindings']
        );
    }
}
