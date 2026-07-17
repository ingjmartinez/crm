<?php

namespace Tests\Feature;

use App\Services\Incentivos\RendimientoCoordinadorReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RendimientoCoordinadorIntegralTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('coordinador_operador', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('apellido')->nullable();
            $table->string('puesto');
        });
        Schema::create('agencias', function (Blueprint $table) {
            $table->id();
            $table->string('terminal')->nullable();
            $table->string('nombre_agencia')->nullable();
            $table->string('agencia')->nullable();
            $table->string('empresa')->nullable();
            $table->string('sistema')->nullable();
            $table->integer('estatus')->default(1);
        });
        Schema::create('coordinador_operador_agencia', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coordinador_operador_id');
            $table->unsignedBigInteger('agencia_id');
        });
        foreach (['vt_usuarios_bet', 'vt_usuarios_net'] as $tabla) {
            Schema::create($tabla, function (Blueprint $table) {
                $table->id();
                $table->string('agencia_id')->nullable();
                $table->string('cedula')->nullable();
                $table->decimal('monto', 12, 2)->nullable();
                $table->date('fecha')->nullable();
            });
        }
        Schema::create('empleados', function (Blueprint $table) {
            $table->id();
            $table->string('cedula')->nullable();
            $table->string('nombres')->nullable();
            $table->string('apellidos')->nullable();
        });

        DB::table('coordinador_operador')->insert([
            'id' => 1,
            'nombre' => 'Ana',
            'apellido' => 'Coordinadora',
            'puesto' => 'coordinador',
        ]);
        DB::table('agencias')->insert([
            ['id' => 10, 'terminal' => '001', 'nombre_agencia' => 'Agencia Uno', 'empresa' => 'Empresa', 'sistema' => 'Lotobet', 'estatus' => 1],
            ['id' => 11, 'terminal' => '002', 'nombre_agencia' => 'Agencia Dos', 'empresa' => 'Empresa', 'sistema' => 'Lotobet', 'estatus' => 1],
        ]);
        DB::table('coordinador_operador_agencia')->insert([
            ['coordinador_operador_id' => 1, 'agencia_id' => 10],
            ['coordinador_operador_id' => 1, 'agencia_id' => 11],
        ]);
        DB::table('empleados')->insert([
            ['cedula' => '00101', 'nombres' => 'Usuario', 'apellidos' => 'Meta'],
            ['cedula' => '00202', 'nombres' => 'Usuario', 'apellidos' => 'Seguimiento'],
        ]);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '001', 'cedula' => '00101', 'monto' => 100001, 'fecha' => '2026-06-01'],
            ['agencia_id' => '001', 'cedula' => '00202', 'monto' => 50000, 'fecha' => '2026-06-02'],
            ['agencia_id' => '001', 'cedula' => '00101', 'monto' => 50000, 'fecha' => '2026-05-31'],
        ]);
    }

    public function test_reporte_integral_respeta_la_cascada_y_compara_periodos(): void
    {
        $reporte = app(RendimientoCoordinadorReportService::class)->generar(1, [
            'fecha_inicio' => '2026-06-01',
            'fecha_fin' => '2026-06-02',
            'sistema' => 'Lotobet',
        ]);

        $this->assertSame('Ana Coordinadora', $reporte['meta']['coordinador']);
        $this->assertSame(150001.0, $reporte['resumen']['venta_total']);
        $this->assertSame(2, $reporte['resumen']['agencias_asignadas']);
        $this->assertSame(1, $reporte['resumen']['agencias_con_ventas']);
        $this->assertSame(1, $reporte['resumen']['agencias_sin_ventas']);
        $this->assertSame(2, $reporte['resumen']['usuarios_vendedores']);
        $this->assertSame(1, $reporte['resumen']['usuarios_cumplieron']);
        $this->assertSame(1000.0, $reporte['resumen']['incentivo_total']);
        $this->assertSame(50000.0, $reporte['comparacion']['venta_anterior']);
        $this->assertSame(200.0, $reporte['comparacion']['variacion_pct']);
        $this->assertSame('Agencia Uno', $reporte['agencias'][0]['agencia']);
        $this->assertSame('Cumple', $reporte['usuarios'][0]['clasificacion']);
        $this->assertCount(2, $reporte['tendencia']);
    }

    public function test_rutas_y_vista_del_dashboard_integral_estan_publicadas(): void
    {
        $this->assertTrue(Route::has('incentivos.rendimiento-coordinador.detalle'));
        $this->assertTrue(Route::has('incentivos.rendimiento-coordinador.pdf'));
        $this->assertTrue(Route::has('incentivos.rendimiento-coordinador.excel'));

        $html = view('incentivos.rendimiento-coordinador', [
            'coordinadores' => collect([[
                'coordinador_id' => 1,
                'coordinador' => 'Ana Coordinadora',
                'agencias_asignadas' => 2,
                'agencias_cumplieron' => 1,
                'agencias_no_cumplieron' => 1,
                'usuarios_cumplieron' => 1,
                'usuarios_no_cumplieron' => 1,
                'detalle_agencias_cumplieron' => [],
                'detalle_agencias_no_cumplieron' => [],
                'detalle_usuarios_cumplieron' => [],
                'detalle_usuarios_no_cumplieron' => [],
            ]]),
            'agenciasSinCoordinador' => collect(),
            'resumen' => [],
            'filtros' => ['fecha_inicio' => '2026-06-01', 'fecha_fin' => '2026-06-02', 'sistema' => 'Lotobet'],
            'filtrosAplicados' => true,
        ])->render();

        $this->assertStringContainsString('modalReporteIntegralCoordinador', $html);
        $this->assertStringContainsString('PDF ejecutivo', $html);
        $this->assertStringContainsString('Excel detallado', $html);
        $this->assertStringContainsString('Tendencia y comparaci', $html);
    }
}
