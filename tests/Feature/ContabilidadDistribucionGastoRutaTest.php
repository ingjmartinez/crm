<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContabilidadDistribucionGastoRutaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        $this->crearEsquema();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('distribucion_gasto_ruta_mapeos');
        Schema::dropIfExists('ruta_agencia');
        Schema::dropIfExists('centros_de_costo');
        Schema::dropIfExists('movimientos_rutas_v2_gastos');
        Schema::dropIfExists('agencias');
        Schema::dropIfExists('rutas');

        parent::tearDown();
    }

    public function test_distribuye_el_gasto_en_partes_iguales_por_agencia_y_agrupa_por_socio(): void
    {
        $rutaId = DB::table('rutas')->insertGetId([
            'nombre_ruta' => 'RUTA 05',
            'empresa' => 'Joselito',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (range(1, 10) as $indice) {
            $agenciaId = DB::table('agencias')->insertGetId([
                'agencia' => "AG-{$indice}",
                'nombre_agencia' => "Agencia {$indice}",
                'terminal' => (string) (1000 + $indice),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('ruta_agencia')->insert(['ruta_id' => $rutaId, 'agencia_id' => $agenciaId]);

            $socio = match (true) {
                $indice <= 4 => '1-Socio A',
                $indice <= 7 => '2-Socio B',
                default => '3-Socio C',
            };
            DB::table('centros_de_costo')->insert([
                'id_centro_costo' => $indice,
                'company_id' => '168',
                'id_viejo' => '00'.(1000 + $indice),
                'id_sub_grupo' => $socio,
                'inactivo' => false,
                'ocultar' => false,
            ]);
        }

        DB::table('movimientos_rutas_v2_gastos')->insert([
            'fecha' => '2026-08-10',
            'ruta_key' => 'RUTA 05 GJ',
            'ruta' => 'RUTA 05 GJ',
            'monto' => 5000,
            'concepto' => 'Combustible',
            'estado' => 'aplicado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson(route('operaciones.distribucion-gastos-ruta.data', [
            'fecha_ini' => '2026-08-01',
            'fecha_fin' => '2026-08-31',
            'empresa' => 'GJ',
        ]))->assertOk();

        $payload = $response->json();
        $socios = collect($payload['data'])->keyBy('socio');

        $this->assertSame(5000.0, (float) $payload['meta']['total_gastos']);
        $this->assertSame(5000.0, (float) $payload['meta']['total_asignado_socios']);
        $this->assertSame(0.0, (float) $payload['meta']['total_pendiente']);
        $this->assertSame(10, $payload['meta']['total_agencias']);
        $this->assertSame(3, $payload['meta']['total_socios']);
        $this->assertSame(4, $socios['Socio A']['agencias']);
        $this->assertSame(2000.0, (float) $socios['Socio A']['gasto_socio']);
        $this->assertSame(1500.0, (float) $socios['Socio B']['gasto_socio']);
        $this->assertSame(1500.0, (float) $socios['Socio C']['gasto_socio']);
        $this->assertSame(5000.0, (float) collect($payload['detalle'])->sum('gasto_agencia'));
    }

    public function test_conserva_el_monto_de_una_agencia_sin_socio_como_pendiente(): void
    {
        $rutaId = DB::table('rutas')->insertGetId(['nombre_ruta' => 'RUTA 01', 'empresa' => 'Negosur']);

        foreach ([2001, 2002] as $terminal) {
            $agenciaId = DB::table('agencias')->insertGetId([
                'agencia' => "AG-{$terminal}",
                'nombre_agencia' => "Agencia {$terminal}",
                'terminal' => (string) $terminal,
            ]);
            DB::table('ruta_agencia')->insert(['ruta_id' => $rutaId, 'agencia_id' => $agenciaId]);
        }

        DB::table('centros_de_costo')->insert([
            'id_centro_costo' => 20,
            'company_id' => '169',
            'id_viejo' => '002001',
            'id_sub_grupo' => '20-Socio Unico',
            'inactivo' => false,
            'ocultar' => false,
        ]);
        DB::table('movimientos_rutas_v2_gastos')->insert([
            'fecha' => '2026-08-11',
            'ruta_key' => 'RUTA 01 NG',
            'ruta' => 'RUTA 01 NG',
            'monto' => 100,
            'concepto' => 'Peaje',
            'estado' => 'aplicado',
        ]);

        $payload = $this->getJson(route('operaciones.distribucion-gastos-ruta.data', [
            'fecha_ini' => '2026-08-01',
            'fecha_fin' => '2026-08-31',
        ]))->assertOk()->json();

        $this->assertSame(50.0, (float) $payload['meta']['total_asignado_socios']);
        $this->assertSame(50.0, (float) $payload['meta']['total_pendiente']);
        $this->assertSame(1, $payload['meta']['total_incidencias']);
        $this->assertSame('sin_socio', $payload['incidencias'][0]['tipo']);
    }

    public function test_ajusta_los_centavos_para_que_el_detalle_cuadre_con_el_gasto(): void
    {
        $rutaId = DB::table('rutas')->insertGetId(['nombre_ruta' => 'RUTA 09', 'empresa' => 'Joselito']);

        foreach ([3001, 3002, 3003] as $indice => $terminal) {
            $agenciaId = DB::table('agencias')->insertGetId(['agencia' => "AG-{$terminal}", 'terminal' => (string) $terminal]);
            DB::table('ruta_agencia')->insert(['ruta_id' => $rutaId, 'agencia_id' => $agenciaId]);
            DB::table('centros_de_costo')->insert([
                'id_centro_costo' => 30 + $indice,
                'company_id' => '168',
                'id_viejo' => (string) $terminal,
                'id_sub_grupo' => '30-Socio Redondeo',
                'inactivo' => false,
                'ocultar' => false,
            ]);
        }

        DB::table('movimientos_rutas_v2_gastos')->insert([
            'fecha' => '2026-08-12', 'ruta_key' => 'RUTA 09', 'ruta' => 'RUTA 09',
            'monto' => 100, 'concepto' => 'Prueba', 'estado' => 'aplicado',
        ]);

        $payload = $this->getJson(route('operaciones.distribucion-gastos-ruta.data', [
            'fecha_ini' => '2026-08-01', 'fecha_fin' => '2026-08-31',
        ]))->assertOk()->json();

        $montos = collect($payload['detalle'])->pluck('gasto_agencia')->sortDesc()->values()->all();
        $this->assertSame([33.34, 33.33, 33.33], $montos);
        $this->assertSame(100.0, (float) collect($payload['detalle'])->sum('gasto_agencia'));
    }

    public function test_permite_relacionar_una_ruta_del_gasto_con_varios_socios_usando_id_grupo_e_id_sub_grupo(): void
    {
        DB::table('movimientos_rutas_v2_gastos')->insert([
            'fecha' => '2026-08-11', 'ruta_key' => 'TAMAYO', 'ruta' => 'Tamayo',
            'monto' => 600, 'concepto' => 'Combustible', 'estado' => 'aplicado',
        ]);

        foreach (range(1, 6) as $indice) {
            DB::table('centros_de_costo')->insert([
                'id_centro_costo' => 100 + $indice,
                'company_id' => '168-Grupo Joselito',
                'id_grupo' => '61-Ruta Contable Tamayo',
                'id_sub_grupo' => $indice <= 4 ? '45-Socio A' : '46-Socio B',
                'id_viejo' => (string) (5000 + $indice),
                'descripcion' => "Agencia {$indice}",
                'inactivo' => false,
                'ocultar' => false,
            ]);
        }

        $this->get(route('operaciones.distribucion-gastos-ruta'))
            ->assertOk()
            ->assertSee('Tamayo');
        $this->postJson(route('operaciones.distribucion-gastos-ruta.mapeos.store'), [
            'ruta_key' => 'TAMAYO', 'id_grupo' => '61', 'id_sub_grupo' => '45',
        ])->assertUnprocessable()->assertJsonValidationErrors('company_id');

        $this->postJson(route('operaciones.distribucion-gastos-ruta.mapeos.store'), [
            'ruta_key' => 'TAMAYO', 'id_grupo' => '61', 'id_sub_grupo' => '45', 'company_id' => '168',
        ])->assertOk()->assertJsonPath('terminales', 4);
        $this->postJson(route('operaciones.distribucion-gastos-ruta.mapeos.store'), [
            'ruta_key' => 'TAMAYO', 'id_grupo' => '61', 'id_sub_grupo' => '46', 'company_id' => '168',
        ])->assertOk()->assertJsonPath('terminales', 2);

        $this->postJson(route('operaciones.distribucion-gastos-ruta.mapeos.store'), [
            'ruta_key' => 'TAMAYO', 'id_grupo' => '61', 'id_sub_grupo' => '99', 'company_id' => '168',
        ])->assertUnprocessable()->assertJsonValidationErrors('id_sub_grupo');

        $payload = $this->getJson(route('operaciones.distribucion-gastos-ruta.data', [
            'fecha_ini' => '2026-08-01', 'fecha_fin' => '2026-08-31',
        ]))->assertOk()->json();
        $socios = collect($payload['data'])->keyBy('socio');

        $this->assertSame(6, $payload['meta']['total_agencias']);
        $this->assertSame(600.0, (float) $payload['meta']['total_asignado_socios']);
        $this->assertSame(4, $socios['Socio A']['agencias']);
        $this->assertSame(400.0, (float) $socios['Socio A']['gasto_socio']);
        $this->assertSame(200.0, (float) $socios['Socio B']['gasto_socio']);

        $pdf = $this->get(route('operaciones.distribucion-gastos-ruta.pdf', [
            'fecha_ini' => '2026-08-01',
            'fecha_fin' => '2026-08-31',
            'empresa' => 'todas',
            'ruta_key' => 'TAMAYO',
        ]))->assertOk();

        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringContainsString('distribucion_gastos_tamayo', (string) $pdf->headers->get('content-disposition'));
    }

    public function test_el_reporte_esta_registrado_en_el_modulo_de_operaciones(): void
    {
        $this->get(route('operaciones.distribucion-gastos-ruta'))
            ->assertOk()
            ->assertViewIs('contabilidad.reportes.distribucion-gastos-ruta');

        $item = collect(config('module_hubs.operaciones.items'))
            ->firstWhere('nombre', 'Distribucion de Gastos de Ruta');

        $this->assertNotNull($item);
        $this->assertSame('/operaciones/distribucion-gastos-ruta', $item['url']);
        $this->assertNull(
            collect(config('module_hubs.contabilidad.items'))->firstWhere('nombre', 'Distribucion de Gastos de Ruta')
        );
    }

    private function crearEsquema(): void
    {
        Schema::create('rutas', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_ruta');
            $table->string('empresa')->nullable();
            $table->unsignedBigInteger('operador_ruta_id')->nullable();
            $table->timestamps();
        });
        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('agencia')->nullable();
            $table->string('nombre_agencia')->nullable();
            $table->string('terminal')->nullable();
            $table->timestamps();
        });
        Schema::create('ruta_agencia', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ruta_id');
            $table->unsignedBigInteger('agencia_id');
            $table->timestamps();
        });
        Schema::create('movimientos_rutas_v2_gastos', function (Blueprint $table): void {
            $table->id();
            $table->date('fecha');
            $table->string('ruta_key');
            $table->string('ruta');
            $table->decimal('monto', 15, 2);
            $table->string('concepto');
            $table->string('estado')->default('aplicado');
            $table->timestamps();
        });
        Schema::create('centros_de_costo', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_centro_costo');
            $table->string('company_id')->nullable();
            $table->string('id_viejo')->nullable();
            $table->string('id_grupo')->nullable();
            $table->string('id_sub_grupo')->nullable();
            $table->string('descripcion')->nullable();
            $table->boolean('inactivo')->default(false);
            $table->boolean('ocultar')->default(false);
            $table->timestamps();
        });
        Schema::create('distribucion_gasto_ruta_mapeos', function (Blueprint $table): void {
            $table->id();
            $table->string('ruta_key');
            $table->string('ruta_nombre');
            $table->string('company_id');
            $table->string('id_grupo');
            $table->string('nombre_grupo');
            $table->string('id_sub_grupo');
            $table->string('nombre_socio');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->unique(['ruta_key', 'company_id', 'id_grupo', 'id_sub_grupo']);
        });
    }
}
