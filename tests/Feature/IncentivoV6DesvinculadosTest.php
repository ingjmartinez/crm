<?php

namespace Tests\Feature;

use App\Http\Controllers\IncentivosController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IncentivoV6DesvinculadosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::connection()->getPdo()->sqliteCreateFunction(
            'CONCAT',
            fn (...$values): string => implode('', $values),
            -1
        );

        Schema::dropIfExists('empleados');
        Schema::create('empleados', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('companyid');
            $table->unsignedInteger('empleadoid');
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('cedula');
            $table->boolean('activo')->default(true);
            $table->date('fechasalida')->nullable();
        });
    }

    public function test_cedula_is_not_unlinked_when_active_in_either_company(): void
    {
        DB::table('empleados')->insert([
            [
                'companyid' => 168,
                'empleadoid' => 1774,
                'nombres' => 'Ilandia Yanira',
                'apellidos' => 'Melo',
                'cedula' => '01100358512',
                'activo' => true,
                'fechasalida' => null,
            ],
            [
                'companyid' => 169,
                'empleadoid' => 3512,
                'nombres' => 'Ilandia Yabira',
                'apellidos' => 'Melo',
                'cedula' => '01100358512',
                'activo' => false,
                'fechasalida' => '2022-12-20',
            ],
        ]);

        $response = app(IncentivosController::class)->desvinculadosReporteNuevoIncentivoV5(
            Request::create('/incentivos/reporte-nuevo-incentivo-v5/desvinculados', 'POST', [
                'cedulas' => ['01100358512'],
            ])
        );

        $this->assertSame([
            'total_desvinculados' => 0,
            'total_desactivados' => 0,
            'total_con_fecha_salida' => 0,
            'data' => [],
        ], $response->getData(true));
    }

    public function test_cedula_is_unlinked_when_no_company_has_an_active_record(): void
    {
        DB::table('empleados')->insert([
            'companyid' => 169,
            'empleadoid' => 3512,
            'nombres' => 'Persona',
            'apellidos' => 'Desvinculada',
            'cedula' => '01100358512',
            'activo' => false,
            'fechasalida' => '2022-12-20',
        ]);

        $response = app(IncentivosController::class)->desvinculadosReporteNuevoIncentivoV5(
            Request::create('/incentivos/reporte-nuevo-incentivo-v5/desvinculados', 'POST', [
                'cedulas' => ['01100358512'],
            ])
        );
        $payload = $response->getData(true);

        $this->assertSame(1, $payload['total_desvinculados']);
        $this->assertSame(1, $payload['total_desactivados']);
        $this->assertSame(1, $payload['total_con_fecha_salida']);
        $this->assertSame('1100358512', $payload['data'][0]['cedula']);
    }

    public function test_global_active_rule_supports_current_employee_status_columns(): void
    {
        Schema::dropIfExists('empleados');
        Schema::create('empleados', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('companyid');
            $table->unsignedInteger('empleadoid');
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('cedula');
            $table->boolean('estatus')->default(true);
            $table->date('fecha_egreso')->nullable();
            $table->softDeletes();
        });
        DB::table('empleados')->insert([
            [
                'companyid' => 168,
                'empleadoid' => 1774,
                'nombres' => 'Ilandia Yanira',
                'apellidos' => 'Melo',
                'cedula' => '01100358512',
                'estatus' => true,
                'fecha_egreso' => null,
            ],
            [
                'companyid' => 169,
                'empleadoid' => 3512,
                'nombres' => 'Ilandia Yabira',
                'apellidos' => 'Melo',
                'cedula' => '01100358512',
                'estatus' => false,
                'fecha_egreso' => '2022-12-20',
            ],
        ]);

        $response = app(IncentivosController::class)->desvinculadosReporteNuevoIncentivoV5(
            Request::create('/incentivos/reporte-nuevo-incentivo-v5/desvinculados', 'POST', [
                'cedulas' => ['01100358512'],
            ])
        );

        $this->assertSame(0, $response->getData(true)['total_desvinculados']);
    }
}
