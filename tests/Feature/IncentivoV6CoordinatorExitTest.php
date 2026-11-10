<?php

namespace Tests\Feature;

use App\Http\Controllers\IncentivosController;
use App\Http\Controllers\IncentivoV6Controller;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Tests\TestCase;

class IncentivoV6CoordinatorExitTest extends TestCase
{
    public function test_coordinators_are_flagged_by_cedula_when_any_employee_record_has_an_exit(): void
    {
        Schema::create('empleados', function (Blueprint $table): void {
            $table->id();
            $table->string('cedula')->nullable();
            $table->string('fechasalida')->nullable();
        });

        DB::table('empleados')->insert([
            ['cedula' => '001-1111111-1', 'fechasalida' => '2026-10-01'],
            ['cedula' => '00111111111', 'fechasalida' => null],
            ['cedula' => '00222222222', 'fechasalida' => null],
            ['cedula' => '00333333333', 'fechasalida' => ''],
            ['cedula' => '00444444444', 'fechasalida' => '0000-00-00'],
            ['cedula' => '', 'fechasalida' => '2026-10-01'],
        ]);

        $coordinadores = collect([
            ['id' => 1, 'cedula' => '001.1111111.1', 'monto_usuarios' => 100],
            ['id' => 2, 'cedula' => '002-2222222-2', 'monto_usuarios' => 200],
            ['id' => 3, 'cedula' => '00333333333', 'monto_usuarios' => 300],
            ['id' => 4, 'cedula' => '00444444444', 'monto_usuarios' => 400],
            ['id' => 5, 'cedula' => '00555555555', 'monto_usuarios' => 500],
            ['id' => 6, 'cedula' => '', 'monto_usuarios' => 600],
        ]);

        $this->mock(IncentivosController::class, function (MockInterface $mock) use ($coordinadores): void {
            $mock->shouldReceive('reporteNuevoIncentivoV5View')->once()
                ->andReturn(view('incentivos.reporte-nuevo-incentivo-v5', compact('coordinadores')));
        });

        $view = app(IncentivoV6Controller::class)->index();
        $rows = $view->getData()['coordinadores'];

        $this->assertSame('incentivos.reporte-nuevo-incentivo-v6', $view->name());
        $this->assertSame([true, false, false, false, false, false], $rows->pluck('cedula_con_salida')->all());
        $this->assertSame($coordinadores->all(), $rows->map(function (array $row): array {
            unset($row['cedula_con_salida']);

            return $row;
        })->all());
    }

    public function test_coordinator_modal_marks_exit_rows_in_blue_and_keeps_retention_controls(): void
    {
        $template = file_get_contents(resource_path('views/incentivos/reporte-nuevo-incentivo-v6.blade.php'));

        $this->assertStringContainsString('#tbodyCoordinadores > tr.coordinador-con-salida > td', $template);
        $this->assertStringContainsString('background-color: #e7f5ff !important;', $template);
        $this->assertStringContainsString("tr.classList.toggle('coordinador-con-salida', row.cedula_con_salida === true)", $template);
        $this->assertStringContainsString('Salida en maestra', $template);
        $this->assertStringContainsString("tr.classList.toggle('table-warning', excluded)", $template);
    }
}
