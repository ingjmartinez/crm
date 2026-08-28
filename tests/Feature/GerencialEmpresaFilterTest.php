<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GerencialEmpresaFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal')->nullable();
            $table->string('nombre_agencia')->nullable();
            $table->string('empresa')->nullable();
            $table->string('ciudad')->nullable();
        });

        foreach (['vt_usuarios_bet', 'vt_usuarios_net'] as $tabla) {
            Schema::create($tabla, function (Blueprint $table): void {
                $table->id();
                $table->string('agencia_id');
                $table->decimal('monto', 14, 2);
                $table->dateTime('fecha');
            });
        }
    }

    public function test_filtra_el_reporte_gerencial_por_empresa(): void
    {
        DB::table('agencias')->insert([
            ['terminal' => '001001', 'nombre_agencia' => 'Agencia Empresa A', 'empresa' => 'Empresa A', 'ciudad' => 'Santo Domingo'],
            ['terminal' => '2001', 'nombre_agencia' => 'Agencia Empresa B', 'empresa' => 'Empresa B', 'ciudad' => 'Santiago'],
        ]);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '1001', 'monto' => 200000, 'fecha' => '2026-01-10 00:00:00'],
            ['agencia_id' => '1001', 'monto' => 350000, 'fecha' => '2026-02-10 00:00:00'],
            ['agencia_id' => '2001', 'monto' => 80000, 'fecha' => '2026-01-10 00:00:00'],
            ['agencia_id' => '2001', 'monto' => 120000, 'fecha' => '2026-02-10 00:00:00'],
        ]);

        $response = $this->withoutMiddleware()->getJson(route('gerencia.gerencial.data', [
            'anio' => 2026,
            'mes_inicio' => 1,
            'mes_fin' => 2,
            'empresa' => 'Empresa A',
        ]));

        $response->assertOk()
            ->assertJsonPath('meta.empresa', 'Empresa A')
            ->assertJsonCount(1, 'transiciones_agencias_detalle')
            ->assertJsonPath('transiciones_agencias_detalle.0.codigo_agencia', '1001')
            ->assertJsonPath('transiciones_agencias_detalle.0.nombre_agencia', 'Agencia Empresa A')
            ->assertJsonPath('transiciones_agencias_detalle.0.ciudad', 'Santo Domingo')
            ->assertJsonMissing(['codigo_agencia' => '2001']);

        $filas = collect($response->json('data'))->keyBy('clasificacion');
        $this->assertSame(1, $filas['A']['conteo_mes_inicio']);
        $this->assertSame(1, $filas['AA']['conteo_mes_fin']);
    }

    public function test_muestra_las_empresas_disponibles_y_conserva_la_seleccion(): void
    {
        DB::table('agencias')->insert([
            ['terminal' => '1001', 'nombre_agencia' => 'Agencia A', 'empresa' => 'Empresa A'],
            ['terminal' => '2001', 'nombre_agencia' => 'Agencia B', 'empresa' => 'Empresa B'],
        ]);

        $this->withoutMiddleware()->get(route('gerencia.gerencial', ['empresa' => 'Empresa B']))
            ->assertOk()
            ->assertViewHas('empresaSeleccionada', 'Empresa B')
            ->assertViewHas('empresas', fn ($empresas): bool => $empresas->all() === ['Empresa A', 'Empresa B'])
            ->assertSee('Todas las empresas')
            ->assertSee('Empresa B')
            ->assertSee('Ciudad');
    }

    public function test_muestra_un_texto_seguro_cuando_la_agencia_no_tiene_ciudad(): void
    {
        DB::table('agencias')->insert([
            'terminal' => '3001',
            'nombre_agencia' => 'Agencia sin ciudad',
            'empresa' => 'Empresa C',
            'ciudad' => null,
        ]);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '3001', 'monto' => 200000, 'fecha' => '2026-01-10 00:00:00'],
            ['agencia_id' => '3001', 'monto' => 350000, 'fecha' => '2026-02-10 00:00:00'],
        ]);

        $this->withoutMiddleware()->getJson(route('gerencia.gerencial.data', [
            'anio' => 2026,
            'mes_inicio' => 1,
            'mes_fin' => 2,
            'empresa' => 'Empresa C',
        ]))->assertOk()
            ->assertJsonPath('transiciones_agencias_detalle.0.ciudad', 'Sin ciudad registrada');
    }

    public function test_rechaza_un_nombre_de_empresa_demasiado_largo(): void
    {
        $this->withoutMiddleware()->getJson(route('gerencia.gerencial.data', [
            'empresa' => str_repeat('A', 151),
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('empresa');
    }
}
