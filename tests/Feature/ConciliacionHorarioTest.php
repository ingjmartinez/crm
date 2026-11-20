<?php

namespace Tests\Feature;

use App\Services\RecursosHumanos\ConciliacionHorarioService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConciliacionHorarioTest extends TestCase
{
    public function test_exports_use_the_calculated_hours_for_totals_and_missing_hours(): void
    {
        $fila = (object) ['horas_acumuladas' => 4.87];
        $novedades = new \App\Exports\NovedadesHorarioExport(collect([$fila]), 8, 100);
        $this->assertSame('Horas Calculadas', $novedades->headings()[8]);
        $this->assertSame('4.87', $novedades->map($fila)[8]);
        $this->assertSame('3.13', $novedades->map($fila)[10]);
        $this->assertSame('313.00', $novedades->map($fila)[11]);
        $verificador = new \App\Exports\VerificadorUsuariosExport([]);
        $this->assertSame('Horas Calculadas Total', $verificador->headings()[6]);
        $this->assertSame('4.87', $verificador->map((object) ['horas_total' => 4.87])[6]);
    }

    public function test_lotonet_uses_inactivity_exit_and_fallback_sales_without_pending_recargas(): void
    {
        Schema::create('asistencias_net', function (Blueprint $table): void {
            $table->id();
            $table->string('identificacion');
            $table->string('terminal');
            $table->dateTime('entrada')->nullable();
            $table->dateTime('salida')->nullable();
            $table->dateTime('salida_inactividad')->nullable();
        });
        Schema::create('nomina_domingo_ventas', function (Blueprint $table): void {
            $table->id();
            $table->string('usuario_venta');
            $table->string('terminal');
            $table->dateTime('fecha_transaccion');
            $table->string('tipo')->nullable();
            $table->string('estatus')->nullable();
        });
        try {
            DB::table('asistencias_net')->insert([
                'identificacion' => '001-1234567-8', 'terminal' => '00012', 'entrada' => '2026-11-16 09:00:00',
                'salida' => null, 'salida_inactividad' => '2026-11-16 16:30:00',
            ]);
            foreach (['09:15:00', '16:35:00'] as $hora) {
                DB::table('nomina_domingo_ventas')->insert([
                    'usuario_venta' => '00112345678', 'terminal' => '12', 'fecha_transaccion' => '2026-11-16 '.$hora,
                ]);
            }
            DB::table('nomina_domingo_ventas')->insert([
                'usuario_venta' => '00112345678', 'terminal' => '12', 'fecha_transaccion' => '2026-11-16 19:30:00', 'tipo' => 'Recargas', 'estatus' => 'Pendiente',
            ]);
            $service = app(ConciliacionHorarioService::class);
            $php = $service->consultar('2026-11-16', '2026-11-16', 'lotonet')->first();
            $sql = DB::selectOne('SELECT * FROM '.$service->fuenteSql('lotonet', '2026-11-16', '2026-11-16').' diario');
            $this->assertSame('2026-11-16 09:00:00', $php['entrada_calculada']);
            $this->assertSame('2026-11-16 16:40:00', $php['salida_calculada']);
            $this->assertSame(7.67, $php['hora_calculada']);
            $this->assertSame($php['entrada_calculada'], $sql->entrada_calculada);
            $this->assertSame($php['salida_calculada'], $sql->salida_calculada);
            $this->assertEquals($php['segundos_calculados'], $sql->segundos_calculados);
            $this->assertSame(7.67, $service->totalesPorCedula('2026-11-16', '2026-11-16', 'Todos', ['00112345678'])->get('00112345678'));
        } finally {
            Schema::dropIfExists('asistencias_net');
            Schema::dropIfExists('nomina_domingo_ventas');
        }
    }

    public function test_sql_and_php_use_the_same_daily_rules_without_counting_duplicate_punches(): void
    {
        Schema::create('asistencias_bet', function (Blueprint $table): void {
            $table->id();
            $table->string('cedula');
            $table->string('agencia_id');
            $table->date('fecha');
            $table->dateTime('primer_login')->nullable();
            $table->dateTime('ultimo_login')->nullable();
        });
        (require database_path('migrations/2026_10_14_125659_create_ponche_por_cedula_ventas_table.php'))->up();
        try {
            foreach ([
                ['40209126321', '08:06:44', '18:25:27'],
                ['40226483440', '08:16:17', '13:34:12'],
                ['40209126321', '08:06:44', '18:25:27'],
                ['00000000001', null, null],
                ['00000000002', '19:00:00', '20:00:00'],
            ] as [$cedula, $entrada, $salida]) {
                DB::table('asistencias_bet')->insert([
                    'cedula' => $cedula, 'agencia_id' => $cedula === '00000000001' ? '00099' : '051913', 'fecha' => '2026-11-15',
                    'primer_login' => $entrada ? '2026-11-15 '.$entrada : null, 'ultimo_login' => $salida ? '2026-11-15 '.$salida : null,
                ]);
            }
            foreach ([
                ['40209126321', '13:40:21', '51913'], ['40209126321', '18:25:27', '051913'],
                ['40226483440', '08:30:47', '051913'], ['40226483440', '13:34:12', '51913'],
                ['00000000001', '00:01:00', '99'], ['00000000001', '01:00:00', '99'],
            ] as [$cedula, $hora, $terminal]) {
                DB::table('ponche_por_cedula_ventas')->insert([
                    'usuario_venta' => $cedula, 'terminal' => $terminal, 'fecha_transaccion' => '2026-11-15 '.$hora, 'tipo' => 'Tradicional',
                ]);
            }
            $service = app(ConciliacionHorarioService::class);
            $php = $service->consultar('2026-11-15', '2026-11-15', 'lotobet')->keyBy('cedula');
            $sql = collect(DB::select('SELECT * FROM '.$service->fuenteSql('lotobet', '2026-11-15', '2026-11-15').' diario'))->keyBy('cedula');
            $this->assertCount(4, $php);
            $this->assertCount(4, $sql);
            foreach ($php as $cedula => $fila) {
                $this->assertSame($fila['entrada_calculada'], $sql[$cedula]->entrada_calculada);
                $this->assertSame($fila['salida_calculada'], $sql[$cedula]->salida_calculada);
                $this->assertEquals($fila['segundos_calculados'], $sql[$cedula]->segundos_calculados);
            }
            $this->assertSame(4.87, $php['40209126321']['hora_calculada']);
            $this->assertSame(5.38, $php['40226483440']['hora_calculada']);
            $this->assertSame('2026-11-14 23:59:00', $php['00000000001']['entrada_calculada']);
            $this->assertSame(1.0, $php['00000000002']['hora_calculada']);
            $horasIncentivos = (new \ReflectionMethod(\App\Http\Controllers\IncentivosController::class, 'obtenerHorasTotalesReporteNuevoIncentivoV5'))->invoke(
                app(\App\Http\Controllers\IncentivosController::class), '2026-11-15', '2026-11-15', 'Lotobet', ['40209126321', '40226483440']
            );
            $this->assertSame(4.87, $horasIncentivos['40209126321']);
            $this->assertSame(5.38, $horasIncentivos['40226483440']);
            $api = $service->registroApi([
                'cedula' => '40209126321', 'terminal' => '051913', 'entrada' => '08:06:44', 'salida' => '18:25:27',
            ], '2026-11-15', $service->actividad('2026-11-15', '2026-11-15'));
            $this->assertTrue($api['tiene_cruce']);
            $this->assertSame(4.87, $api['hora_calculada']);
        } finally {
            Schema::dropIfExists('ponche_por_cedula_ventas');
            Schema::dropIfExists('asistencias_bet');
        }
    }
}
