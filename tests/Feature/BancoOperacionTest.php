<?php

namespace Tests\Feature;

use App\Http\Middleware\ForcePasswordChange;
use App\Models\BancoOperacion;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BancoOperacionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('bancos_operaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 150)->unique();
            $table->timestamps();
        });
        Schema::create('movimientos_rutas_v2_depositos', function (Blueprint $table): void {
            $table->id();
            $table->string('banco', 100);
        });
        Schema::create('reporte_diario_rutas', function (Blueprint $table): void {
            $table->id();
            $table->string('banco_nombre', 150)->nullable();
        });
        Schema::create('operaciones_deposito_rutas', function (Blueprint $table): void {
            $table->id();
            $table->string('banco', 80);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('operaciones_deposito_rutas');
        Schema::dropIfExists('reporte_diario_rutas');
        Schema::dropIfExists('movimientos_rutas_v2_depositos');
        Schema::dropIfExists('bancos_operaciones');

        parent::tearDown();
    }

    public function test_publica_la_tarjeta_banco_en_el_hub_de_operaciones(): void
    {
        $item = collect(config('module_hubs.operaciones.items'))->firstWhere('nombre', 'Banco');

        $this->assertNotNull($item);
        $this->assertSame('/operaciones/bancos', $item['url']);
        $this->assertSame('Gestion', $item['categoria']);
    }

    public function test_muestra_el_formulario_y_los_bancos_predeterminados(): void
    {
        BancoOperacion::query()->insert([
            ['nombre' => 'Banco Reservas'],
            ['nombre' => 'Banco Caribe'],
            ['nombre' => 'Banco Santa Cruz'],
        ]);
        BancoOperacion::query()->create(['nombre' => 'Banco Popular']);

        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->get(route('operaciones.bancos.index'))
            ->assertOk()
            ->assertSee('Agregar banco')
            ->assertSee('Banco Reservas')
            ->assertSee('Banco Caribe')
            ->assertSee('Banco Santa Cruz')
            ->assertSee('Banco Popular');
    }

    public function test_agrega_un_banco_y_rechaza_duplicados(): void
    {
        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->post(route('operaciones.bancos.store'), ['nombre' => '  Banco Popular  '])
            ->assertRedirect(route('operaciones.bancos.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('bancos_operaciones', ['nombre' => 'Banco Popular']);

        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->from(route('operaciones.bancos.index'))
            ->post(route('operaciones.bancos.store'), ['nombre' => 'Banco Popular'])
            ->assertRedirect(route('operaciones.bancos.index'))
            ->assertSessionHasErrors('nombre');

    }

    public function test_elimina_un_banco_personalizado_sin_registros_asociados(): void
    {
        $banco = BancoOperacion::query()->create(['nombre' => 'Banco Popular']);

        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->delete(route('operaciones.bancos.destroy', $banco))
            ->assertRedirect(route('operaciones.bancos.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('bancos_operaciones', ['id' => $banco->id]);
    }

    public function test_elimina_bancos_iniciales_y_bancos_con_registros_sin_borrar_el_historial(): void
    {
        $predeterminado = BancoOperacion::query()->create(['nombre' => 'Banco Reservas']);
        $utilizado = BancoOperacion::query()->create(['nombre' => 'Banco Popular']);
        Schema::getConnection()->table('movimientos_rutas_v2_depositos')->insert([
            'banco' => 'Banco Popular',
        ]);

        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->delete(route('operaciones.bancos.destroy', $predeterminado))
            ->assertSessionHas('success');
        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->delete(route('operaciones.bancos.destroy', $utilizado))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('bancos_operaciones', ['id' => $predeterminado->id]);
        $this->assertDatabaseMissing('bancos_operaciones', ['id' => $utilizado->id]);
        $this->assertDatabaseHas('movimientos_rutas_v2_depositos', ['banco' => 'Banco Popular']);
    }

    public function test_la_migracion_registra_los_tres_bancos_iniciales_una_sola_vez(): void
    {
        $migracion = require database_path('migrations/2026_08_07_111532_seed_default_bancos_operaciones.php');

        $migracion->up();
        $migracion->up();

        $this->assertSame(3, BancoOperacion::query()->count());
        $this->assertSame([
            'Banco Caribe',
            'Banco Reservas',
            'Banco Santa Cruz',
        ], BancoOperacion::query()->orderBy('nombre')->pluck('nombre')->all());
    }
}
