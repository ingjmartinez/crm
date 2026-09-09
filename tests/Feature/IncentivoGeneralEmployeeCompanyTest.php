<?php

namespace Tests\Feature;

use App\Http\Controllers\IncentivosController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IncentivoGeneralEmployeeCompanyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::connection()->getPdo()->sqliteCreateFunction(
            'CONCAT',
            fn (...$values): string => implode('', $values),
            -1
        );

        Schema::dropIfExists('vt_usuarios_bet');
        Schema::dropIfExists('vt_usuarios_net');
        Schema::dropIfExists('agencias');
        Schema::dropIfExists('empleados');

        Schema::create('vt_usuarios_bet', function (Blueprint $table): void {
            $table->id();
            $table->string('cedula');
            $table->string('agencia_id');
            $table->date('fecha');
            $table->decimal('monto', 12, 2);
        });

        Schema::create('vt_usuarios_net', function (Blueprint $table): void {
            $table->id();
            $table->string('cedula');
            $table->string('agencia_id');
            $table->date('fecha');
            $table->decimal('monto', 12, 2);
        });

        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal');
            $table->string('empresa');
            $table->string('nombre_agencia')->nullable();
            $table->string('agencia')->nullable();
        });

        Schema::create('empleados', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('companyid');
            $table->unsignedInteger('empleadoid');
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('cedula');
            $table->string('viapago')->nullable();
            $table->string('ciudad')->nullable();
            $table->date('fechasalida')->nullable();
        });
    }

    public function test_general_report_uses_employee_from_the_sales_company(): void
    {
        DB::table('agencias')->insert([
            'terminal' => '051269',
            'empresa' => 'Grupo Joselito',
            'nombre_agencia' => 'Soribel',
        ]);

        DB::table('vt_usuarios_bet')->insert([
            'cedula' => '01100358512',
            'agencia_id' => '051269',
            'fecha' => '2026-07-15',
            'monto' => 134849,
        ]);

        DB::table('empleados')->insert([
            [
                'companyid' => 168,
                'empleadoid' => 1774,
                'nombres' => 'Ilandia Yanira',
                'apellidos' => 'Melo',
                'cedula' => '01100358512',
                'viapago' => 'Transferencia',
                'ciudad' => 'Santo Domingo',
                'fechasalida' => null,
            ],
            [
                'companyid' => 169,
                'empleadoid' => 3512,
                'nombres' => 'Ilandia Yabira',
                'apellidos' => 'Melo',
                'cedula' => '01100358512',
                'viapago' => 'Transferencia',
                'ciudad' => 'San Cristobal',
                'fechasalida' => '2022-12-20',
            ],
        ]);

        $response = app(IncentivosController::class)->reporteNuevoIncentivoV2(
            Request::create('/incentivos/reporte-nuevo-incentivo-v2', 'GET', [
                'fecha_ini' => '2026-07-01',
                'fecha_fin' => '2026-07-31',
                'sistema' => 'Todos',
                'min_dias_venta' => 1,
                'filtro_cumplimiento' => 'todos',
            ])
        );

        $row = collect($response->getData(true)['data'])->firstWhere('cedula', '01100358512');

        $this->assertNotNull($row);
        $this->assertSame('Grupo Joselito', $row['empresa']);
        $this->assertSame('1774', $row['empleadoid']);
        $this->assertSame('Ilandia Yanira Melo', $row['nombre']);
        $this->assertSame('Santo Domingo', $row['ciudad']);
        $this->assertSame('1,000.00', $row['nuevo_incentivo']);
    }
}
