<?php

namespace Tests\Feature;

use App\Http\Middleware\ForcePasswordChange;
use App\Models\BancoOperacion;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BancoOperacionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\EnsureViewPermission::class);
        Cache::forget('cuentas_bancarias_empresa:168');
        Cache::forget('cuentas_bancarias_empresa:169');
        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $empresa = (string) ($query['intIdEmpresa'] ?? '');
            $descripcion = $empresa === '169' ? 'Coopcentral Empresarial Negosur' : 'Banreservas Empresarial';

            return Http::response(['Result' => ['Det' => [
                ['Cuenta' => '100210003', 'Descripcion' => $descripcion],
                ['Cuenta' => '600120005', 'Descripcion' => 'Combustibles'],
            ]]], 200);
        });

        Schema::create('bancos_operaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 150);
            $table->string('empresa_id', 3)->nullable();
            $table->string('cuenta_codigo', 50)->nullable();
            $table->string('cuenta_descripcion')->nullable();
            $table->unsignedBigInteger('cuenta_contable_id')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'nombre']);
            $table->unique(['empresa_id', 'cuenta_codigo']);
        });
        Schema::create('cuentas_contables', function (Blueprint $table): void {
            $table->id();
            $table->string('cuenta')->unique();
            $table->string('descripcion');
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
        Schema::dropIfExists('cuentas_contables');

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

    public function test_carga_cuentas_bancarias_segun_la_empresa_seleccionada(): void
    {
        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->getJson(route('operaciones.bancos.cuentas', ['empresaId' => '168']))
            ->assertOk()
            ->assertJsonPath('cuentas.0.descripcion', 'Banreservas Empresarial')
            ->assertJsonCount(1, 'cuentas');

        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->getJson(route('operaciones.bancos.cuentas', ['empresaId' => '169']))
            ->assertOk()
            ->assertJsonPath('cuentas.0.descripcion', 'Coopcentral Empresarial Negosur');
    }

    public function test_agrega_un_banco_y_rechaza_duplicados(): void
    {
        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->post(route('operaciones.bancos.store'), ['empresa_id' => '168', 'cuenta_codigo' => '100210003'])
            ->assertRedirect(route('operaciones.bancos.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('bancos_operaciones', ['empresa_id' => '168', 'nombre' => 'Banreservas Empresarial', 'cuenta_codigo' => '100210003']);

        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->from(route('operaciones.bancos.index'))
            ->post(route('operaciones.bancos.store'), ['empresa_id' => '168', 'cuenta_codigo' => '100210003'])
            ->assertRedirect(route('operaciones.bancos.index'))
            ->assertSessionHasErrors('cuenta_codigo');

        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->post(route('operaciones.bancos.store'), ['empresa_id' => '169', 'cuenta_codigo' => '100210003'])
            ->assertRedirect(route('operaciones.bancos.index'));
        $this->assertDatabaseHas('bancos_operaciones', ['empresa_id' => '169', 'nombre' => 'Coopcentral Empresarial Negosur', 'cuenta_codigo' => '100210003']);

    }

    public function test_asigna_una_cuenta_del_catalogo_a_un_banco_existente(): void
    {
        $banco = BancoOperacion::query()->create(['nombre' => 'Banco Reservas']);

        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->put(route('operaciones.bancos.update', $banco), ['empresa_id' => '169', 'cuenta_codigo' => '100210003'])
            ->assertRedirect(route('operaciones.bancos.index'));

        $this->assertDatabaseHas('bancos_operaciones', ['id' => $banco->id, 'empresa_id' => '169', 'cuenta_codigo' => '100210003']);
    }

    public function test_rechaza_cuentas_que_no_son_bancarias(): void
    {
        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->post(route('operaciones.bancos.store'), ['empresa_id' => '168', 'cuenta_codigo' => '600120005'])
            ->assertSessionHasErrors('cuenta_codigo');

        $this->assertDatabaseMissing('bancos_operaciones', ['nombre' => 'Banco Popular']);

        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->post(route('operaciones.bancos.store'), ['empresa_id' => '169', 'cuenta_codigo' => '100210999'])
            ->assertSessionHasErrors('cuenta_codigo');
    }

    public function test_el_reporte_diario_tambien_asocia_la_cuenta_al_crear_un_banco(): void
    {
        $this->withoutMiddleware([Authenticate::class, ForcePasswordChange::class])
            ->post(route('operaciones.reporte.diario.bancos.guardar'), [
                'empresa_id' => '168',
                'cuenta_codigo' => '100210003',
                'fecha' => '2026-08-10',
            ])
            ->assertRedirect(route('operaciones.reporte.diario', ['fecha' => '2026-08-10']));

        $this->assertDatabaseHas('bancos_operaciones', ['nombre' => 'Banreservas Empresarial', 'empresa_id' => '168', 'cuenta_codigo' => '100210003']);
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

    public function test_la_migracion_registra_los_tres_bancos_iniciales(): void
    {
        $migracion = require database_path('migrations/2026_08_07_111532_seed_default_bancos_operaciones.php');

        $migracion->up();

        $this->assertSame(3, BancoOperacion::query()->count());
        $this->assertSame([
            'Banco Caribe',
            'Banco Reservas',
            'Banco Santa Cruz',
        ], BancoOperacion::query()->orderBy('nombre')->pluck('nombre')->all());
    }
}
