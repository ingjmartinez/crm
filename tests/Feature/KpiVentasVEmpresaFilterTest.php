<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KpiVentasVEmpresaFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal')->nullable();
            $table->string('empresa')->nullable();
        });

        Schema::create('vt_usuarios_bet', function (Blueprint $table): void {
            $table->id();
            $table->string('agencia_id');
            $table->string('tipo');
            $table->decimal('monto', 14, 2);
            $table->date('fecha');
        });
    }

    public function test_filtra_todos_los_indicadores_por_empresa(): void
    {
        DB::table('agencias')->insert([
            ['terminal' => '001001', 'empresa' => 'Empresa A'],
            ['terminal' => '2001', 'empresa' => 'Empresa B'],
        ]);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '1001', 'tipo' => 'Tradicional', 'monto' => 100, 'fecha' => '2026-08-27'],
            ['agencia_id' => '1001', 'tipo' => 'Recargas', 'monto' => 25, 'fecha' => '2026-08-27'],
            ['agencia_id' => '2001', 'tipo' => 'Tradicional', 'monto' => 900, 'fecha' => '2026-08-27'],
        ]);

        $this->withoutMiddleware()->get(route('comercial.kpi-ventas-v', [
            'fecha' => '2026-08-27',
        ]))->assertViewHas('kpis', fn (array $kpis): bool => $kpis['tradicional'] === 1000.0);

        $response = $this->withoutMiddleware()->get(route('comercial.kpi-ventas-v', [
            'fecha' => '2026-08-27',
            'empresa' => 'Empresa A',
        ]));

        $response->assertOk()
            ->assertViewHas('empresaSeleccionada', 'Empresa A')
            ->assertViewHas('empresas', fn ($empresas): bool => $empresas->all() === ['Empresa A', 'Empresa B'])
            ->assertSee('Todas las empresas')
            ->assertSee('Empresa A');

        $this->assertSame([
            'tradicional' => 100.0,
            'no_tradicional' => 0.0,
            'recargas' => 25.0,
        ], $response->viewData('kpis'));
        $this->assertSame(125.0, $response->viewData('comparativasTabla')[0]['total_general']);
        $this->assertSame(1, $response->viewData('comparativasTabla')[0]['total_agencias_con_venta']);
    }

    public function test_rechaza_un_nombre_de_empresa_demasiado_largo(): void
    {
        $this->withoutMiddleware()->get(route('comercial.kpi-ventas-v', [
            'empresa' => str_repeat('A', 151),
        ]))->assertSessionHasErrors('empresa');
    }

    public function test_una_empresa_sin_terminales_no_muestra_ventas(): void
    {
        DB::table('agencias')->insert([
            ['terminal' => '1001', 'empresa' => 'Empresa A'],
        ]);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '1001', 'tipo' => 'Tradicional', 'monto' => 100, 'fecha' => '2026-08-27'],
        ]);

        $this->withoutMiddleware()->get(route('comercial.kpi-ventas-v', [
            'fecha' => '2026-08-27',
            'empresa' => 'Empresa inexistente',
        ]))->assertOk()
            ->assertViewHas('kpis', fn (array $kpis): bool => array_sum($kpis) === 0.0);

    }
}
