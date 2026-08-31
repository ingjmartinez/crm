<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InicioDashboardVentasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Schema::dropIfExists('vt_usuarios_net');
        Schema::dropIfExists('vt_usuarios_bet');
        Schema::dropIfExists('catalogo_juegos');
        Schema::dropIfExists('agencias');

        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal');
            $table->string('nombre_agencia')->nullable();
            $table->string('agencia')->nullable();
            $table->string('empresa')->nullable();
            $table->boolean('estatus')->default(true);
        });
        Schema::create('catalogo_juegos', function (Blueprint $table): void {
            $table->id();
            $table->string('producto_id');
            $table->string('tipo');
            $table->string('descripcion');
        });

        foreach (['vt_usuarios_bet', 'vt_usuarios_net'] as $tabla) {
            Schema::create($tabla, function (Blueprint $table): void {
                $table->id();
                $table->string('agencia_id');
                $table->string('producto_id');
                $table->string('tipo')->nullable();
                $table->decimal('monto', 14, 2);
                $table->dateTime('fecha');
            });
        }
    }

    public function test_includes_all_sales_but_only_active_agencies_without_sales(): void
    {
        DB::table('agencias')->insert([
            ['terminal' => '100', 'nombre_agencia' => 'Activa sin ventas', 'empresa' => 'Empresa Uno', 'estatus' => 1],
            ['terminal' => '200', 'nombre_agencia' => 'Activa con ventas', 'empresa' => 'Empresa Uno', 'estatus' => 1],
            ['terminal' => '300', 'nombre_agencia' => 'Inactiva con ventas', 'empresa' => 'Empresa Uno', 'estatus' => 0],
            ['terminal' => '400', 'nombre_agencia' => 'Inactiva sin ventas', 'empresa' => 'Empresa Uno', 'estatus' => 0],
        ]);
        DB::table('catalogo_juegos')->insert([
            'producto_id' => '1',
            'tipo' => 'Tradicional',
            'descripcion' => 'Tradicional',
        ]);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '200', 'producto_id' => '1', 'tipo' => 'Tradicional', 'monto' => 50, 'fecha' => '2026-08-15 10:00:00'],
            ['agencia_id' => '300', 'producto_id' => '1', 'tipo' => 'Tradicional', 'monto' => 100, 'fecha' => '2026-08-15 11:00:00'],
            ['agencia_id' => '999', 'producto_id' => '1', 'tipo' => 'Tradicional', 'monto' => 25, 'fecha' => '2026-08-15 12:00:00'],
        ]);

        $response = $this->withoutMiddleware()->getJson(route('inicio.ventas-data', [
            'fecha' => '2026-08-15',
            'empresa' => 'todos',
        ]));

        $response->assertOk()
            ->assertJsonPath('ventas.total_general', 175)
            ->assertJsonPath('ventas.tipos.tradicional.total', 175)
            ->assertJsonPath('ventas.agencias_con_venta', 3)
            ->assertJsonPath('ventas.agencias_sin_venta', 1)
            ->assertJsonCount(1, 'ventas.agencias_sin_ventas')
            ->assertJsonPath('ventas.agencias_sin_ventas.0.terminal', '100')
            ->assertJsonPath('ventas.balance_mensual.ingresos.14', 175);

        $this->withoutMiddleware()->getJson(route('inicio.ventas-data', [
            'fecha' => '2026-08-15',
            'empresa' => 'Empresa Uno',
        ]))
            ->assertOk()
            ->assertJsonPath('ventas.total_general', 150)
            ->assertJsonPath('ventas.agencias_con_venta', 2)
            ->assertJsonPath('ventas.agencias_sin_venta', 1)
            ->assertJsonPath('ventas.balance_mensual.ingresos.14', 150);
    }
}
