<?php

namespace Tests\Feature;

use App\Models\Agencia;
use App\Models\BiLimiteProducto;
use App\Models\BiVentaHora;
use App\Models\CatalogoJuego;
use App\Models\User;
use App\Services\Bi\AlertasProductos;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BiLimiteProductoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('catalogo_juegos', function (Blueprint $table): void {
            $table->id();
            $table->string('producto_id')->unique();
            $table->string('descripcion')->nullable();
            $table->string('tipo')->nullable();
        });
        (require database_path('migrations/2026_10_01_123543_create_bi_limite_productos_table.php'))->up();
        Schema::create('bi_venta_horas', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('agencias', function (Blueprint $table): void {
            $table->id();
            $table->string('terminal')->nullable();
            $table->string('nombre_agencia')->nullable();
            $table->string('agencia')->nullable();
            $table->string('sistema')->nullable();
            $table->string('empresa')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_01_125650_add_terminal_scope_to_bi_limits.php'))->up();
        CatalogoJuego::query()->create(['producto_id' => '43', 'descripcion' => 'Quiniela Loteka']);
    }

    public function test_guests_cannot_save_limits(): void
    {
        $this->post(route('bi.limites-productos.guardar'), [
            'producto_id' => '43', 'monto' => 1000, 'activo' => true,
        ])->assertRedirect(route('login'));

        $this->assertSame(0, BiLimiteProducto::query()->count());
    }

    public function test_category_limits_can_be_saved_updated_and_paused_in_both_scopes(): void
    {
        Agencia::query()->create(['terminal' => '00100', 'sistema' => 'LOTOBET']);
        $this->withoutMiddleware()->actingAs(new User(['name' => 'Usuario BI']));

        foreach (array_keys(BiLimiteProducto::GRUPOS) as $productoId) {
            foreach (['global', 'terminal'] as $alcance) {
                $payload = [
                    'producto_id' => $productoId, 'alcance' => $alcance,
                    'terminal' => $alcance === 'terminal' ? '00100' : '', 'monto' => 100, 'activo' => true,
                ];
                $this->post(route('bi.limites-productos.guardar'), $payload)
                    ->assertRedirect()->assertSessionHasNoErrors();
                $this->post(route('bi.limites-productos.guardar'), array_replace($payload, ['monto' => 200, 'activo' => false]))
                    ->assertRedirect()->assertSessionHasNoErrors();
            }
        }

        $this->assertSame(4, BiLimiteProducto::query()->count());
        foreach (BiLimiteProducto::query()->get() as $limite) {
            $this->assertSame('200.00', $limite->monto);
            $this->assertFalse($limite->activo);
            $this->assertContains($limite->terminal, ['', '100']);
        }

        foreach (['grupo:otros', 'grupo:TRADICIONAL', 'grupo:', 'missing'] as $productoId) {
            $this->postJson(route('bi.limites-productos.guardar'), [
                'producto_id' => $productoId, 'monto' => 100, 'activo' => true,
            ])->assertUnprocessable()->assertJsonValidationErrors('producto_id');
        }
        $this->assertSame(4, BiLimiteProducto::query()->count());
    }

    public function test_category_alerts_total_global_and_terminal_sales_without_mixing_other_products(): void
    {
        foreach ([['43', ' Tradicional '], ['44', 'TRADICIONAL'], ['38', 'No Tradicional'], ['39', 'no_tradicional'], ['99', 'Recargas']] as [$id, $tipo]) {
            CatalogoJuego::query()->updateOrCreate(['producto_id' => $id], ['tipo' => $tipo]);
        }
        foreach (array_keys(BiLimiteProducto::GRUPOS) as $productoId) {
            foreach (['', '100', '200'] as $terminal) {
                BiLimiteProducto::factory()->create(['producto_id' => $productoId, 'terminal' => $terminal, 'monto' => 100]);
            }
        }
        $reading = BiVentaHora::factory()->make([
            'tradicional_acumulado' => 120, 'no_tradicional_acumulado' => 100,
            'productos' => ['43' => 60, '44' => 60, '38' => 45, '39' => 55, '99' => 1000],
            'productos_terminales' => ['100' => ['43' => 40, '44' => 60, '38' => 20, '39' => 30, '99' => 1000]],
        ]);
        $service = app(AlertasProductos::class);
        $rows = collect($service->resumir($reading))->keyBy(fn (array $row): string => $row['producto_id'].'/'.$row['terminal']);

        $this->assertSame(120.0, $rows['grupo:tradicional/']['ventas']);
        $this->assertSame(100.0, $rows['grupo:no_tradicional/']['ventas']);
        $this->assertSame('Total Tradicionales', $rows['grupo:tradicional/']['nombre']);
        $this->assertTrue($rows['grupo:tradicional/']['esGrupo']);
        $this->assertSame('Total No tradicionales', $rows['grupo:no_tradicional/']['nombre']);
        $this->assertTrue($rows['grupo:tradicional/']['alerta']);
        $this->assertTrue($rows['grupo:no_tradicional/']['alerta']);
        $this->assertSame(100.0, $rows['grupo:tradicional/100']['ventas']);
        $this->assertSame(100.0, $rows['grupo:tradicional/100']['porcentaje']);
        $this->assertTrue($rows['grupo:tradicional/100']['alerta']);
        $this->assertSame(50.0, $rows['grupo:no_tradicional/100']['ventas']);
        $this->assertFalse($rows['grupo:no_tradicional/100']['alerta']);
        $this->assertSame(0.0, $rows['grupo:tradicional/200']['ventas']);

        $reading->productos_terminales = null;
        foreach ($service->resumir($reading) as $row) {
            if ($row['terminal'] !== '') {
                $this->assertNull($row['ventas']);
                $this->assertSame('Sin lectura', $row['estado']);
                $this->assertFalse($row['alerta']);
            }
        }
        foreach ($service->resumir(null) as $row) {
            $this->assertNull($row['ventas']);
            $this->assertNull($row['porcentaje']);
            $this->assertFalse($row['alerta']);
        }
    }

    public function test_saves_updates_and_pauses_one_limit_per_product(): void
    {
        $this->withoutMiddleware()->actingAs(new User(['name' => 'Usuario BI']));
        $this->from(route('bi.index'))->post(route('bi.limites-productos.guardar'), [
            'producto_id' => '43', 'monto' => 1000.25, 'activo' => true,
        ])->assertRedirect(route('bi.index'))->assertSessionHas('biLimiteMensaje');

        $this->assertSame('1000.25', BiLimiteProducto::query()->firstOrFail()->monto);
        $this->post(route('bi.limites-productos.guardar'), [
            'producto_id' => '43', 'monto' => 2000, 'activo' => false,
        ])->assertRedirect();

        $this->assertSame(1, BiLimiteProducto::query()->count());
        $this->assertSame('2000.00', BiLimiteProducto::query()->firstOrFail()->monto);
        $this->assertFalse(BiLimiteProducto::query()->firstOrFail()->activo);
    }

    public function test_rejects_invalid_product_amount_and_state(): void
    {
        $this->withoutMiddleware()->actingAs(new User(['name' => 'Usuario BI']));
        foreach ([
            ['producto_id', 'missing'],
            ['monto', 0],
            ['monto', -1],
            ['monto', 'invalid'],
            ['monto', 1.234],
            ['monto', 1000000000000],
            ['activo', 'invalid'],
        ] as [$field, $value]) {
            $payload = ['producto_id' => '43', 'monto' => 1000, 'activo' => true];
            $payload[$field] = $value;
            $this->postJson(route('bi.limites-productos.guardar'), $payload)
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame(0, BiLimiteProducto::query()->count());
    }

    public function test_alerts_at_exact_limit_and_above_and_can_be_paused(): void
    {
        $limit = BiLimiteProducto::factory()->create(['producto_id' => '43', 'monto' => 100]);
        $service = app(AlertasProductos::class);

        foreach ([[99.99, false], [100, true], [150, true]] as [$sales, $expected]) {
            $reading = BiVentaHora::factory()->make(['productos' => ['43' => $sales]]);
            $row = $service->resumir($reading)[0];
            $this->assertSame($expected, $row['alerta']);
            $this->assertSame('Quiniela Loteka', $row['nombre']);
            $this->assertFalse($row['esGrupo']);
        }

        $limit->update(['activo' => false]);
        $row = $service->resumir(BiVentaHora::factory()->make(['productos' => ['43' => 200]]))[0];
        $this->assertFalse($row['alerta']);
        $this->assertSame('Pausada', $row['estado']);
    }

    public function test_global_and_multiple_terminal_limits_coexist_and_update_independently(): void
    {
        Agencia::query()->create(['terminal' => '00100', 'sistema' => 'LOTOBET', 'nombre_agencia' => 'Agencia Norte']);
        Agencia::query()->create(['terminal' => '200', 'sistema' => 'LOTOBET', 'nombre_agencia' => 'Agencia Sur']);
        $this->withoutMiddleware()->actingAs(new User(['name' => 'Usuario BI']));
        foreach ([['global', '', 1000], ['terminal', '00100', 100], ['terminal', '200', 200]] as [$scope, $terminal, $amount]) {
            $this->post(route('bi.limites-productos.guardar'), [
                'producto_id' => '43', 'alcance' => $scope, 'terminal' => $terminal, 'monto' => $amount, 'activo' => true,
            ])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame(3, BiLimiteProducto::query()->count());
        $this->post(route('bi.limites-productos.guardar'), [
            'producto_id' => '43', 'alcance' => 'terminal', 'terminal' => '100', 'monto' => 150, 'activo' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(3, BiLimiteProducto::query()->count());
        $this->assertSame('1000.00', BiLimiteProducto::query()->where('terminal', '')->firstOrFail()->monto);
        $this->assertSame('200.00', BiLimiteProducto::query()->where('terminal', '200')->firstOrFail()->monto);
        $limit = BiLimiteProducto::query()->where('terminal', '100')->firstOrFail();
        $this->assertSame('150.00', $limit->monto);
        $this->assertFalse($limit->activo);
    }

    public function test_terminal_scope_requires_a_known_lotobet_terminal(): void
    {
        Agencia::query()->create(['terminal' => '300', 'sistema' => 'LOTONET']);
        $this->withoutMiddleware()->actingAs(new User(['name' => 'Usuario BI']));
        foreach (['', '999', '300'] as $terminal) {
            $this->postJson(route('bi.limites-productos.guardar'), [
                'producto_id' => '43', 'alcance' => 'terminal', 'terminal' => $terminal, 'monto' => 100, 'activo' => true,
            ])->assertUnprocessable()->assertJsonValidationErrors('terminal');
        }
        $this->postJson(route('bi.limites-productos.guardar'), [
            'producto_id' => '43', 'alcance' => 'unknown', 'monto' => 100, 'activo' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('alcance');
        $this->assertSame(0, BiLimiteProducto::query()->count());
    }

    public function test_sales_and_alerts_are_separate_for_global_and_each_terminal(): void
    {
        foreach (['', '100', '200'] as $terminal) {
            BiLimiteProducto::factory()->create(['producto_id' => '43', 'terminal' => $terminal, 'monto' => $terminal === '' ? 100 : 10]);
        }
        $reading = BiVentaHora::factory()->make([
            'productos' => ['43' => 120],
            'productos_terminales' => ['100' => ['43' => 8], '200' => ['43' => 112]],
        ]);
        $rows = collect(app(AlertasProductos::class)->resumir($reading))->keyBy('terminal');
        $this->assertTrue($rows['']['alerta']);
        $this->assertSame(120.0, $rows['']['ventas']);
        $this->assertSame('global', $rows['']['alcance']);
        $this->assertFalse($rows['100']['alerta']);
        $this->assertSame(8.0, $rows['100']['ventas']);
        $this->assertSame('terminal', $rows['100']['alcance']);
        $this->assertTrue($rows['200']['alerta']);
        $this->assertSame(112.0, $rows['200']['ventas']);

        $reading->productos_terminales = null;
        $rows = app(AlertasProductos::class)->resumir($reading);
        $this->assertSame('Sin lectura', $rows[1]['estado']);
        $this->assertNull($rows[1]['ventas']);
        $this->assertTrue($rows[0]['alerta']);
        $reading->productos_terminales = [];
        $this->assertSame(0.0, app(AlertasProductos::class)->resumir($reading)[1]['ventas']);
    }

    public function test_terminal_migration_preserves_existing_global_limits(): void
    {
        $migration = require database_path('migrations/2026_10_01_125650_add_terminal_scope_to_bi_limits.php');
        $migration->down();
        BiLimiteProducto::query()->create(['producto_id' => '43', 'monto' => 100, 'activo' => true]);
        $migration->up();
        $this->assertSame('', BiLimiteProducto::query()->firstOrFail()->terminal);
        $this->assertSame('100.00', BiLimiteProducto::query()->firstOrFail()->monto);
        BiLimiteProducto::factory()->create(['producto_id' => '43', 'terminal' => '100']);
        $this->assertSame(2, BiLimiteProducto::query()->count());
    }

    public function test_missing_snapshot_is_unknown_but_absent_product_in_valid_snapshot_is_zero(): void
    {
        BiLimiteProducto::factory()->create(['producto_id' => '43', 'monto' => 100]);
        $service = app(AlertasProductos::class);

        foreach ([null, BiVentaHora::factory()->make(['productos' => null])] as $reading) {
            $row = $service->resumir($reading)[0];
            $this->assertNull($row['ventas']);
            $this->assertNull($row['porcentaje']);
            $this->assertFalse($row['alerta']);
            $this->assertSame('Sin lectura', $row['estado']);
        }

        $row = $service->resumir(BiVentaHora::factory()->make(['productos' => []]))[0];
        $this->assertSame(0.0, $row['ventas']);
        $this->assertSame(0.0, $row['porcentaje']);
        $this->assertSame('Dentro del límite', $row['estado']);
    }
}
