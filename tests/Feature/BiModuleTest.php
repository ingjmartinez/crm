<?php

namespace Tests\Feature;

use App\Http\Middleware\ExpireInactiveSession;
use App\Http\Middleware\PreventDeletionForAdmin2;
use App\Models\Agencia;
use App\Models\BiVentaHora;
use App\Models\User;
use App\Services\Bi\ResumirPremiosDia;
use App\Services\Bi\ResumirVentasDia;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BiModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (require database_path('migrations/2026_10_01_123543_create_bi_limite_productos_table.php'))->up();

        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal')->nullable();
            $table->string('agencia')->nullable();
            $table->string('nombre_agencia')->nullable();
            $table->string('empresa')->nullable();
            $table->string('sistema')->nullable();
            $table->string('ciudad')->nullable();
            $table->integer('estatus')->default(1);
            $table->timestamps();
        });

        Schema::create('catalogo_juegos', function (Blueprint $table): void {
            $table->id();
            $table->string('producto_id')->unique();
            $table->string('tipo')->nullable();
            $table->string('descripcion')->nullable();
        });

        Schema::create('vt_usuarios_bet', function (Blueprint $table): void {
            $table->id();
            $table->string('producto_id');
            $table->string('tipo')->nullable();
            $table->decimal('monto', 14, 2);
            $table->dateTime('fecha');
        });

        Schema::create('premios_bet', function (Blueprint $table): void {
            $table->id('premio_id');
            $table->decimal('monto', 14, 2);
            $table->date('fecha');
        });

        Schema::create('bi_venta_horas', function (Blueprint $table): void {
            $table->id();
            $table->date('fecha');
            $table->unsignedTinyInteger('hora');
            $table->decimal('tradicional_acumulado', 18, 2);
            $table->decimal('no_tradicional_acumulado', 18, 2);
            $table->decimal('otros_acumulado', 18, 2);
            $table->decimal('externas_acumulado', 18, 2)->default(0);
            $table->decimal('recargas_acumulado', 18, 2)->default(0);
            $table->unsignedInteger('registros');
            $table->dateTime('capturado_en');
            $table->json('rutas')->nullable();
            $table->json('productos')->nullable();
            $table->unsignedInteger('terminales_evaluadas')->nullable();
            $table->unsignedInteger('terminales_con_venta')->nullable();
            $table->json('terminales_categoria')->nullable();
            $table->decimal('quiniela_loteka_acumulado', 18, 2)->nullable();
            $table->decimal('mega_chance_acumulado', 18, 2)->nullable();
            $table->timestamps();
            $table->unique(['fecha', 'hora']);
        });

        Schema::create('bi_venta_dias', function (Blueprint $table): void {
            $table->id();
            $table->date('fecha')->unique();
            $table->decimal('tradicional', 18, 2)->default(0);
            $table->decimal('no_tradicional', 18, 2)->default(0);
            $table->decimal('externas', 18, 2)->default(0);
            $table->decimal('recargas', 18, 2)->default(0);
            $table->decimal('otros', 18, 2)->default(0);
            $table->unsignedInteger('registros')->default(0);
            $table->string('origen')->default('vt_usuarios_bet');
            $table->dateTime('resumido_en');
            $table->timestamps();
        });

        Schema::create('bi_premio_dias', function (Blueprint $table): void {
            $table->id();
            $table->date('fecha')->unique();
            $table->decimal('premios', 18, 2)->default(0);
            $table->unsignedInteger('registros')->default(0);
            $table->string('origen')->default('premios_bet');
            $table->dateTime('resumido_en');
            $table->timestamps();
        });

        Schema::create('bi_producto_dias', function (Blueprint $table): void {
            $table->id();
            $table->string('producto_id');
            $table->date('fecha');
            $table->decimal('monto', 18, 2);
            $table->unsignedInteger('registros');
            $table->timestamps();
            $table->unique(['producto_id', 'fecha']);
        });

        Schema::create('ventas_online_promedios_historicos', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo_categoria');
            $table->decimal('monto_promedio', 18, 2);
            $table->json('meses_incluidos');
            $table->dateTime('calculado_en')->nullable();
            $table->foreignId('calculado_por_id')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_01_125650_add_terminal_scope_to_bi_limits.php'))->up();
    }

    public function test_bi_route_is_protected_and_listed_in_dashboard_hub(): void
    {
        $this->assertTrue(Route::has('bi.index'));
        $this->get(route('bi.index'))->assertRedirect(route('login'));

        $item = collect(config('module_hubs.dashboard.items'))->firstWhere('nombre', 'BI');

        $this->assertNotNull($item);
        $this->assertSame('/bi', $item['url']);
    }

    public function test_bi_controller_renders_its_inertia_page(): void
    {
        $this->assertSame('bi', app(\App\Http\Middleware\HandleBiInertiaRequests::class)->rootView(request()));

        $this->withoutMiddleware()
            ->withHeader('X-Inertia', 'true')
            ->get(route('bi.index'))
            ->assertOk()
            ->assertJsonPath('component', 'Bi/Dashboard')
            ->assertJsonPath('props.modulo', 'BI')
            ->assertJsonPath('props.limitesProductosUrl', route('bi.limites-productos.guardar'))
            ->assertJsonPath('props.alertasProductos', [])
            ->assertJsonPath('props.productosDisponibles', [])
            ->assertJsonPath('props.menuUrl', route('dashboard.index'))
            ->assertJsonPath('props.biUrl', route('bi.index'))
            ->assertJsonPath('props.recalcularUrl', route('bi.recalcular'))
            ->assertJsonPath('props.catalogoActualizado', null)
            ->assertJsonPath('props.ventasPorCategoria.total', 0)
            ->assertJsonPath('props.ventasPremios.ventas', 0)
            ->assertJsonPath('props.ventasPremios.premios', 0)
            ->assertJsonPath('props.lecturasPorHora', [])
            ->assertJsonPath('props.terminales.total', 0);

        $layout = file_get_contents(resource_path('views/bi.blade.php'));

        $this->assertStringContainsString("@vite('resources/js/bi/app.js')", $layout);
        $this->assertStringNotContainsString('scrollbar', $layout);
    }

    public function test_bi_initial_page_uses_the_script_data_expected_by_vue(): void
    {
        $this->withoutMiddleware([ExpireInactiveSession::class, PreventDeletionForAdmin2::class]);

        $this->actingAs(new User(['name' => 'Usuario BI', 'email' => 'bi@example.test']))
            ->get(route('bi.index'))
            ->assertOk()
            ->assertSee('data-page="app"', false)
            ->assertSee('"component":"Bi\\/Dashboard"', false);
    }

    public function test_authenticated_bi_page_loads_with_a_today_hourly_snapshot(): void
    {
        BiVentaHora::factory()->create([
            'fecha' => today()->toDateString(),
            'hora' => 12,
            'productos' => ['43' => 80],
        ]);

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->withoutMiddleware([ExpireInactiveSession::class, PreventDeletionForAdmin2::class]);
        $this->actingAs(new User(['name' => 'Usuario BI', 'email' => 'bi@example.test']))
            ->get(route('bi.index'))
            ->assertOk()
            ->assertSee('"hora":12', false);

        $this->assertTrue(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'bi_venta_horas') && str_contains($sql, 'fecha') && ! str_contains(strtolower($sql), 'date(')));
    }

    public function test_product_list_offers_category_totals_alongside_individual_products(): void
    {
        \App\Models\CatalogoJuego::query()->create(['producto_id' => '43', 'descripcion' => 'Quiniela Loteka']);

        $this->withoutMiddleware()->withHeader('X-Inertia', 'true')
            ->get(route('bi.index'))
            ->assertOk()
            ->assertJsonCount(1, 'props.productosDisponibles')
            ->assertJsonPath('props.productosDisponibles.0.producto_id', '43')
            ->assertJsonCount(2, 'props.gruposDisponibles')
            ->assertJsonPath('props.gruposDisponibles.0.producto_id', 'grupo:tradicional')
            ->assertJsonPath('props.gruposDisponibles.0.descripcion', 'Total Tradicionales')
            ->assertJsonPath('props.gruposDisponibles.1.producto_id', 'grupo:no_tradicional')
            ->assertJsonPath('props.gruposDisponibles.1.descripcion', 'Total No tradicionales');
    }

    public function test_product_alerts_use_latest_today_snapshot_independently_of_selected_period(): void
    {
        \App\Models\CatalogoJuego::query()->create(['producto_id' => '43', 'descripcion' => 'Quiniela Loteka']);
        \App\Models\BiLimiteProducto::factory()->create(['producto_id' => '43', 'monto' => 100]);
        Agencia::query()->create(['terminal' => '00100', 'sistema' => 'LOTOBET', 'nombre_agencia' => 'Agencia Norte']);
        \App\Models\BiLimiteProducto::factory()->create(['producto_id' => '43', 'terminal' => '100', 'monto' => 50]);
        BiVentaHora::factory()->create(['fecha' => today()->subDay()->toDateString(), 'hora' => 22, 'productos' => ['43' => 9999]]);
        BiVentaHora::factory()->create(['fecha' => today()->toDateString(), 'hora' => 6, 'productos' => ['43' => 80]]);
        BiVentaHora::factory()->create(['fecha' => today()->toDateString(), 'hora' => 7, 'productos' => ['43' => 120], 'productos_terminales' => ['100' => ['43' => 60]]]);

        $this->withoutMiddleware()->withHeader('X-Inertia', 'true')
            ->get(route('bi.index', ['desde' => today()->subDays(5)->toDateString(), 'hasta' => today()->subDay()->toDateString()]))
            ->assertOk()
            ->assertJsonPath('props.productosDisponibles.0.descripcion', 'Quiniela Loteka')
            ->assertJsonPath('props.alertasProductos.0.ventas', 120)
            ->assertJsonPath('props.alertasProductos.0.eliminarUrl', route('bi.limites-productos.eliminar', \App\Models\BiLimiteProducto::query()->where('terminal', '')->firstOrFail()))
            ->assertJsonPath('props.alertasProductos.0.porcentaje', 120)
            ->assertJsonPath('props.alertasProductos.0.alerta', true)
            ->assertJsonPath('props.alertasProductos.1.terminal', '100')
            ->assertJsonPath('props.alertasProductos.1.ventas', 60)
            ->assertJsonPath('props.alertasProductos.1.alerta', true)
            ->assertJsonPath('props.terminalesLimites.0.terminal', '100')
            ->assertJsonPath('props.terminalesLimites.0.nombre', 'Agencia Norte');

        BiVentaHora::query()->whereDate('fecha', today()->toDateString())->delete();
        $this->get(route('bi.index'))->assertOk()
            ->assertJsonPath('props.alertasProductos.0.ventas', null)
            ->assertJsonPath('props.alertasProductos.0.estado', 'Sin lectura');
    }

    public function test_bi_reads_terminals_from_agencias_and_filters_on_the_server(): void
    {
        Agencia::query()->create(['terminal' => '50091', 'agencia' => 'A-01', 'nombre_agencia' => 'Agencia Norte', 'empresa' => 'Grupo Joselito', 'ciudad' => 'Santiago', 'estatus' => 1]);
        Agencia::query()->create(['terminal' => '5719', 'agencia' => 'A-02', 'nombre_agencia' => 'Agencia Sur', 'empresa' => 'Grupo Joselito', 'ciudad' => 'Santo Domingo', 'estatus' => 0]);
        Agencia::query()->create(['terminal' => null, 'agencia' => 'A-03', 'nombre_agencia' => 'Sin terminal']);

        $this->withoutMiddleware()
            ->withHeader('X-Inertia', 'true')
            ->get(route('bi.index', ['q' => 'Norte']))
            ->assertOk()
            ->assertJsonPath('props.busquedaTerminal', 'Norte')
            ->assertJsonPath('props.terminales.total', 1)
            ->assertJsonPath('props.catalogoActualizado', now()->toDateString())
            ->assertJsonPath('props.terminales.data.0.terminal', '50091')
            ->assertJsonPath('props.terminales.data.0.nombre_agencia', 'Agencia Norte');

        $this->withHeader('X-Inertia', 'true')
            ->get(route('bi.index'))
            ->assertJsonPath('props.terminales.total', 2);
    }

    public function test_bi_exposes_hourly_sales_snapshots_for_today(): void
    {
        DB::table('vt_usuarios_bet')->insert([
            ['producto_id' => '43', 'tipo' => 'Tradicional', 'monto' => 100, 'fecha' => today()->subDays(7)->setTime(9, 0)],
            ['producto_id' => '43', 'tipo' => 'Tradicional', 'monto' => 75, 'fecha' => today()->subDays(7)->setTime(17, 0)],
            ['producto_id' => '44', 'tipo' => 'Tradicional', 'monto' => 500, 'fecha' => today()->subDays(7)->setTime(17, 0)],
            ['producto_id' => '43', 'tipo' => 'Tradicional', 'monto' => 200, 'fecha' => today()->subDays(6)->setTime(9, 0)],
        ]);
        DB::table('bi_producto_dias')->insert([
            ['producto_id' => '43', 'fecha' => today()->subDays(7)->toDateString(), 'monto' => 175, 'registros' => 2],
            ['producto_id' => '43', 'fecha' => today()->subDays(6)->toDateString(), 'monto' => 250, 'registros' => 1],
            ['producto_id' => '44', 'fecha' => today()->subDays(5)->toDateString(), 'monto' => 500, 'registros' => 1],
            ['producto_id' => '38', 'fecha' => today()->subDays(7)->toDateString(), 'monto' => 110, 'registros' => 1],
            ['producto_id' => '38', 'fecha' => today()->subDays(6)->toDateString(), 'monto' => 150, 'registros' => 1],
        ]);
        foreach ([0, 1, 6] as $hora) {
            BiVentaHora::factory()->create([
                'fecha' => today()->toDateString(),
                'hora' => $hora,
            ]);
        }

        BiVentaHora::factory()->create([
            'fecha' => today()->toDateString(),
            'hora' => 14,
            'tradicional_acumulado' => 125,
            'no_tradicional_acumulado' => 60,
            'externas_acumulado' => 5,
            'recargas_acumulado' => 10,
            'quiniela_loteka_acumulado' => 35,
            'mega_chance_acumulado' => 45,
            'capturado_en' => today()->setTime(14, 5),
            'rutas' => [['nombre' => 'RUTA NORTE', 'monto' => 185]],
        ]);

        DB::table('ventas_online_promedios_historicos')->insert([
            'tipo_categoria' => 'tradicional',
            'monto_promedio' => 500,
            'meses_incluidos' => json_encode(['2026-06', '2026-07', '2026-08']),
        ]);

        $this->withoutMiddleware()
            ->withHeader('X-Inertia', 'true')
            ->get(route('bi.index'))
            ->assertOk()
            ->assertJsonCount(2, 'props.lecturasPorHora')
            ->assertJsonPath('props.lecturasPorHora.0.hora', 6)
            ->assertJsonPath('props.lecturasPorHora.1.hora', 14)
            ->assertJsonPath('props.lecturasPorHora.1.tradicional', 125)
            ->assertJsonPath('props.lecturasPorHora.1.noTradicional', 60)
            ->assertJsonPath('props.lecturasPorHora.1.total', 200)
            ->assertJsonPath('props.quinielaLotekaHoy', 35)
            ->assertJsonPath('props.quinielaLotekaSemanaAnterior', 175)
            ->assertJsonPath('props.quinielaLotekaRecordAnterior', 250)
            ->assertJsonPath('props.megaChanceHoy', 45)
            ->assertJsonPath('props.megaChanceSemanaAnterior', 110)
            ->assertJsonPath('props.megaChanceRecordAnterior', 150)
            ->assertJsonPath('props.rutasConMasVenta.0.nombre', 'RUTA NORTE')
            ->assertJsonPath('props.rutasConMasVenta.0.monto', 185)
            ->assertJsonPath('props.promediosHistoricos.tradicional.monto', 500)
            ->assertJsonPath('props.promediosHistoricos.tradicional.meses.0', '2026-06');
    }

    public function test_bi_shows_the_ten_highest_routes_from_the_latest_hourly_snapshot(): void
    {
        $routes = collect(range(1, 12))
            ->map(fn (int $position): array => [
                'nombre' => "RUTA {$position}",
                'monto' => 1300 - ($position * 100),
            ])
            ->all();

        BiVentaHora::factory()->create([
            'fecha' => today()->toDateString(),
            'hora' => 10,
            'rutas' => $routes,
        ]);

        $this->withoutMiddleware()
            ->withHeader('X-Inertia', 'true')
            ->get(route('bi.index'))
            ->assertOk()
            ->assertJsonCount(10, 'props.rutasConMasVenta')
            ->assertJsonPath('props.rutasConMasVenta.0.nombre', 'RUTA 1')
            ->assertJsonPath('props.rutasConMasVenta.9.nombre', 'RUTA 10');
    }

    public function test_bi_sums_bet_categories_by_date_using_game_catalog_and_type_fallback(): void
    {
        DB::table('catalogo_juegos')->insert([
            ['producto_id' => '1', 'tipo' => 'Tradicional'],
            ['producto_id' => '2', 'tipo' => 'No Tradicional'],
            ['producto_id' => '3', 'tipo' => 'Recargas'],
        ]);
        DB::table('vt_usuarios_bet')->insert([
            ['producto_id' => '1', 'tipo' => 'Otro', 'monto' => 100, 'fecha' => '2026-09-28'],
            ['producto_id' => '2', 'tipo' => null, 'monto' => 40, 'fecha' => '2026-09-28'],
            ['producto_id' => '3', 'tipo' => null, 'monto' => 20, 'fecha' => '2026-09-28'],
            ['producto_id' => '4', 'tipo' => 'Recarga', 'monto' => 10, 'fecha' => '2026-09-28'],
            ['producto_id' => '1', 'tipo' => null, 'monto' => 999, 'fecha' => '2026-09-27'],
        ]);

        app(ResumirVentasDia::class)->resumir(CarbonImmutable::parse('2026-09-27'));
        app(ResumirVentasDia::class)->resumir(CarbonImmutable::parse('2026-09-28'));
        DB::table('vt_usuarios_bet')->delete();

        $this->withoutMiddleware()
            ->withHeader('X-Inertia', 'true')
            ->get(route('bi.index', ['desde' => '2026-09-28', 'hasta' => '2026-09-28']))
            ->assertOk()
            ->assertJsonPath('props.periodoVentas.desde', '2026-09-28')
            ->assertJsonPath('props.periodoVentas.hasta', '2026-09-28')
            ->assertJsonPath('props.ventasPorCategoria.tradicional', 100)
            ->assertJsonPath('props.ventasPorCategoria.no_tradicional', 40)
            ->assertJsonPath('props.ventasPorCategoria.recargas', 30)
            ->assertJsonPath('props.ventasPorCategoria.diasSinDatos', 0)
            ->assertJsonPath('props.ventasPorCategoria.total', 170)
            ->assertJsonCount(1, 'props.ventasPorCategoria.serie')
            ->assertJsonPath('props.ventasPorCategoria.serie.0.fecha', '2026-09-28')
            ->assertJsonPath('props.ventasPorCategoria.serie.0.tradicional', 100)
            ->assertJsonPath('props.ventasPorCategoria.serie.0.recargas', 30);

        $this->withHeader('X-Inertia', 'true')
            ->get(route('bi.index', ['desde' => '2026-09-27', 'hasta' => '2026-09-28']))
            ->assertJsonPath('props.ventasPorCategoria.tradicional', 1099)
            ->assertJsonCount(2, 'props.ventasPorCategoria.serie');
    }

    public function test_bi_builds_sales_and_prizes_chart_from_historical_tables(): void
    {
        DB::table('vt_usuarios_bet')->insert([
            ['producto_id' => '1', 'tipo' => 'Tradicional', 'monto' => 100, 'fecha' => '2026-09-27'],
            ['producto_id' => '2', 'tipo' => 'No Tradicional', 'monto' => 50, 'fecha' => '2026-09-27'],
            ['producto_id' => '1', 'tipo' => 'Tradicional', 'monto' => 200, 'fecha' => '2026-09-28'],
        ]);
        DB::table('premios_bet')->insert([
            ['monto' => 60, 'fecha' => '2026-09-27'],
            ['monto' => 80, 'fecha' => '2026-09-28'],
        ]);
        foreach (['2026-09-27', '2026-09-28'] as $fecha) {
            app(ResumirVentasDia::class)->resumir(CarbonImmutable::parse($fecha));
            app(ResumirPremiosDia::class)->resumir(CarbonImmutable::parse($fecha));
        }
        DB::table('vt_usuarios_bet')->delete();
        DB::table('premios_bet')->delete();

        $this->withoutMiddleware()
            ->withHeader('X-Inertia', 'true')
            ->get(route('bi.index', ['desde' => '2026-09-27', 'hasta' => '2026-09-28']))
            ->assertOk()
            ->assertJsonPath('props.ventasPremios.ventas', 350)
            ->assertJsonPath('props.ventasPremios.premios', 140)
            ->assertJsonPath('props.ventasPremios.utilidad', 210)
            ->assertJsonPath('props.ventasPremios.tasaPremiacion', 40)
            ->assertJsonCount(2, 'props.ventasPremios.serie')
            ->assertJsonPath('props.ventasPremios.serie.0.fecha', '2026-09-27')
            ->assertJsonPath('props.ventasPremios.serie.0.ventas', 150)
            ->assertJsonPath('props.ventasPremios.serie.0.premios', 60)
            ->assertJsonPath('props.ventasPremios.serie.1.ventas', 200)
            ->assertJsonPath('props.ventasPremios.serie.1.premios', 80);

        $this->withHeader('X-Inertia', 'true')
            ->get(route('bi.index', ['desde' => '2026-09-28', 'hasta' => '2026-09-28']))
            ->assertOk()
            ->assertJsonCount(1, 'props.ventasPremios.serie')
            ->assertJsonPath('props.ventasPremios.ventas', 200)
            ->assertJsonPath('props.ventasPremios.premios', 80)
            ->assertJsonPath('props.ventasPremios.serie.0.fecha', '2026-09-28');
    }

    public function test_sales_and_prizes_chart_excludes_today_even_when_filter_includes_it(): void
    {
        $ayer = today()->subDay()->toDateString();
        DB::table('bi_venta_dias')->insert([
            [
                'fecha' => $ayer,
                'tradicional' => 100,
                'registros' => 1,
                'resumido_en' => now(),
            ],
            [
                'fecha' => today()->toDateString(),
                'tradicional' => 500,
                'registros' => 1,
                'resumido_en' => now(),
            ],
        ]);
        DB::table('bi_premio_dias')->insert([
            [
                'fecha' => $ayer,
                'premios' => 40,
                'registros' => 1,
                'resumido_en' => now(),
            ],
            [
                'fecha' => today()->toDateString(),
                'premios' => 300,
                'registros' => 1,
                'resumido_en' => now(),
            ],
        ]);

        $this->withoutMiddleware()
            ->withHeader('X-Inertia', 'true')
            ->get(route('bi.index', ['desde' => $ayer, 'hasta' => today()->toDateString()]))
            ->assertOk()
            ->assertJsonPath('props.ventasPremios.ventas', 100)
            ->assertJsonPath('props.ventasPremios.premios', 40)
            ->assertJsonPath('props.ventasPremios.hasta', $ayer)
            ->assertJsonCount(1, 'props.ventasPremios.serie');
    }

    public function test_bi_rejects_an_invalid_sales_period(): void
    {
        $this->withoutMiddleware()
            ->getJson(route('bi.index', ['desde' => '2026-09-29', 'hasta' => '2026-09-28']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hasta');
    }

    public function test_bi_sidebar_uses_hourly_terminal_counts_and_closed_day_average(): void
    {
        DB::table('bi_venta_dias')->insert([
            'fecha' => today()->subDay()->toDateString(),
            'tradicional' => 100,
            'no_tradicional' => 40,
            'externas' => 0,
            'recargas' => 10,
            'otros' => 0,
            'registros' => 3,
            'resumido_en' => now(),
        ]);
        BiVentaHora::factory()->create([
            'fecha' => today()->toDateString(),
            'hora' => 8,
            'tradicional_acumulado' => 30,
            'no_tradicional_acumulado' => 20,
            'otros_acumulado' => 5,
            'terminales_evaluadas' => 3,
            'terminales_con_venta' => 2,
            'terminales_categoria' => ['tradicional' => 2, 'no_tradicional' => 1, 'externas' => 0, 'recargas' => 0, 'otros' => 0],
        ]);

        $this->withoutMiddleware()
            ->withHeader('X-Inertia', 'true')
            ->get(route('bi.index', ['desde' => today()->subDay()->toDateString(), 'hasta' => today()->toDateString()]))
            ->assertOk()
            ->assertJsonPath('props.indicadores.ventasHoy', 55)
            ->assertJsonPath('props.indicadores.ventasAyer', 150)
            ->assertJsonPath('props.indicadores.terminalesEvaluadas', 3)
            ->assertJsonPath('props.indicadores.terminalesConVenta', 2)
            ->assertJsonPath('props.indicadores.terminalesSinVenta', 1)
            ->assertJsonPath('props.indicadores.coberturaCategorias.tradicional', 2)
            ->assertJsonPath('props.indicadores.coberturaCategorias.recargas', null)
            ->assertJsonPath('props.indicadores.promedioVentasDiarias', 150)
            ->assertJsonPath('props.indicadores.diasPromedioConDatos', 1)
            ->assertJsonPath('props.indicadores.tendencia7d', null);
    }

    public function test_daily_sales_comparison_uses_hourly_sales_and_closed_day_summaries(): void
    {
        $ayer = today()->subDay()->toDateString();
        $mesAnterior = today()->subMonthNoOverflow()->toDateString();
        foreach ([[$ayer, 150], [$mesAnterior, 200]] as [$fecha, $venta]) {
            DB::table('bi_venta_dias')->insert([
                'fecha' => $fecha,
                'tradicional' => $venta,
                'registros' => 1,
                'resumido_en' => now(),
            ]);
            DB::table('bi_premio_dias')->insert([
                'fecha' => $fecha,
                'premios' => $venta / 2,
                'registros' => 1,
                'resumido_en' => now(),
            ]);
        }
        Agencia::query()->create(['terminal' => '50091', 'sistema' => 'LOTOBET', 'estatus' => 1]);
        BiVentaHora::factory()->create([
            'fecha' => today()->toDateString(),
            'hora' => 11,
            'tradicional_acumulado' => 90,
            'no_tradicional_acumulado' => 30,
        ]);

        $this->withoutMiddleware()
            ->withHeader('X-Inertia', 'true')
            ->get(route('bi.index'))
            ->assertOk()
            ->assertJsonPath('props.indicadores.comparativoDia.hoy.ventas', 120)
            ->assertJsonPath('props.indicadores.comparativoDia.hoy.premios', null)
            ->assertJsonPath('props.indicadores.comparativoDia.hoy.resultado', null)
            ->assertJsonPath('props.indicadores.comparativoDia.hoy.agenciasActivas', 1)
            ->assertJsonPath('props.indicadores.comparativoDia.ayer.ventas', 150)
            ->assertJsonPath('props.indicadores.comparativoDia.ayer.premios', 75)
            ->assertJsonPath('props.indicadores.comparativoDia.ayer.resultado', 75)
            ->assertJsonPath('props.indicadores.comparativoDia.ayer.tasa', 50)
            ->assertJsonPath('props.indicadores.comparativoDia.mesAnterior.fecha', $mesAnterior)
            ->assertJsonPath('props.indicadores.comparativoDia.mesAnterior.ventas', 200)
            ->assertJsonPath('props.indicadores.comparativoDia.mesAnterior.agenciasActivas', null);

        DB::table('premios_bet')->insert(['fecha' => today()->toDateString(), 'monto' => 60]);

        $this->withHeader('X-Inertia', 'true')
            ->get(route('bi.index'))
            ->assertJsonPath('props.indicadores.comparativoDia.hoy.premios', 60)
            ->assertJsonPath('props.indicadores.comparativoDia.hoy.resultado', 60)
            ->assertJsonPath('props.indicadores.comparativoDia.hoy.tasa', 50);
    }

    public function test_bi_trend_requires_two_complete_weeks(): void
    {
        $dias = [];
        for ($offset = 1; $offset <= 14; $offset++) {
            $dias[] = [
                'fecha' => today()->subDays($offset)->toDateString(),
                'tradicional' => $offset <= 7 ? 200 : 100,
                'no_tradicional' => 0,
                'externas' => 0,
                'recargas' => 0,
                'otros' => 0,
                'registros' => 1,
                'resumido_en' => now(),
            ];
        }
        DB::table('bi_venta_dias')->insert($dias);

        $this->withoutMiddleware()
            ->withHeader('X-Inertia', 'true')
            ->get(route('bi.index'))
            ->assertOk()
            ->assertJsonPath('props.indicadores.tendencia7d', 100)
            ->assertJsonPath('props.indicadores.diasTendenciaActual', 7)
            ->assertJsonPath('props.indicadores.diasTendenciaAnterior', 7);
    }

    public function test_bi_category_chart_excludes_today_even_when_hourly_snapshot_exists(): void
    {
        DB::table('bi_venta_dias')->insert([
            'fecha' => today()->subDay()->toDateString(),
            'tradicional' => 100,
            'no_tradicional' => 40,
            'externas' => 5,
            'recargas' => 10,
            'otros' => 0,
            'registros' => 4,
            'resumido_en' => now(),
        ]);
        BiVentaHora::factory()->create([
            'fecha' => today()->toDateString(),
            'tradicional_acumulado' => 25,
            'no_tradicional_acumulado' => 15,
            'externas_acumulado' => 2,
            'recargas_acumulado' => 3,
            'otros_acumulado' => 0,
        ]);
        DB::table('bi_venta_dias')->insert([
            'fecha' => today()->toDateString(),
            'tradicional' => 999,
            'no_tradicional' => 0,
            'externas' => 0,
            'recargas' => 0,
            'otros' => 0,
            'registros' => 1,
            'resumido_en' => now(),
        ]);

        $this->withoutMiddleware()
            ->withHeader('X-Inertia', 'true')
            ->get(route('bi.index', ['desde' => today()->subDay()->toDateString(), 'hasta' => today()->toDateString()]))
            ->assertOk()
            ->assertJsonPath('props.ventasPorCategoria.tradicional', 100)
            ->assertJsonPath('props.ventasPorCategoria.no_tradicional', 40)
            ->assertJsonPath('props.ventasPorCategoria.externas', 5)
            ->assertJsonPath('props.ventasPorCategoria.recargas', 10)
            ->assertJsonPath('props.ventasPorCategoria.total', 155)
            ->assertJsonPath('props.ventasPorCategoria.hasta', today()->subDay()->toDateString())
            ->assertJsonPath('props.ventasPorCategoria.diasSinDatos', 0)
            ->assertJsonCount(1, 'props.ventasPorCategoria.serie')
            ->assertJsonPath('props.ventasPorCategoria.serie.0.fecha', today()->subDay()->toDateString());

        $this->withHeader('X-Inertia', 'true')
            ->get(route('bi.index', ['desde' => today()->toDateString(), 'hasta' => today()->toDateString()]))
            ->assertJsonPath('props.ventasPorCategoria.total', 0)
            ->assertJsonPath('props.ventasPorCategoria.hasta', null)
            ->assertJsonPath('props.ventasPorCategoria.serie', []);
    }

    public function test_recalculate_action_updates_closed_day_from_bet_table_without_using_api(): void
    {
        $fecha = today()->subDay()->toDateString();
        DB::table('vt_usuarios_bet')->insert([
            'producto_id' => '1', 'tipo' => 'Tradicional', 'monto' => 100, 'fecha' => $fecha,
        ]);
        app(ResumirVentasDia::class)->resumir(CarbonImmutable::parse($fecha));
        DB::table('vt_usuarios_bet')->insert([
            'producto_id' => '1', 'tipo' => 'Tradicional', 'monto' => 25, 'fecha' => $fecha,
        ]);

        $this->withoutMiddleware()
            ->post(route('bi.recalcular'), ['desde' => $fecha, 'hasta' => today()->toDateString()])
            ->assertRedirect(route('bi.index', ['desde' => $fecha, 'hasta' => today()->toDateString()]));

        $this->assertDatabaseHas('bi_venta_dias', [
            'fecha' => $fecha,
            'tradicional' => 125,
            'registros' => 2,
            'origen' => 'vt_usuarios_bet',
        ]);

        $this->withHeader('X-Inertia', 'true')
            ->get(route('bi.index', ['desde' => $fecha, 'hasta' => today()->toDateString()]))
            ->assertJsonPath('props.ventasPorCategoria.tradicional', 125);
    }

    public function test_recalculate_action_rejects_today_only_and_ranges_over_31_days(): void
    {
        $this->withoutMiddleware()
            ->postJson(route('bi.recalcular'), ['desde' => today()->toDateString(), 'hasta' => today()->toDateString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('desde');

        $this->postJson(route('bi.recalcular'), [
            'desde' => today()->subDays(32)->toDateString(),
            'hasta' => today()->subDay()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('hasta');
    }
}
