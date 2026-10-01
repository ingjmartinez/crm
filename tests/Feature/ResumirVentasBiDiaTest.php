<?php

namespace Tests\Feature;

use App\Models\BiPremioDia;
use App\Models\BiVentaDia;
use App\Services\Lotobet\LotobetSessionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ResumirVentasBiDiaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('catalogo_juegos', function (Blueprint $table): void {
            $table->id();
            $table->string('producto_id');
            $table->string('tipo')->nullable();
        });
        Schema::create('vt_usuarios_bet', function (Blueprint $table): void {
            $table->id();
            $table->string('producto_id');
            $table->string('tipo')->nullable();
            $table->decimal('monto', 14, 2);
            $table->date('fecha');
        });
        Schema::create('premios_bet', function (Blueprint $table): void {
            $table->id('premio_id');
            $table->decimal('monto', 14, 2);
            $table->date('fecha');
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
    }

    public function test_daily_summary_is_idempotent_and_classifies_all_categories(): void
    {
        DB::table('catalogo_juegos')->insert([
            ['producto_id' => '1', 'tipo' => 'Tradicional'],
            ['producto_id' => '2', 'tipo' => 'No Tradicional'],
            ['producto_id' => '3', 'tipo' => 'Recargas'],
            ['producto_id' => '5', 'tipo' => ''],
        ]);
        DB::table('vt_usuarios_bet')->insert([
            ['producto_id' => '1', 'tipo' => 'Otro', 'monto' => 100, 'fecha' => '2026-09-28'],
            ['producto_id' => '2', 'tipo' => null, 'monto' => 40, 'fecha' => '2026-09-28'],
            ['producto_id' => '3', 'tipo' => null, 'monto' => 20, 'fecha' => '2026-09-28'],
            ['producto_id' => '4', 'tipo' => 'Externa', 'monto' => 5, 'fecha' => '2026-09-28'],
            ['producto_id' => '5', 'tipo' => 'Recarga', 'monto' => 7, 'fecha' => '2026-09-28'],
            ['producto_id' => '1', 'tipo' => null, 'monto' => 999, 'fecha' => '2026-09-27'],
        ]);
        DB::table('premios_bet')->insert([
            ['monto' => 60, 'fecha' => '2026-09-28'],
            ['monto' => 999, 'fecha' => '2026-09-27'],
        ]);

        $this->artisan('bi:resumir-ventas-dia', ['--fecha' => '2026-09-28'])->assertSuccessful();
        $this->artisan('bi:resumir-ventas-dia', ['--fecha' => '2026-09-28'])->assertSuccessful();

        $resumen = BiVentaDia::query()->firstOrFail();
        $this->assertSame(1, BiVentaDia::query()->count());
        $this->assertSame('100.00', $resumen->tradicional);
        $this->assertSame('40.00', $resumen->no_tradicional);
        $this->assertSame('27.00', $resumen->recargas);
        $this->assertSame('5.00', $resumen->externas);
        $this->assertSame(5, $resumen->registros);
        $this->assertSame('vt_usuarios_bet', $resumen->origen);
        $this->assertSame(1, BiPremioDia::query()->count());
        $this->assertSame('60.00', BiPremioDia::query()->firstOrFail()->premios);
        $this->assertSame('premios_bet', BiPremioDia::query()->firstOrFail()->origen);

        DB::table('vt_usuarios_bet')->where('fecha', '2026-09-28')->update(['monto' => 0]);
        $this->artisan('bi:resumir-ventas-dia', ['--fecha' => '2026-09-28'])->assertSuccessful();
        $this->assertSame('0.00', BiVentaDia::query()->firstOrFail()->tradicional);
    }

    public function test_product_record_backfill_and_daily_refresh_use_bet_sales(): void
    {
        $ayer = today()->subDay()->toDateString();
        DB::table('vt_usuarios_bet')->insert([
            ['producto_id' => '43', 'tipo' => 'Tradicional', 'monto' => 100, 'fecha' => $ayer],
            ['producto_id' => '43', 'tipo' => 'Tradicional', 'monto' => 75, 'fecha' => $ayer],
            ['producto_id' => '44', 'tipo' => 'Tradicional', 'monto' => 900, 'fecha' => $ayer],
            ['producto_id' => '38', 'tipo' => 'No Tradicional', 'monto' => 80, 'fecha' => $ayer],
        ]);

        $this->artisan('bi:resumir-productos')->assertSuccessful();
        $this->assertSame(175.0, (float) DB::table('bi_producto_dias')->where('producto_id', '43')->where('fecha', $ayer)->value('monto'));
        $this->assertSame(80.0, (float) DB::table('bi_producto_dias')->where('producto_id', '38')->where('fecha', $ayer)->value('monto'));

        DB::table('vt_usuarios_bet')->where('producto_id', '43')->where('fecha', $ayer)->update(['monto' => 50]);
        $this->artisan('bi:resumir-ventas-dia', ['--fecha' => $ayer])->assertSuccessful();
        $this->assertSame(100.0, (float) DB::table('bi_producto_dias')->where('producto_id', '43')->where('fecha', $ayer)->value('monto'));
        $this->assertSame(80.0, (float) DB::table('bi_producto_dias')->where('producto_id', '38')->where('fecha', $ayer)->value('monto'));
    }

    public function test_command_rejects_today_as_a_closed_day(): void
    {
        $this->artisan('bi:resumir-ventas-dia', ['--fecha' => CarbonImmutable::today()->toDateString()])
            ->assertFailed();

        $this->assertSame(0, BiVentaDia::query()->count());
    }

    public function test_missing_database_day_never_uses_the_api(): void
    {
        $this->mock(LotobetSessionService::class)
            ->shouldReceive('getVentasProducto')
            ->never();

        $this->artisan('bi:resumir-ventas-dia', ['--fecha' => '2026-09-29'])->assertSuccessful();

        $resumen = BiVentaDia::query()->firstOrFail();
        $this->assertSame('vt_usuarios_bet', $resumen->origen);
        $this->assertSame('0.00', $resumen->tradicional);
        $this->assertSame('0.00', $resumen->recargas);
        $this->assertSame(0, $resumen->registros);
    }

    public function test_range_command_updates_a_missing_day_after_source_data_arrives(): void
    {
        $this->artisan('bi:resumir-ventas-dia', ['--fecha' => '2026-09-28'])->assertSuccessful();
        DB::table('vt_usuarios_bet')->insert([
            'producto_id' => '1',
            'tipo' => 'Tradicional',
            'monto' => 75,
            'fecha' => '2026-09-28',
        ]);

        $this->artisan('bi:resumir-ventas-dia', ['--solo-faltantes' => true])->assertSuccessful();

        $this->assertSame('75.00', BiVentaDia::query()->firstOrFail()->tradicional);
    }
}
