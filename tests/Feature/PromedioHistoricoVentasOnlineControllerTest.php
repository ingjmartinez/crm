<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PromedioHistoricoVentasOnlineControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        Schema::dropIfExists('ventas_online_promedios_historicos');
        Schema::dropIfExists('vt_usuarios_bet');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('vt_usuarios_bet', function (Blueprint $table): void {
            $table->id('vt_usuario_id');
            $table->string('agencia_id');
            $table->string('tipo')->nullable();
            $table->string('producto_id')->nullable();
            $table->string('descripcion')->nullable();
            $table->decimal('monto', 14, 2);
            $table->date('fecha');
        });

        Schema::create('ventas_online_promedios_historicos', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo_categoria')->unique();
            $table->decimal('monto_promedio', 14, 2)->default(0);
            $table->json('meses_incluidos');
            $table->timestamp('calculado_en');
            $table->foreignId('calculado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function test_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('gerencia.ventas-online.promedio-historico.show'));
        $this->assertTrue(Route::has('gerencia.ventas-online.promedio-historico.calcular'));
    }

    public function test_show_returns_an_empty_list_before_any_calculation(): void
    {
        $response = $this->getJson(route('gerencia.ventas-online.promedio-historico.show'));

        $response->assertOk();
        $response->assertExactJson(['promedios' => []]);
    }

    public function test_calcular_persists_and_returns_the_daily_average_for_both_types(): void
    {
        $diasPeriodo = collect(range(1, 3))
            ->sum(fn (int $mesesAtras): int => now()->subMonthsNoOverflow($mesesAtras)->daysInMonth);
        DB::table('vt_usuarios_bet')->insert([
            ['agencia_id' => '1', 'tipo' => 'tradicional', 'producto_id' => '1', 'descripcion' => 'X', 'monto' => 900, 'fecha' => now()->subMonthsNoOverflow(2)->startOfMonth()->toDateString()],
            ['agencia_id' => '1', 'tipo' => 'no tradicional', 'producto_id' => '2', 'descripcion' => 'Y', 'monto' => 90, 'fecha' => now()->subMonthsNoOverflow(2)->startOfMonth()->toDateString()],
        ]);

        $response = $this->postJson(route('gerencia.ventas-online.promedio-historico.calcular'));

        $response->assertOk();
        $response->assertJsonCount(2, 'promedios');
        $response->assertJsonFragment([
            'tipo_categoria' => 'tradicional',
            'monto_promedio' => round(900 / $diasPeriodo, 2),
            'dias_promediados' => $diasPeriodo,
        ]);
        $response->assertJsonFragment([
            'tipo_categoria' => 'no_tradicional',
            'monto_promedio' => round(90 / $diasPeriodo, 2),
            'dias_promediados' => $diasPeriodo,
        ]);

        // El frontend usa Array.isArray(data.promedios) para decidir si pinta algo: si el JSON
        // saliera como objeto ({"tradicional": ...}) en vez de lista ([{...}, {...}]), la vista
        // se quedaria en "Sin calcular" aunque el calculo si se guardo en la base de datos.
        $this->assertTrue(array_is_list($response->json('promedios')));

        $this->assertSame(2, DB::table('ventas_online_promedios_historicos')->count());
    }

    public function test_show_after_calcular_returns_the_stored_value_without_recalculating(): void
    {
        DB::table('vt_usuarios_bet')->insert([
            'agencia_id' => '1', 'tipo' => 'tradicional', 'producto_id' => '1', 'descripcion' => 'X', 'monto' => 900,
            'fecha' => now()->subMonthsNoOverflow(2)->startOfMonth()->toDateString(),
        ]);

        $this->postJson(route('gerencia.ventas-online.promedio-historico.calcular'))->assertOk();

        // Se agregan mas ventas, pero como no se vuelve a pulsar "Calcular", show() debe seguir devolviendo lo mismo.
        DB::table('vt_usuarios_bet')->insert([
            'agencia_id' => '1', 'tipo' => 'tradicional', 'producto_id' => '1', 'descripcion' => 'X', 'monto' => 99999,
            'fecha' => now()->subMonthsNoOverflow(1)->startOfMonth()->toDateString(),
        ]);

        $response = $this->getJson(route('gerencia.ventas-online.promedio-historico.show'));

        $response->assertOk();
        $diasPeriodo = collect(range(1, 3))
            ->sum(fn (int $mesesAtras): int => now()->subMonthsNoOverflow($mesesAtras)->daysInMonth);
        $response->assertJsonFragment([
            'tipo_categoria' => 'tradicional',
            'monto_promedio' => round(900 / $diasPeriodo, 2),
        ]);
        $this->assertTrue(array_is_list($response->json('promedios')));
    }
}
